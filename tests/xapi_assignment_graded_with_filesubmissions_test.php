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
 * Содержит тесткейс генерации xapi выражения с вложением.
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
use logstore_xapi\local\persistent\xapi_attachment;
use logstore_xapi\task\enqueue_jobs;
use moodle_database;
use assign;

require_once dirname(__DIR__) . '/src/autoload.php';

/**
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xapi_assignment_graded_with_filesubmissions_testcase extends advanced_testcase {

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
     *      - В журнале logstore_xapi_log есть запись о событии mod_assign\event\submission_graded
     *      - в таблице xapi_attachment::TABLE есть запись связанная с событием журнала
     * Выполнить:
     *      - Выполнить \src\transformer\handler($transformerconfig, $events);
     * Результат:
     *      - Сгенерированное выражение содержит блок attachments
     */
    public function test_event_with_file_submission_generates_attachment_block() {
        /** @var moodle_database $DB */
        global $DB;
        global $CFG;

        $this->resetAfterTest();

        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $assignparams = [
            'course' => $course->id,
            'assignsubmission_file_enabled' => 1,
            'assignsubmission_file_maxfiles' => 1,
            'assignsubmission_file_maxsizebytes' => 10,
            'assignfeedback_comments_enabled' => 1,
        ];
        $instance = $this->getDataGenerator()->create_module('assign', $assignparams);
        $this->getDataGenerator()->enrol_user($student->id, $course->id);
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $this->teacherroleid);
        $cm = get_coursemodule_from_instance('assign', $instance->id);
        $context = context_module::instance($cm->id);
        $assign = new assign($context, $cm, $course);

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

        $plugin = $assign->get_submission_plugin_by_type('file');
        $plugin->save($submission, new \stdClass());

        $grade = $assign->get_user_grade($student->id, true);
        $grade->grader = $teacher->id;
        $grade->grade = 100;
        $assign->update_grade($grade);

        // Create formdata.
        $data = (object) [
            'assignfeedbackcomments_editor' => [
                'text' => '<p>first comment for this test</p>',
                'format' => 1
            ]
        ];

        $feedbackplugin = $assign->get_feedback_plugin_by_type('comments');
        $feedbackplugin->save($grade, $data);

        $this->create_log_events($student, $teacher, $assign, $course, $grade);
        $logevent = log_event::get_record();
        $this->create_xapi_attachment_record($logevent);

        $transformerconfig = [
            'log_error' => function($e) {$this->assertEmpty($e, 'Transformer exception: ' . $e);},
            'log_info' => function() {},
            'source_url' => 'http://moodle.org',
            'source_name' => 'Moodle',
            'source_version' => '1',
            'source_lang' => 'en',
            'send_mbox' => false,
            'send_response_choices' => false,
            'send_short_course_id' => false,
            'send_course_and_module_idnumber' => false,
            'send_username' => false,
            'plugin_url' => 'https://github.com/xAPI-vle/moodle-logstore_xapi',
            'plugin_version' => 1,
            'repo' => new \src\transformer\repos\MoodleRepository($DB),
            'app_url' => $CFG->wwwroot,
        ];
        $transformed = \src\transformer\handler($transformerconfig, [$logevent]);

        $transformed = reset($transformed);
        $this->assertTrue($transformed['transformed']);
        $statement = reset($transformed['statements']);

        $this->assertArrayHasKey('attachments', $statement);
        $attachments = $statement['attachments'];
        $attachment = reset($attachments);
        $this->assertArraySubset([
            'fileUrl' => 'https://s3.example.com/foo_bar_baz.zip',
            'display' => [
                'ru-RU' => 'foo_bar_baz.zip'
            ],
            'contentType' => 'application/zip',
            'length' => '15',
            'sha2' => 'dee193c2b0872a100ed85f3b1a3234af512bc3dfda0f83a3ddfeb35cf0fdcffc',
            'usageType' => 'http://id.tincanapi.com/attachment/supporting_media'
        ], $attachment);
    }

    /**
     *
     *
     */
    protected function create_log_events($student, $teacher, assign $assign, $course, $grade) {
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

    /**
     * @param log_event $logevent
     *
     * @return xapi_attachment
     */
    protected function create_xapi_attachment_record(log_event $logevent) {
        $xapiattachment = new xapi_attachment(0, (object) [
            'eventid' => $logevent->id,
            's3_url' => 'https://s3.example.com/foo_bar_baz.zip',
            's3_filename' => 'foo_bar_baz.zip',
            's3_sha2' => 'dee193c2b0872a100ed85f3b1a3234af512bc3dfda0f83a3ddfeb35cf0fdcffc',
            's3_filesize' => 15,
            's3_contenttype' => 'application/zip',
            's3_etag' => 'dbaf3c5c99376bc41aa0526be392dc18'
        ]);
        $xapiattachment->save();

        return $xapiattachment;
    }
}
