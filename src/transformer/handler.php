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

namespace src\transformer;

use src\transformer\repos\exceptions\TypeNotFound;

defined('MOODLE_INTERNAL') || die();

function handler(array $config, array $events) {
    $eventfunctionmap = get_event_function_map();

    $transformedevents = array_map(function ($event) use ($config, $eventfunctionmap) {
        $eventobj = (object) $event;
        $eventstatements = [];
        try {
            $eventname = $eventobj->eventname;
            if (isset($eventfunctionmap[$eventname])) {
                $eventfunctionname = $eventfunctionmap[$eventname];
                $eventfunction = '\src\transformer\events\\' . $eventfunctionname;
                $eventconfig = array_merge([
                    'event_function' => $eventfunction,
                ], $config);
                $eventstatements = $eventfunction($eventconfig, $eventobj);
                if (true === isset($config['add_u2035_extensions']) && (bool) $config['add_u2035_extensions']) {
                    list($eventstatements, $err) = \src\transformer\utils\add_u2035_extensions($eventstatements, $eventconfig, $eventobj);
                    if (null !== $err) {
                        throw $err;
                    }
                }
            }

            // Returns successfully transformed event with its statements.
            $transformedevent = [
                'event' => $eventobj,
                'statements' => $eventstatements,
                'transformed' => true,
            ];
            return $transformedevent;
        } catch (TypeNotFound $e) {
            $logerror = $config['log_error'];
            $e->addDebugInfo(['event id' => $eventobj->id]);
            $errmsg = sprintf("Failed transform for event id: %d Message: %s", $eventobj->id, (string) $e);
            $logerror($errmsg);

            // Returns unsuccessfully transformed event without statements.
            $transformedevent = [
                'event' => $eventobj,
                'statements' => [],
                'transformed' => false,
                'error' => $e
            ];
            return $transformedevent;
        } catch (\Exception $e) {
            $logerror = $config['log_error'];
            $errmsg = sprintf("Failed transform for event id: %d Message: %s Debug: %s", $eventobj->id, $e->getMessage(), json_encode(['trace' => $e->getTraceAsString()]));
            $logerror($errmsg);

            // Returns unsuccessfully transformed event without statements.
            $transformedevent = [
                'event' => $eventobj,
                'statements' => [],
                'transformed' => false,
                'error' => $errmsg
            ];
            return $transformedevent;
        }
    }, $events);
    return $transformedevents;
}
