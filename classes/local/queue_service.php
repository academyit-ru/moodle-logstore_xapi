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
 * Содержит класс для работы с очердью задачь
 *
 * @package   logstore_xapi
 * @author    Victor Kilikaev vkilikaev@it.ru
 * @copyright academyit.ru
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\local;

defined('MOODLE_INTERNAL') || die();

use logstore_xapi\event\queue_item_completed;
use moodle_database;

class queue_service {

    /**
     * @var moodle_database
     */
    protected $db;

    /**
     * @param moodle_database $db
     *
     */
    public function __construct(moodle_database $db) {
        $this->db = $db;
    }

    /**
     * Создаств собственный экземпляр класса
     * @return self
     */
    public static function instance() {
        global $DB;

        $instance = new static($DB);

        return $instance;
    }

    /**
     *
     *
     */
    public function pop($limit = 1, $queue = null) {
        return [];
        $conditions = ['isbanned' => false, 'isrunning' => false];
        if ($queue) {
            $conditions['queue'] = $queue;
        }
        $items = queue_item::get_records(
            $conditions,
            'priority ASC, timecreated ASC, timecompleted ASC',
            '', // $order
            0, // $skip
            $limit
        );
        queue_item::mark_as_running($items);

        return $items;
    }

    /**
     *
     *
     */
    public function push() {
        # code ...
    }

    /**
     * @param array $records
     *
     * @return void
     */
    public function complete(array $queueitems) {
        if ([] === $queueitems) {
            return [];
        }
        array_walk($queueitems, fn(queue_item $r) => $r->mark_as_complete());

        $this->db->update_record(
            queue_item::TABLE,
            array_map(fn($r) => $r->to_record(), $queueitems),
            true // $bulk
        );

        array_walk($queueitems, function (queue_item $qi) {
            $event = queue_item_completed::create_from_record($qi);
            $event->trigger();
        });
    }

    /**
     * @param array $records
     *
     * @return void
     */
    public function requeue(array $records) {
        # code ...
    }
}
