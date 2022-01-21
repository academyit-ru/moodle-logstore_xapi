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

use assign;
use assign_submission_file;
use context_module;
use logstore_xapi\local\file_finder;

require_once dirname(__DIR__, 7) . '/mod/assign/locallib.php';
require_once dirname(__DIR__, 7) . '/mod/assign/submission/file/locallib.php';

class file_finder_mod_assign extends file_finder {

    /**
     * TODO: write docbloc
     *
     *
     */
    public function __construct() {
        # code ...
    }
    /**
     * @inheritdoc
     */
    public function find_for(log_event $logevent) {
        global $CFG;
        $fs = get_file_storage();
        switch ($logevent->eventname) {
            case '\mod_assign\event\submission_graded':
                $cm = get_coursemodule_from_id('assign', $logevent->contextinstanceid, 0, false, MUST_EXIST);
                $course = get_course($cm->course);
                $assign = new \assign(context_module::instance($logevent->contextinstanceid), $cm, $course);
                $assign->get_submission_plugins();
                $submissionfile = new assign_submission_file($assign, 'assign_submission_file');
                // найти coursemodule
                $submissionid = 0;
                return $fs->get_area_files(
                    $logevent->contextid,
                    'assignsubmission_file',
                    ASSIGNSUBMISSION_FILE_FILEAREA,
                    $submissionid,
                    "id",                           // $sort
                    false                           // $includedirs
                );
                break;
            default:
                return [];
        }
    }
}
