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
 * Содержит тесткейс для класс logstore_xapi\task\emit_statement
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi;

use advanced_testcase;
use assign;
use context_course;
use context_module;
use logstore_xapi\local\log_event;
use logstore_xapi\task\emit_statement;
use logstore_xapi\task\enqueue_jobs;
use moodle_database;

class emit_statement_task_test extends advanced_testcase {

    /**
     *
     *
     */
    public function test_new_emit_statements_batch_job() {
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
        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $assign = $this->getDataGenerator()->create_module('assign', $assignparams);
        $this->getDataGenerator()->enrol_user($student->id, $course1->id);
        $this->getDataGenerator()->enrol_user($teacher->id, $course1->id, $teacherroleid);
        $this->create_log_events($student, $teacher, $assign, $course1);
        $this->create_queue_items();

        set_config('loader_lrs', 'log', 'logstore_xapi');
        $task = new emit_statement();
        $task->execute();
        $this->expectOutputRegex(sprintf('|.*"id"\: "https:\\\/\\\/www.example.com\\\/moodle\\\/course\\\/view.php\?id=%s".*|', $course1->id));
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
}
