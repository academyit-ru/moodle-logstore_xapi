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
 * Содержит Persistent класс для таблицы logstore_xapi_q_stats
 *
 * @package   logstore_xapi
 * @author    Victor Kilikaev vkilikaev@it.ru
 * @copyright academyit.ru
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\local\persistent;

use core\persistent;

class queue_stat extends persistent {

    const TABLE = 'logstore_xapi_q_stats';

    /**
     * @inheritdoc
     */
    protected static function define_properties() {
        return [
            'name' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_TEXT,
            ],
            'val' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_FLOAT
            ],
            'meta' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_TEXT,
            ],
        ];
    }
}
