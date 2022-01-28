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
 * Содержит тесткейс для класса logstore_xapi\task\emit_statement.
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
use logstore_xapi\local\emit_statements_batch_job;
use logstore_xapi\local\log_event;
use logstore_xapi\local\persistent\xapi_record;
use logstore_xapi\local\queue_service;
use logstore_xapi\task\emit_statement;
use logstore_xapi\task\enqueue_jobs;
use moodle_database;
use assign;
use src\loader\utils as utils;

require_once dirname(__DIR__) . '/src/autoload.php';

/**
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class emit_statements_batch_job_testcase extends advanced_testcase {

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
     *      - В журнале logstore_xapi_log есть записи о его работе
     *      - В очереди EMIT_STATEMENTS N записей связанные с N записей из таблицы logstore_xapi_log
     *      - В таблице {@see xapi_record::TABLE} пусто
     * Выполнить:
     *      - Выполнить $job->run()
     * Результат:
     *      - В таблице {@see xapi_record::TABLE} созданы записи связанные с записями из logstore_xapi_log
     *      - У каждой записи колонки lrs_uuid и body содержат данные полученные из LRS
     */
    public function test_successful_run_creates_xapi_records() {
        /** @var moodle_database $DB */
        global $DB;

        $this->resetAfterTest();

        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course();
        $assignparams = [
            'course' => $course1->id,
            'assignsubmission_file_enabled' => 1,
            'assignsubmission_file_maxfiles' => 1,
            'assignsubmission_file_maxsizebytes' => 10
        ];
        $assign = $this->getDataGenerator()->create_module('assign', $assignparams);
        $this->getDataGenerator()->enrol_user($student->id, $course1->id);
        $this->getDataGenerator()->enrol_user($teacher->id, $course1->id, $this->teacherroleid);
        $this->create_log_events($student, $teacher, $assign, $course1);
        $this->create_queue_items();

        $queueservice = queue_service::instance();
        $queuerecords = $queueservice->pop(emit_statement::DEFAULT_BATCH_SIZE, queue_service::QUEUE_EMIT_STATEMENTS);

        $job = new emit_statements_batch_job($queuerecords, $DB);
        $job->set_loader([$this, 'loader']);
        $job->run();

        $this->assertEquals(log_event::count_records(), xapi_record::count_records());
        $this->assertEquals(0, xapi_record::count_records(['lrs_uuid' => NULL]));
        $this->assertEquals(0, xapi_record::count_records_select('body IS NULL'));

        $this->assertCount(3, $job->result_success());
        $this->assertCount(0, $job->result_error());
    }

    /**
     *
     *
     */
    protected function create_log_events($student, $teacher, $assignrecord, $course) {
        /** @var moodle_database $DB */
        global $DB;
        $cm = get_coursemodule_from_instance('assign', $assignrecord->id);
        $context = context_module::instance($cm->id);
        $assign = new assign($context, $cm, $course);
        $submission = $assign->get_user_submission($student->id, true);
        $coursecontext = context_course::instance($course->id);
        $logevents = [
            [
                "eventname" => '\core\event\course_viewed',
                "component" => 'core',
                "action" => 'viewed',
                "target" => 'course',
                "objecttable" => '',
                "objectid" => '',
                "crud" => 'r',
                "edulevel" => 2,
                "contextid" => $coursecontext->id,
                "contextlevel" => 50,
                "contextinstanceid" => $course->id,
                "userid" => $student->id,
                "courseid" => $course->id,
                "relateduserid" => null,
                "anonymous" => "0",
                "other" => 'N;',
                "timecreated" => 1643120576,
                "origin" => 'web',
                "ip" => '127.0.0.1',
                "realuserid" => null,
            ],
            [
                "eventname" => '\core\event\course_viewed',
                "component" => 'core',
                "action" => 'viewed',
                "target" => 'course',
                "objecttable" => '',
                "objectid" => '',
                "crud" => 'r',
                "edulevel" => 2,
                "contextid" => $coursecontext->id,
                "contextlevel" => 50,
                "contextinstanceid" => $course->id,
                "userid" => $teacher->id,
                "courseid" => $course->id,
                "relateduserid" => null,
                "anonymous" => "0",
                "other" => 'N;',
                "timecreated" => 1643120576,
                "origin" => 'web',
                "ip" => '127.0.0.1',
                "realuserid" => null,
            ],
            [
                "eventname" => '\mod_assign\event\assessable_submitted',
                "component" => 'mod_assign',
                "action" => 'submitted',
                "target" => 'assessable',
                "objecttable" => 'assign_submission',
                "objectid" => $submission->id,
                "crud" => 'u',
                "edulevel" => 2,
                "contextid" => $assign->get_context()->id,
                "contextlevel" => 70,
                "contextinstanceid" => $assign->get_instance()->id,
                "userid" => $student->id,
                "courseid" => $course->id,
                "relateduserid" => null,
                "anonymous" => "0",
                "other" => serialize(['submission_editable' => true]),
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

    /**
     * @param array $config
     * @param array $events
     *
     * @return mixed
     */
    public function loader(array $config, array $events) {
        $sendhttpstatements = function (array $config, array $statements) {
            return json_encode(
                [
                    "f9711fde-7928-472a-b34a-0e3e19401d34",
                    "45bee79a-e12c-43e9-b808-78430c43820c",
                    "f608c114-3f43-4291-9a6a-161fd53efde4",
                ]
            );
        };

        $result = utils\load_in_batches($config, $events, $sendhttpstatements);
        return $result;
    }
}
