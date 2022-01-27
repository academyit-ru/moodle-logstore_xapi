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
 * Содержит тесткейс для класса logstore_xapi\task\publish_attachments_batch_job.
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi;

use advanced_testcase;
use context_course;
use context_module;
use DateTimeImmutable;
use logstore_xapi\event\attachment_published;
use logstore_xapi\local\emit_statements_batch_job;
use logstore_xapi\local\log_event;
use logstore_xapi\local\persistent\queue_item;
use logstore_xapi\local\persistent\xapi_attachment;
use logstore_xapi\local\persistent\xapi_record;
use logstore_xapi\local\publish_attachments_batch_job;
use logstore_xapi\local\queue_service;
use logstore_xapi\local\s3client_interface;
use logstore_xapi\task\emit_statement;
use logstore_xapi\task\enqueue_jobs;
use moodle_database;
use testable_assign;
use src\loader\utils as utils;

require_once dirname(__DIR__) . '/src/autoload.php';

/**
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class publish_attachments_batch_job_testcase extends advanced_testcase {

    protected $teacherroleid;

    /**
     *
     *
     */
    protected function setUp() {
        /** @var moodle_database $DB */
        global $DB;

        $this->teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
    }

    /**
     *
     * Дано:
     *      - Есть курс
     *      - На курсе есть модуль задания
     *      - На курс записан студент и преподаватель
     *      - В журнале logstore_xapi_log есть записи о его работе (загружен файл для задания)
     *      - В очереди PUBLISH_ATTACHMENTS N записей связанные с N записей из таблицы logstore_xapi_log
     *      - В таблице {@see xapi_attachment::TABLE} пусто
     * Выполнить:
     *      - Выполнить $job->run()
     * Результат:
     *      - В таблице {@see xapi_attachment::TABLE} созданы записи связанные с записями из logstore_xapi_log
     *      - У каждой записи колонки s3_url, s3_filename, s3_sha2, s3_filesize, s3_contenttype содержат данные полученные из LRS
     *      - После отправки артефактов в очереди EMIT_STATEMENTS появилась запись для отправки выражения в LRS
     */
    public function test_successful_run_creates_xapi_attachment_records() {
        /** @var moodle_database $DB */
        global $DB;

        $this->resetAfterTest();

        $student = $this->getDataGenerator()->create_user([
            'auth' => 'untissooauth',
            'idnumber' => '123123123'
        ]);
        $teacher = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course();
        $assignparams = [
            'course' => $course1->id,
            'assignsubmission_file_enabled' => 1,
            'assignsubmission_file_maxfiles' => 1,
            'assignsubmission_file_maxsizebytes' => 10
        ];
        $instance = $this->getDataGenerator()->create_module('assign', $assignparams);
        $this->getDataGenerator()->enrol_user($student->id, $course1->id);
        $this->getDataGenerator()->enrol_user($teacher->id, $course1->id, $this->teacherroleid);

        $cm = get_coursemodule_from_instance('assign', $instance->id);
        $context = context_module::instance($cm->id);
        $assign = new testable_assign($context, $cm, $course1);

        $this->setUser($student->id);
        $submission = $assign->get_user_submission($student->id, true);

        $fs = get_file_storage();
        $dummy = (object) [
            'contextid' => $context->id,
            'component' => 'assignsubmission_file',
            'filearea' => ASSIGNSUBMISSION_FILE_FILEAREA,
            'itemid' => $submission->id,
            'filepath' => '/',
            'filename' => 'myassignmnent.pdf'
        ];
        $fs->create_file_from_string($dummy, 'Content of ' . $dummy->filename);
        $dummy = (object) [
            'contextid' => $context->id,
            'component' => 'assignsubmission_file',
            'filearea' => ASSIGNSUBMISSION_FILE_FILEAREA,
            'itemid' => $submission->id,
            'filepath' => '/',
            'filename' => 'myassignmnent.png'
        ];
        $fs->create_file_from_string($dummy, 'Content of ' . $dummy->filename);
        $fs->get_area_files(
            $context->id,
            'assignsubmission_file',        // $component
            ASSIGNSUBMISSION_FILE_FILEAREA, // $filearea
            $submission->id,
            "id",                           // $sort
            false                           // $includedirs
        );
        $plugin = $assign->get_submission_plugin_by_type('file');
        $plugin->save($submission, new \stdClass());

        $grade = $assign->get_user_grade($student->id, true);
        $grade->grader = $teacher->id;
        $grade->grade = 100;
        $assign->update_grade($grade);


        $this->create_log_events($student, $teacher, $assign, $course1, $grade);
        $this->create_queue_items();

        $queueservice = queue_service::instance();
        $queuerecords = $queueservice->pop(emit_statement::DEFAULT_BATCH_SIZE, queue_service::QUEUE_PUBLISH_ATTACHMENTS);

        $s3client = new class implements s3client_interface {
            public function upload($key, $body, $bucketname = null,  $acl = 'public-read') {
                return [
                    'etag' => md5('foo bar baz'),
                    'url' => "https://s3.example.com/{$key}",
                    'lastmodified' => new DateTimeImmutable(),
                ];
            }
        };

        $this->assertEquals(0, xapi_attachment::count_records());
        $queueitemcount = queue_item::count_records_select(
            "queue = :queue AND " . $DB->sql_like('itemkey', ':itemkey'),
            ['queue' => queue_service::QUEUE_EMIT_STATEMENTS, 'itemkey' => "'%mod_assign_event_submission_graded%'"]);
        $this->assertEquals(0, $queueitemcount);

        $sink = $this->redirectEvents();

        $job =  new publish_attachments_batch_job($queuerecords, $DB, $s3client);
        $job->run();

        $this->assertEquals(1, xapi_attachment::count_records());
        $fields = ['s3_url', 's3_filename', 's3_sha2', 's3_filesize', 's3_contenttype'];
        $xapiattachment = xapi_attachment::get_record();
        foreach ($fields as $field) {
            $this->assertNotEmpty($xapiattachment->get($field), "$field не задано");
        }

        $events = $sink->get_events();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(attachment_published::class, $event);

        $this->assertCount(1, $job->result_success());
        $this->assertCount(0, $job->result_error());
    }

    /**
     *
     *
     */
    protected function create_log_events($student, $teacher, testable_assign $assign, $course, $grade) {
        /** @var moodle_database $DB */
        global $DB;
        $logevents = [
            [
                "eventname" => '\mod_assign\event\submission_graded',
                "component" => 'mod_assign',
                "action" => 'graded',
                "target" => 'submission',
                "objecttable" => 'assign_grades',
                "objectid" => $grade->id,
                "crud" => 'u',
                "edulevel" => 1,
                "contextid" => $assign->get_context()->id,
                "contextlevel" => 70,
                "contextinstanceid" => $assign->get_instance()->id,
                "userid" => $teacher->id,
                "courseid" => $course->id,
                "relateduserid" => $student->id,
                "anonymous" => "0",
                "other" => 'N;',
                "timecreated" => 1643120576,
                "origin" => 'web',
                "ip" => '127.0.0.1',
                "realuserid" => null,
            ],
        ];

        $DB->insert_records('logstore_xapi_log', $logevents);

        return log_event::get_records();
    }

    protected function create_queue_items() {
        $enqueuejobstask = new enqueue_jobs();
        $enqueuejobstask->execute();
    }
}
