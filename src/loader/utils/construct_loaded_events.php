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
 *      'event' => \stdClass,
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
            $uuid = _extract_uuid($response['result'], $offset);
        } else {
            $error = _extract_error($response['error'], $offset);
        }
        return [
            'event' => $transformedevent['event'],
            'statements' => $transformedevent['statements'],
            'transformed' => $transformedevent['transformed'],
            'loaded' => $loaded,
            'uuid' => $uuid,
            'error' => $error,
        ];
    }, $transformedevents, array_keys($transformedevents));
    return $loadedevents;
}

function _extract_uuid($response, $statementoffset) {
    if (false === is_array($response)) {
        $response = json_decode($response);
    }
    $uuid = $response[$statementoffset] ?? null;

    return $uuid;
}

function _extract_error($response, $statementoffset) {
    if (false === is_array($response)) {
        $response = json_decode($response);
    }
    $errormsg = null;
    $errorid = $response['errorId'] ?? null;
    $warning = $response['warnings'][$statementoffset] ?? 'No warnings for this xAPI statement';
    if ($errorid) {
        $errormsg = json_encode(compact('errorid', 'warning'));
    }

    return $errormsg;
}