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

use core\persistent;
use moodle_database;

class queue_item extends persistent {

    const TABLE = 'logstore_xapi_queue';

    /**
     * @inheritdoc
     */
    protected static function define_properties() {
        return [
            'logrecordid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
            ],
            'itemkey' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_ALPHANUMEXT,
            ],
            'queue' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_ALPHANUMEXT,
            ],
            'timecreated' => [
                'default' => 0,
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
            ],
            'timechanged' => [
                'default' => 0,
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
            ],
            'timestarted' => [
                'default' => 0,
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
            ],
            'timecompleted' => [
                'default' => 0,
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
            ],
            'priority' => [
                'default' => 0,
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
            ],
            'attempts' => [
                'default' => 0,
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
            ],
            'isrunning' => [
                'default' => false,
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_BOOL,
            ],
            'isbanned' => [
                'default' => false,
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_BOOL,
            ],
            'payload' => [
                'default' => '',
                'null' => NULL_ALLOWED,
                'type' => PARAM_RAW,
            ],
        ];
    }

    /**
     * Пометит пачку записей что они в обработке
     *
     * @param self[]
     *
     * @return self[]
     */
    public static function mark_as_running(array $records) {
        /** @var moodle_database $DB */
        global $DB;
        global $USER;

        if ([] === $records) {
            return [];
        }
        array_walk(
            $records,
            fn(queue_item $r) => $r->set('isrunning', true)
                                   ->set('timestarted', time())
                                   ->set('timemodified', time())
                                   ->set('usermodified', $USER->id)
        );

        $DB->update_record(
            static::TABLE,
            array_map(fn($r) => $r->to_record(), $records),
            true // $bulk
        );
    }

    /**
     * Пометит пачку записей как обработанные
     *
     * @param self[]
     *
     * @return self[]
     */
    public static function mark_as_complete(array $records) {
        /** @var moodle_database $DB */
        global $DB;
        global $USER;

        if ([] === $records) {
            return [];
        }
        array_walk(
            $records,
            fn(queue_item $r) => $r->set('isrunning', false)
                                   ->set('timecompleted', time())
                                   ->set('timemodified', time())
                                   ->set('usermodified', $USER->id)
        );

        $DB->update_record(
            static::TABLE,
            array_map(fn($r) => $r->to_record(), $records),
            true // $bulk
        );
    }
}
