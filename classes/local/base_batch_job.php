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
 * Базовый Job класс
 *
 * @package   logstore_xapi
 * @author    Victor Kilikaev vkilikaev@it.ru
 * @copyright academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi\local;

use logstore_xapi\local\persistent\queue_item;
use moodle_database;

abstract class base_batch_job {

    /**
     * @var moodle_database
     */
    protected $db;

    /**
     * @var queue_item[]
     */
    protected $queueitems;

    /**
     * @var log_event[]
     */
    protected $events;

    /**
     *
     * @return queue_item[]
     */
    abstract public function result_success();

    /**
     *
     * @return queue_item[]
     */
    abstract public function result_error();

    /**
     * @return void
     */
    abstract public function run();

    /**
     * Вернёт события журнала которые были переданы на обработку
     * @return log_event[]
     */
    protected function get_events() {
        if ([] === $this->queueitems) {
            return [];
        }

        if ([] === $this->events) {
            $ids = array_map(
                fn (queue_item $qi) => $qi->get('logrecordid'),
                $this->queueitems
            );
            list($insql, $params) = $this->db->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'id');

            $records = $this->db->get_records_select('logstore_xapi_log', 'id ' . $insql, $params);
            $this->events = array_map(fn($record) => new log_event($record), $records);
        }

        return $this->events;
    }


}
