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
 * Содержит тесткейс для класса logstore_xapi\local\queue_service.
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi;

use advanced_testcase;
use logstore_xapi\local\persistent\queue_item;
use logstore_xapi\local\persistent\queue_stat;
use logstore_xapi\local\queue_monitor\measurements\base as base_measurement;
use logstore_xapi\task\read_stats_of_the_queue;
use moodle_database;

class read_stats_of_the_queue_task_testcase extends advanced_testcase {

    public function test_execute() {
        /** @var moodle_database $DB */
        global $DB;

        $this->resetAfterTest();

        $datasetbasepath = dirname(__FILE__) . '/fixtures/dataset5';
        $tablename = queue_item::TABLE;
        $dataset = $this->createCsvDataSet([$tablename => "{$datasetbasepath}/{$tablename}.csv"]);
        $this->loadDataSet($dataset);


        $this->assertTrue(0 === queue_stat::count_records());

        $task = new read_stats_of_the_queue();

        $task->execute();

        $this->assertTrue(0 < queue_stat::count_records());
    }

    public function test_it_saves_results() {
        /** @var moodle_database $DB */
        global $DB;

        $this->resetAfterTest();

        $measurement = new class extends base_measurement {
            public function __construct() {
                $this->name = 'test_measurement';
            }

            public function run() {
                $this->result = 1000.1234;
            }
        };
        $measurement->run();
        $task = new read_stats_of_the_queue();

        $this->assertEquals(0, queue_stat::count_records());
        $task->save_results([$measurement]);
        $this->assertEquals(1, queue_stat::count_records());
        $val = queue_stat::get_record(['name' => 'test_measurement'])->get('val');
        $this->assertEquals(1000.1234, $val);
    }
}
