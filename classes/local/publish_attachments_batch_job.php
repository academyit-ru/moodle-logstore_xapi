<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Задание по расписанию для отправки артефакторв обучения в S3
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi\local;

use coding_exception;
use logstore_xapi\event\attachment_published;
use logstore_xapi\local\persistent\queue_item;
use logstore_xapi\local\persistent\xapi_attachment;
use moodle_database;
use moodle_exception;
use Aws\S3\Exception\S3Exception;
use core_text;
use stored_file;
use Throwable;
use zip_packer;

class publish_attachments_batch_job extends base_batch_job {

    /**
     * @var queue_item[]
     */
    protected $queueitems;

    /**
     * @var log_event[]
     */
    protected $events;

    /**
     * @var queue_item[]
     */
    protected $resulterror;

    /**
     * @var queue_item[]
     */
    protected $resultsuccess;

    /**
     * @var xapi_attachment[]
     */
    protected $xapiattachmentsrecords;

    /**
     * @var moodle_database
     */
    protected $db;

    /**
     * @var s3client_interface
     */
    protected $s3client;
    /**
     * @param queue_item[] $queueitems
     * @param moodle_database $db
     */
    public function __construct(array $queueitems, moodle_database $db, s3client_interface $s3client) {
        $this->queueitems = $queueitems;
        $this->events = [];
        $this->resulterror = [];
        $this->resultsuccess = [];
        $this->xapiattachmentsrecords = [];
        $this->db = $db;
        $this->s3client = $s3client;
    }

    /**
     * @inheritdoc
     */
    public function result_success() {
        return $this->resultsuccess;
    }

    /**
     * @inheritdoc
     */
    public function result_error() {
        return $this->resulterror;
    }

    /**
     * @inheritdoc
     */
    public function run() {
        $logevents = $this->get_events();
        foreach ($this->queueitems as $qitem) {

            $logevent = $logevents[$qitem->get('logrecordid')];
            try {
                /// 1. Подготовка архива с артефактами к отправке в S3 ///
                $attachmentname = $this->get_attachment_filename($logevent);
                $finder = file_finder::factory($logevent);
                $files = $finder->find_for($logevent);
                if ([] === $files) {
                    $errormsg = sprintf(
                        '%s: Не найдены файлы для события журнала id:%d name:%s',
                        static::class, $logevent->id, $logevent->eventname
                    );
                    throw new coding_exception($errormsg);
                }
                $ziparchivepath = $this->pack_to_zip($files);
                if (false === $ziparchivepath) {
                    $errormsg = sprintf(
                        '%s: Не удалось создать zip-архив для файлов события журнала id:%d name:%s',
                        static::class, $logevent->id, $logevent->eventname
                    );
                    throw new coding_exception($errormsg);
                }
                $handle = fopen($ziparchivepath, 'r');

                /// 2. Загрузка созданного архива в S3 ///
                try {
                    $uploadresult = $this->s3client->upload($attachmentname, $handle);
                } catch (S3Exception $e) {
                    fclose($handle);
                    $errormsg = sprintf(
                        '%s: При отправке файлов в S3 для события журнала id:%d name:%s возникла ошибка: %s',
                        static::class, $logevent->id, $logevent->eventname, $e->getMessage()
                    );
                    throw new coding_exception($errormsg);
                }
                fclose($handle);

                /// 3. Сохраняем информацию о загруженном артефакте для отправки в LRS ///
                $xapiattachment = new xapi_attachment(0, (object) [
                    'eventid' => $logevent->id,
                    's3_url' => $uploadresult['url'],
                    's3_filename' => $attachmentname,
                    's3_sha2' => hash_file('sha256', $ziparchivepath),
                    's3_filesize' => filesize($ziparchivepath),
                    's3_contenttype' => mime_content_type($ziparchivepath),
                    's3_etag' => $uploadresult['etag'],
                ]);
                try {
                    $xapiattachment->save();
                } catch (moodle_exception $e) {
                    $qitem->mark_as_banned(); // Файл уже отправлен поэтому нужно убрать задачу из очереди
                    $errormsg = sprintf(
                        '%s: Не удалось сохранить запись для загруженного файла для события журнала id:%d name:%s возникла ошибка: %s',
                        static::class, $logevent->id, $logevent->eventname, $e->getMessage()
                    );
                    throw new coding_exception($errormsg);
                }

            } catch (moodle_exception $e) {
                // Сохраняем ошибку возникшую на одном из этапов и переходим к следующему событию в очереди
                $errmsg = sprintf('%s debug: %s trace: %s', $e->getMessage(), $e->debuginfo, $e->getTraceAsString());
                $qitem->set('lasterror', $errmsg);
                $this->resulterror[] = $qitem;
                mtrace(sprintf('---- moodle_exception thrown wile handling queue item id:%d Error: %s', $qitem->get('id'), $errmsg));
                continue;
            } catch (Throwable $e) {
                // Сохраняем ошибку возникшую на одном из этапов и переходим к следующему событию в очереди
                $qitem->set('lasterror', sprintf('%s trace: %s', $e->getMessage(), $e->getTraceAsString()));
                $this->resulterror[] = $qitem;
                mtrace(sprintf('---- Exception thrown wile handling queue item id:%d Error: %s', $qitem->get('id'), $e->getMessage()));
                mtrace(sprintf('---- Exception trace: %s', $e->getTraceAsString()));
                continue;
            }

            $this->resultsuccess[] = $qitem;
            $this->xapiattachmentsrecords[] = $xapiattachment;

            try {
                // Регистрируем событие attachment_published которое будет перехвачено в observer'е и для события журнала
                // будет добавлена задача для отправки её xAPI выражения в LRS
                $event = attachment_published::create_from_record($xapiattachment, $qitem, $logevent);
                $event->trigger();
            } catch (moodle_exception $e) {
                $errormsg = sprintf(
                    '%s: Ошибка при обработке события %s, error: %s trace: %s',
                    static::class, attachment_published::class, $e->getMessage(), $e->getTraceAsString()
                );
                error_log($errormsg);
                continue;
            }
        }
    }

    /**
     * @param stored_file[] $files
     *
     * @return string|bool путь к архиву во временной папке или false если не удалось создать архив
     */
    protected function pack_to_zip(array $files) {
        global $CFG;

        $tempzip = tempnam($CFG->tempdir.'/', 'logstore_xapi_attachmnts');
        $filelist = [];
        /** @var stored_file $file */
        foreach ($files as $file) {
            $filename = $file->get_filename();
            $filelist[$filename] = $file;
        }
        $zipper = new zip_packer();
        if (false === $zipper->archive_to_pathname($filelist, $tempzip)) {
            debugging("Problems with archiving the files.", DEBUG_DEVELOPER);
            return false;
        }

        return $tempzip;
    }

    /**
     * @param log_event $logevent запись из logstore_xapi_log
     * @return string Название файла для публикации в S3
     */
    protected function get_attachment_filename(log_event $logevent) {
        /** @var moodle_database $DB */
        global $DB;

        $courseshortname = $DB->get_field('course', 'shortname', ['id' => $logevent->courseid], MUST_EXIST);
        $courseshortname = str_replace(' ', '_', $courseshortname);
        $courseshortnametranslit = core_text::convert($courseshortname, 'utf-8', 'ascii');
        $cmid = $logevent->contextinstanceid;
        $untiid = $DB->get_field(
            'user',
            'idnumber',
            ['id' => $logevent->relateduserid, 'auth' => 'untissooauth'],
            MUST_EXIST
        );

        return vsprintf(
            '%s_cmid%s_untiid%s.zip',
            [$courseshortnametranslit, $cmid, $untiid]
        );
    }

}
