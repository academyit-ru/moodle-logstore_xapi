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

namespace src\loader\utils;

defined('MOODLE_INTERNAL') || die();

/**
 * @param array $transformedevents
 * @param bool  $loaded
 * @param array ['result' => null|string, 'error' => null|string] по умолчанию []
 *
 * @return array [
 *      'event' => log_event,
 *      'statement' => string,
 *      'transformed' => bool,
 *      'loaded' => bool,
 *      'uuid' => string|null,
 *      'error' => string|null
 * ]
 */
function construct_loaded_events(array $transformedevents, $loaded, $response = []) {
    $loadedevents = array_map(function ($transformedevent, $offset) use ($loaded, $response) {
        $uuid = null;
        $error = null;
        if (true === $loaded) {
            $uuid = _extract_uuid($response, $offset);
        } else {
            $error = $response['error'] ?? '';
        }
        return [
            'event' => $transformedevent['event'],
            'statements' => $transformedevent['statements'],
            'transformed' => $transformedevent['transformed'],
            'loaded' => $loaded,
            'uuid' => $uuid,
            'error' => $error,
        ];
    }, array_values($transformedevents), array_keys(array_values($transformedevents)));

    return $loadedevents;
}

function _extract_uuid($response, $statementoffset) {
    $responseResult = $response['result'];
    if (false === is_array($responseResult)) {
        $responseResult = json_decode($responseResult);
    }
    $uuid = $responseResult[$statementoffset] ?? null;

    return $uuid;
}
