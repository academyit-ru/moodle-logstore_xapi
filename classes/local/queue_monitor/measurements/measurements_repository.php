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
 * Класс предоставляющий возможность выполнять запросы на получение данных
 * о собранных измерениях
 *
 *
 * @package   logstore_xapi
 * @author    Victor Kilikaev vkilikaev@it.ru
 * @copyright academyit.ru
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\local\queue_monitor\measurements;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use moodle_database;

class measurements_repository {

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
     * TODO: write docbloc
     *
     *
     */
    public function get_total_qitems_series() {
        // FIXME:Remove test data
        $now = new DateTimeImmutable('now', new DateTimeZone('+05:00'));
        $yesterday = $now->sub(new DateInterval('P1D'));
        $datapoints = [1500, 1300, 1100, 900, 700, 500, 300, 100, 469, 269, 69, 0];
        $result = [];
        $time = $yesterday;
        foreach ($datapoints as $iter => $val) {
            $interval = new DateInterval('P' . 5 * $iter . 'M');
            $time = $time->add($interval);
            $result[$time->format('c')] = $val;
        }

        return $result;
    }
}
