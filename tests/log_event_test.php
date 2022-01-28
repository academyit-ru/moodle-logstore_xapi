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
 * Содержит тесткейс для класса logstore_xapi\local\log_event.
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi;

use advanced_testcase;
use context_module;
use logstore_xapi\local\log_event;
use moodle_database;
use assign;

class log_event_testcase extends advanced_testcase {

    public function test_has_attachments_return_true_for_submission_graded_with_files() {

        $this->resetAfterTest();
        $eventid = $this->prepare_submission_graded_with_files();
        $logevent = log_event::get_record(['id' => $eventid]);

        $this->assertTrue($logevent->has_attachments());
    }

    /**
     *
     *
     */
    protected function prepare_submission_graded_with_files() {
        /** @var moodle_database $DB */
        global $DB;
        global $USER;

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
        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $instance = $this->getDataGenerator()->create_module('assign', $assignparams);
        $this->getDataGenerator()->enrol_user($student->id, $course1->id);
        $this->getDataGenerator()->enrol_user($teacher->id, $course1->id, $teacherroleid);

        $cm = get_coursemodule_from_instance('assign', $instance->id);
        $context = context_module::instance($cm->id);
        $assign = new assign($context, $cm, $course1);

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

        $logevent = [
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
            "contextinstanceid" => $assign->get_context()->instanceid,
            "userid" => $teacher->id,
            "courseid" => $course1->id,
            "relateduserid" => $student->id,
            "anonymous" => "0",
            "other" => 'N;',
            "timecreated" => 1643120576,
            "origin" => 'web',
            "ip" => '127.0.0.1',
            "realuserid" => null,
        ];

        return $DB->insert_record('logstore_xapi_log', $logevent);
    }
}
