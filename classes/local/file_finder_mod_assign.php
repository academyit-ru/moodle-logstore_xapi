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
 * Ищейка файлов для событий \mod_assign\event\*
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi\local;

use file_storage;
use logstore_xapi\local\file_finder;
use moodle_database;

require_once dirname(__DIR__, 7) . '/mod/assign/locallib.php';
require_once dirname(__DIR__, 7) . '/mod/assign/submission/file/locallib.php';

class file_finder_mod_assign extends file_finder {

    /**
     * @var moodle_database
     */
    protected $db;

    /**
     * @var file_storage
     */
    protected $fs;

    /**
     * @param moodle_database $db
     * @param file_storage    $fs
     */
    public function __construct(moodle_database $db, file_storage $fs) {
        $this->db = $db;
        $this->fs = $fs;
    }
    /**
     * @inheritdoc
     */
    public function find_for(log_event $logevent) {
        $default = [];

        switch ($logevent->eventname) {
            case '\mod_assign\event\submission_graded':
                $cmid = (int) $logevent->contextinstanceid;
                if (false === $this->is_assign_submisssion_file_enabled($cmid)) {
                    debugging("Для задания cmid:{$cmid} не включены ответы в виде файлов", DEBUG_DEVELOPER);
                    break;
                }

                $assignid = $this->db->get_field('course_modules', 'instance', ['id' => $cmid]);
                $conditions = [
                    'assignment' => $assignid,
                    'userid' => $logevent->relateduserid,
                    'groupid' => 0
                ];
                $submission = null;
                $records = $this->db->get_records('assign_submission', $conditions, 'attemptnumber DESC', '*', 0, 1);
                if ($records) {
                    $submission = reset($records);
                }
                return $this->fs->get_area_files(
                    $logevent->contextid,
                    'assignsubmission_file',        // $component
                    ASSIGNSUBMISSION_FILE_FILEAREA, // $filearea
                    $submission->id,
                    "id",                           // $sort
                    false                           // $includedirs
                );
            default:
                return [];
        }

        return $default;
    }

    /**
     * @param int $assignid
     * @return bool
     */
    protected function is_assign_submisssion_file_enabled($cmid) {
        $sql = "SELECT true
                FROM {assign_plugin_config} acfg
                WHERE
                    acfg.assignment  = (SELECT cm.instance FROM {course_modules} cm WHERE cm.id = :cmid)
                    AND acfg.name    = 'enabled'
                    AND acfg.value   = '1'
                    AND acfg.subtype = 'assignsubmission'
                    AND acfg.plugin  = 'file'";
        return (bool) $this->db->get_field_sql($sql, ['cmid' => $cmid]);
    }

    /**
     * @param log_event $logevent
     * @return bool
     */
    public function has_attachments(log_event $logevent) {
        return (bool) $this->find_for($logevent);
    }
}
