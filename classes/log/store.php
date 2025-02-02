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

namespace logstore_xapi\log;
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../src/autoload.php');

use \tool_log\log\writer as log_writer;
use \tool_log\log\manager as log_manager;
use \tool_log\helper\store as helper_store;
use \tool_log\helper\reader as helper_reader;
use \tool_log\helper\buffered_writer as helper_writer;
use \core\event\base as event_base;
use moodle_database;

/**
 * This class processes events and enables them to be sent to a logstore.
 *
 */
class store implements log_writer {
    use helper_store;
    use helper_reader;
    use helper_writer;

    /**
     * Constructs a new store.
     * @param log_manager $manager
     */
    public function __construct(log_manager $manager) {
        $this->helper_setup($manager);
    }

    /**
     * Should the event be ignored (not logged)? Overrides helper_writer.
     * @param event_base $event
     * @return bool true если нужно пропустить событие
     *
     */
    public function is_event_ignored($event) {
        global $DB;

        $allowguestlogging = $this->get_config('logguests', 1);
        if ((!CLI_SCRIPT || PHPUNIT_TEST) && !$allowguestlogging && isguestuser()) {
            // Always log inside CLI scripts because we do not login there.
            return true;
        }

        $extradebugxapistore = (bool) get_config('logstore_xapi', 'extradebugxapistore');
        if (true === $extradebugxapistore) {
            error_log(sprintf('[%s][DEBUG]: Checking event name %s', __CLASS__, $event->eventname));
        }

        $enabledevents = explode(',', $this->get_config('routes', ''));
        if (false === in_array($event->eventname, $enabledevents)) {
            if (true === $extradebugxapistore) {
                error_log(sprintf('[%s][DEBUG]: Event name %s disabled', __CLASS__, $event->eventname));
            }
            return true;
        }

        if (true === $extradebugxapistore) {
            error_log(sprintf('[%s][DEBUG]: Checking course %d', __CLASS__, $event->courseid));
        }

        $courses = explode(',', get_config('logstore_xapi', 'courses'));
        $coursenotregistered = !in_array($event->courseid, $courses);
        if ($coursenotregistered) {
            return $coursenotregistered;
        }

        // Так как xapi используется только для интеграции с УНТИ то учитываем только их учётки
        if (true === $extradebugxapistore) {
            error_log(sprintf('[%s][DEBUG]: Checking user or relateduser are from UNTI userid: %d relateduserid: %d', __CLASS__, $event->userid, $event->relateduserid));
        }
        $userids = $DB->get_fieldset_select('user', 'id', 'auth = ?', ['untissooauth']);
        $usernotfromunti = !(in_array($event->userid, $userids) || in_array($event->relateduserid, $userids));

        return $usernotfromunti;
    }

    /**
     * Insert events in bulk to the database. Overrides helper_writer.
     * @param \stdClass[] $events raw event data
     *
     * @return void
     */
    protected function insert_event_entries(array $events) {
        /** @var moodle_database $DB */
        global $DB;

        $DB->insert_records('logstore_xapi_log', $events);
    }

    /**
     * @return int
     */
    public function get_max_batch_size() {
        return (int) $this->get_config('maxbatchsize', 100);
    }

    public function process_events(array $events) {
        global $DB;
        global $CFG;
        require(__DIR__ . '/../../version.php');
        $logerror = function ($message = '') {
            error_log('[LOGSTORE_XAPI][ERROR] ' . $message);
        };
        $loginfo = function ($message = '') {
            error_log('[LOGSTORE_XAPI][INFO] ' . $message);
        };
        $loader = 'moodle_curl_lrs';
        $cfgloader = get_config('logstore_xapi', 'loader');
        if (false !== $cfgloader && in_array($cfgloader, ['log', 'lrs', 'moodle_curl_lrs'])) {
            $loader = $cfgloader;
        }
        $handlerconfig = [
            'log_error' => $logerror,
            'log_info' => $loginfo,
            'transformer' => [
                'source_url' => 'http://moodle.org',
                'source_name' => 'Moodle',
                'source_version' => $CFG->release,
                'source_lang' => 'en',
                'send_mbox' => $this->get_config('mbox', false),
                'send_response_choices' => $this->get_config('sendresponsechoices', false),
                'send_short_course_id' => $this->get_config('shortcourseid', false),
                'send_course_and_module_idnumber' => $this->get_config('sendidnumber', false),
                'send_username' => $this->get_config('send_username', false),
                'plugin_url' => 'https://github.com/xAPI-vle/moodle-logstore_xapi',
                'plugin_version' => $plugin->release,
                'repo' => new \src\transformer\repos\MoodleRepository($DB),
                'app_url' => $CFG->wwwroot,
            ],
            'loader' => [
                'loader' => $loader,
                'lrs_endpoint' => $this->get_config('endpoint', ''),
                'lrs_token' => $this->get_config('token', ''),
                'lrs_max_batch_size' => $this->get_max_batch_size(),
            ],
        ];
        $loadedevents = \src\handler($handlerconfig, $events);
        return $loadedevents;
    }

    /**
     * TODO: Убрать этот метод, так как судя по всему он не используется
     */
    public function get_userinfo(array $events) {
        global $DB;
        global $CFG;
        require(__DIR__ . '/../../version.php');
        $logerror = function ($message = '') {
            debugging($message, DEBUG_NORMAL);
        };
        $loginfo = function ($message = '') {
            debugging($message, DEBUG_DEVELOPER);
        };
        $handlerconfig = [
            'log_error' => $logerror,
            'log_info' => $loginfo,
            'transformer' => [
                'source_url' => 'http://moodle.org',
                'source_name' => 'Moodle',
                'source_version' => $CFG->release,
                'source_lang' => 'en',
                'send_mbox' => $this->get_config('mbox', false),
                'send_response_choices' => $this->get_config('sendresponsechoices', false),
                'send_short_course_id' => $this->get_config('shortcourseid', false),
                'send_course_and_module_idnumber' => $this->get_config('sendidnumber', false),
                'send_username' => $this->get_config('send_username', false),
                'plugin_url' => 'https://github.com/xAPI-vle/moodle-logstore_xapi',
                'plugin_version' => $plugin->release,
                'repo' => new \src\transformer\repos\MoodleRepository($DB),
                'app_url' => $CFG->wwwroot,
            ],
            'loader' => [
                'loader' => 'moodle_curl_lrs',
                'lrs_endpoint' => $this->get_config('endpoint', ''),
                'lrs_token' => $this->get_config('token', ''),
                'lrs_max_batch_size' => $this->get_max_batch_size(),
            ],
        ];
        $loadedevents = \src\handler($handlerconfig, $events);
        return $loadedevents;
    }

    /**
     * Determines if a connection exists to the store.
     * @return boolean
     */
    public function is_logging() {
        return true;
    }
}
