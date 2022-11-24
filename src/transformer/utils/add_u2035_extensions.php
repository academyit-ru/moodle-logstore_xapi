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

namespace src\transformer\utils;

use coding_exception;
use logstore_xapi\local\log_event;

defined('MOODLE_INTERNAL') || die();

define('U2035_EXTENSION_PREFIX', 'https://api.2035.university');

function add_u2035_extensions(array $eventstatements, array $eventconfig, log_event $eventobj): array {

    $courseid = $eventobj->courseid ?? null;
    if (!$courseid) {
        return [$eventstatements, new coding_exception('add_u2035_extensions: courseid not set in event record')];
    }
    $courseid = (int) $courseid;

    $u2035courseid = $eventconfig['u2035_courseid_map'][$courseid] ?? null;
    if (null === $u2035courseid) {
        return [$eventstatements, new coding_exception('add_u2035_extensions: parent_course_id not set', json_encode(['courseid' => $courseid]))];
    }

    $u2035extensions = [
        U2035_EXTENSION_PREFIX . '/parent_course_id' => $u2035courseid,
        U2035_EXTENSION_PREFIX . '/project_id' => $eventconfig['u2035_project_id'] ?? null,

        // U2035_EXTENSION_PREFIX . '/flow_id' => null,
        // U2035_EXTENSION_PREFIX . '/flow_num' => null,
        // U2035_EXTENSION_PREFIX . '/module_id' => null,
        // U2035_EXTENSION_PREFIX . '/module_num' => null,
        // U2035_EXTENSION_PREFIX . '/project' => null,
        // U2035_EXTENSION_PREFIX . '/actor_id' => null,
        // U2035_EXTENSION_PREFIX . '/previous_event_uuid' => null,
    ];

    $eventstatements = array_map(function($eventstatement) use ($u2035extensions, $eventconfig, $eventobj) {
        if (false === isset($eventstatement['context'])) {
            $logerror = $eventconfig['log_error'];
            $logerror(
                'add_u2035_extensions: У xAPI-выражения отсутствует поле context debuginfo: '
                . json_encode(['statement' => $eventstatement, 'eventobj' => $eventobj])
            );
            return $eventstatement;
        }
        if (false === isset($eventstatement['context']['extensions'])) {
            $eventstatement['context'] = [
                'extensions' => []
            ];
        }
        $eventstatement['context']['extensions'] = array_merge(
            $eventstatement['context']['extensions'],
            $u2035extensions
        );

        return $eventstatement;
    }, $eventstatements);


    return [$eventstatements, null];
}
