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
 * Артефакт обучения был успешно загружен в S3.
 *
 * @package    logstore_xapi
 * @author    Victor Kilikaev vkilikaev@it.ru
 * @copyright academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi\event;

use logstore_xapi\local\persistent\queue_item;
use logstore_xapi\local\persistent\xapi_attachment;

/**
 * Event класс события attachment_published.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int instanceid: Id of instance.
 *      - int roleid: Role id for whom report is viewed.
 *      - int groupid: (optional) group id.
 *      - int timefrom: (optional) time from which report is viewed.
 *      - string action: (optional) action viewed.
 * }
 */
class attachment_published extends \core\event\base {

    /**
     * @param queue_item $qitem
     */
    public static function create_from_record(xapi_attachment $record, queue_item $qitem, $logrecord) {
        $event = self::create([
            'context' => \context_system::instance(),
            'objectid' => $record->id,
            'objecttable' => xapi_attachment::TABLE,
            'other' => [
                'logrecordid' => $record->get('eventid'),
                's3_url' => $record->get('s3_url'),
                'qitemid' => $qitem->get('id'),
                'queue' => $qitem->get('queue'),
            ]
        ]);

        $event->add_record_snapshot(xapi_attachment::TABLE, $record);
        $event->add_record_snapshot('logstore_xapi_log', $logrecord);
        $event->add_record_snapshot(queue_item::TABLE, $qitem);

        return $event;
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('attachment_published_event', 'logstore_xapi');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return sprintf(
            'Артефакт обучения id:%d связанный с событием журнала id:%d был отправлен в S3',
            $this->objectid,
            $this->other['logrecordid']
        );
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url(
            '/admin/tool/log/store/xapi/queues/view.php',
            [
                'id' => $this->other['qitemid'],
                'queue' => $this->other['queue'],
            ]
        );
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (empty($this->other['logrecordid'])) {
            throw new \coding_exception('Не указано значение для logrecordid');
        }
        if (empty($this->other['s3_url'])) {
            throw new \coding_exception('Не указано значение для s3_url');
        }
        if (empty($this->other['qitemid'])) {
            throw new \coding_exception('Не указано значение для qitemid');
        }
        if (empty($this->other['queue'])) {
            throw new \coding_exception('Не указано значение для queue');
        }
    }
}
