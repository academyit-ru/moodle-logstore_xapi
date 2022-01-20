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
 * Persistent класс для таблицы xapi_records
 *
 * @package   logstore_xapi
 * @author    Victor Kilikaev vkilikaev@it.ru
 * @copyright academyit.ru
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\local\persistent;

defined('MOODLE_INTERNAL') || die();

use core\invalid_persistent_exception;
use core\persistent;
use logstore_xapi\event\xapi_record_regisrered;
use moodle_database;

class xapi_record extends persistent {

    const TABLE = 'logstore_xapi_records';

    /**
     * @inheritdoc
     */
    protected static function define_properties() {
        return [
            'lrs_uuid' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_ALPHANUMEXT,
            ],
            'eventid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
            ],
            'body' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_RAW,
            ],
            'timeregistered' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_INT,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function after_create() {
        $event = xapi_record_regisrered::create_from_record($this);
        $event->trigger();
    }
}
