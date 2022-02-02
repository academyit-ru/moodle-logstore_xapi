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
 * @param array $events
 * @param bool $transformed true - отфильтровать события которые удалось трансформировать false - вернуть события которые не удалось трансофрмировать
 */
function filter_transformed_events(array $events, $transformed) {
    $filteredevents = array_filter($events, function ($event) use ($transformed) {
        if (true === $transformed) {
            return $event['transformed'] === true && $event['statements'] !== [];
        } else {
            return $event['transformed'] === false || $event['statements'] === [];
        }
    });
    return $filteredevents;
}
