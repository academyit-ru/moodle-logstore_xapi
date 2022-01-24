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

namespace logstore_xapi\local\persistent;

defined('MOODLE_INTERNAL') || die();

use coding_exception;
use core\persistent;
use DateTimeImmutable;
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
            'lasterror' => [
                'default' => '',
                'null' => NULL_ALLOWED,
                'type' => PARAM_RAW,
            ],
        ];
    }

    /**
     * @return self
     */
    public function mark_as_running() {
        global $USER;

        $this->raw_set('isrunning', true);
        $this->raw_set('timestarted', time());
        $this->raw_set('timemodified', time());
        $this->raw_set('usermodified', $USER->id);

        return $this;
    }

    /**
     * @return self
     */
    public function mark_as_complete() {
        global $USER;

        $this->raw_set('isrunning', false);
        $this->raw_set('timecompleted', time());
        $this->raw_set('timemodified', time());
        $this->raw_set('usermodified', $USER->id);

        if (!$this->is_valid()) {
            throw new coding_exception(
                sprintf('%s: Заданы не валидные значения после mark_complete()', static::class),
                json_encode($this->get_errors())
            );
        }

        return $this;
    }

    /**
     * @param int $increment
     *
     * @return self
     */
    public function increase_attempt_number($increment = 1) {
        $current = (int) $this->raw_get('attempts');
        $this->raw_set('attempts', $current + $increment);

        return $this;
    }

    /**
     *
     * @return int
     */
    protected function get_attempts() {
        return (int) $this->raw_get('attempts');
    }

    /**
     *
     * @return int
     */
    protected function get_logrecordid() {
        return (int) $this->raw_get('logrecordid');
    }

    /**
     *
     * @return self
     */
    public function mark_as_banned() {
        global $USER;

        if (true === (bool) $this->raw_get('isbanned')) {
            return $this;
        }

        $this->raw_set('isbanned', true);
        $this->raw_set('timemodified', time());
        $this->raw_set('usermodified', $USER->id);

        return $this;
    }

    /**
     *
     * @return bool
     */
    public function is_banned() {
        return (bool) $this->raw_get('isbanned');
    }
}
