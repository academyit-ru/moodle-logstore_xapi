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

namespace logstore_xapi\task;
defined('MOODLE_INTERNAL') || die();

use tool_log\log\manager;
use logstore_xapi\log\store;
use moodle_database;

class logsync_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasksync', 'logstore_xapi');
    }

    private function reformat_from_standard() {
        /** @var moodle_database $DB */
        global $DB;

        mtrace('    Requestion unti users');
        $userids = $DB->get_fieldset_select('user', 'id', "institution = '" . get_string('institution', 'logstore_xapi'). "'");
        mtrace(sprintf("    userids count: %d", count($userids)));
        if (0 === count($userids)) {
            mtrace(sprintf("    There are no users that can generate xAPI log. Exiting"));
            return false;
        }

        $manager = get_log_manager();
        $xapilogstore = new store($manager);
        $courses = explode(',', get_config('logstore_xapi', 'courses'));
        mtrace(sprintf("    courses count: %d", count($courses)));

        foreach ($courses as $courseid) {
            mtrace(sprintf('    Requestion events for courseid:%d', $courseid));

            $conditionssql = "courseid = :courseid AND (userid %s OR relateduserid %s)";
            list($inuseridssql, $inuseridsparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
            list($relateduseridssql, $relateduseridparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'ru');
            $conditionssql = vsprintf($conditionssql, [$inuseridssql, $relateduseridssql]);
            $sqlparams = array_merge(['courseid' => $courseid], $inuseridsparams, $relateduseridparams);

            $events = $DB->get_records_select('logstore_standard_log', $conditionssql, $sqlparams);

            mtrace(sprintf('    event count for courseid:%d - %d', $courseid, count($events)));
            $synced = 0;
            foreach ($events as $key => $event) {
                if (!$xapilogstore->is_event_ignored($event)) {
                    $msg = sprintf('    Event will be synced eventname:%s eventid:%d courseid:%d userid:%d, relateduserid:%d', $event->eventname, $event->id, $event->courseid, $event->userid, $event->relateduserid);
                    mtrace($msg);
                    $DB->insert_record('logstore_xapi_log', $event);
                }
            }
            mtrace(sprintf('Event synced %d', $synced));
        }
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        $this->reformat_from_standard();
    }
}
