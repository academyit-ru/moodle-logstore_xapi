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
 * Содержит тесткейс для класса logstore_xapi\task\prune_queue_stats.
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi;

use advanced_testcase;
use core_date;
use DateInterval;
use DateTimeImmutable;
use logstore_xapi\local\persistent\queue_stat;
use logstore_xapi\task\prune_queue_stats;

class prune_queue_stats_testcase extends advanced_testcase {

    public function test_execute() {
        /** @var moodle_database $DB */
        global $DB;

        $this->resetAfterTest();

        $now = new DateTimeImmutable('now', core_date::get_server_timezone_object());
        $intervalstr = 'P1D';
        $interval = new DateInterval($intervalstr);
        $records = [
            (object) ['name' => 'foo', 'val' => 1, 'timemeasured' => $now->format('U')],
            (object) ['name' => 'foo', 'val' => 1, 'timemeasured' => $now->sub($interval)->format('U')],
            (object) ['name' => 'foo', 'val' => 1, 'timemeasured' => $now->sub($interval)->sub($interval)->format('U')],
        ];

        array_walk($records, function($rec) { (new queue_stat(0, $rec))->save(); });

        $this->assertCount(3, queue_stat::get_records());

        set_config('prune_queue_stats_cutoffperiod', $intervalstr, 'logstore_xapi');
        $task = new prune_queue_stats();
        $task->execute();

        $this->assertCount(1, queue_stat::get_records());
    }
}