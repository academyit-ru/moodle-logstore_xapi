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

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        // Для начала запускать будем в ручную если понадобится
        'classname' => '\logstore_xapi\task\enqueue_jobs',
        'blocking' => 1,
        'minute' => '0',
        'hour' => '1',
        'day' => '1',
        'dayofweek' => '1',
        'month' => '1'
        // TODO: После 2022-01-28 вернуть прежний вариант
        // 'classname' => '\logstore_xapi\task\enqueue_jobs',
        // 'blocking' => 1,
        // 'minute' => '*/1',
        // 'hour' => '*',
        // 'day' => '*',
        // 'dayofweek' => '*',
        // 'month' => '*',
    ],
    [
        'classname' => '\logstore_xapi\task\emit_statement',
        'blocking' => 0,
        'minute' => '*/5',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ],
    [
        'classname' => '\logstore_xapi\task\publish_attachment',
        'blocking' => 0,
        'minute' => '*/5',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ],
    [
        'classname' => '\logstore_xapi\task\read_stats_of_the_queue',
        'blocking' => 1,
        'minute' => '*/5',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ],
    [
        'classname' => '\logstore_xapi\task\prune_queue_stats',
        'blocking' => 1,
        'minute' => '0',
        'hour' => '1',
        'day' => '1',
        'dayofweek' => '*',
        'month' => '*'
    ],
];