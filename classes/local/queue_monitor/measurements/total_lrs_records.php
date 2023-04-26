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
 * Класс для сбора статистики
 * общее число xAPI выражений отправленных в LRS
 *
 * @package   logstore_xapi
 * @author    Victor Kilikaev vkilikaev@it.ru
 * @copyright academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi\local\queue_monitor\measurements;

use logstore_xapi\local\persistent\xapi_record;
use logstore_xapi\local\queue_monitor\measurements\base as base_measurement;
use Throwable;

class total_lrs_records extends base_measurement {

    /**
     * @inheritdoc
     */
    public function run() {
        try {
            $this->result = xapi_record::count_records();
        } catch (Throwable $e) {
            $this->error = $e;
        }
    }
}