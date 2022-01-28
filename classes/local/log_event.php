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
 *
 * Класс для события журнала logstore_xapi_log
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi\local;

use ArrayAccess;
use coding_exception;
use moodle_database;
use moodle_exception;
use Throwable;

/**
 * @property-read int    $id
 * @property-read string $eventname
 * @property-read string $component
 * @property-read string $action
 * @property-read string $target
 * @property-read string $objecttable
 * @property-read int    $objectid
 * @property-read string $crud
 * @property-read string $edulevel
 * @property-read int    $contextid
 * @property-read string $contextlevel
 * @property-read string $contextinstanceid
 * @property-read int    $userid
 * @property-read int    $courseid
 * @property-read int    $relateduserid
 * @property-read string $anonymous
 * @property-read string $other
 * @property-read int    $timecreated
 * @property-read string $origin
 * @property-read string $ip
 * @property-read int    $realuserid
 */
class log_event implements ArrayAccess {

    const TABLE = 'logstore_xapi_log';

    /**
     * @var \stdClass
     */
    protected $record;

    /**
     * @param \stdClass $record запись из таблицы logstore_xapi_log
     *
     */
    public function __construct($record) {
        $this->record = $record;
    }

    /**
     *
     * @return bool
     */
    public function has_attachments() {
        /** @var file_finder_mod_assign */
        try {
            $filefinder = file_finder::factory($this);
            return $filefinder->has_attachments($this);
        } catch (moodle_exception $e) {
            debugging(sprintf('%s:%s Error: %s debug: %s', __CLASS__, __METHOD__, $e->getMessage(), $e->debuginfo), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        if (false === isset($this->record->$name)) {
            debugging(
                sprintf(
                    '%s: неизвестное свойство записи %s, debug: %s, trace: %s',
                    static::class,
                    $name,
                    json_encode(['name' => $name, 'record' => $this->record]),
                    (new \Exception())->getTraceAsString()
                ),
                DEBUG_DEVELOPER
            );
            throw new coding_exception(
                static::class . ': неизвестное свойство записи ' . $name,
                json_encode(['name' => $name, 'record' => $this->record])
            );
        }

        return $this->record->$name;
    }

    public function offsetExists($offset): bool {
        return isset($this->record->$offset);
    }

    public function offsetGet($offset) {
        return $this->record->$offset;
    }

    public function offsetSet($offset, $value): void {
        $this->record->$offset = $value;
    }

    public function offsetUnset($offset): void {
        $this->record->$offset = null;
    }

    /**
     *
     * @return \stdClass
     */
    public function to_record() {
        return $this->record;
    }

    /**
     * Load a single record.
     *
     * @param array $filters Filters to apply.
     * @return false|self
     */
    public static function get_record($filters = []) {
        /** @var moodle_database $DB */
        global $DB;

        $record = $DB->get_record(static::TABLE, $filters);
        return $record ? new static($record) : false;
    }

    /**
     * Load a list of records.
     *
     * @param array $filters Filters to apply.
     * @param string $sort Field to sort by.
     * @param string $order Sort order.
     * @param int $skip Limitstart.
     * @param int $limit Number of rows to return.
     *
     * @return \logstore_xapi\local\log_event[]
     */
    public static function get_records($filters = array(), $sort = '', $order = 'ASC', $skip = 0, $limit = 0) {
        global $DB;

        $orderby = '';
        if (!empty($sort)) {
            $orderby = $sort . ' ' . $order;
        }

        $records = $DB->get_records(static::TABLE, $filters, $orderby, '*', $skip, $limit);
        $instances = array();

        foreach ($records as $record) {
            $newrecord = new static(0, $record);
            array_push($instances, $newrecord);
        }

        return $instances;
    }

    /**
     * Count a list of records.
     *
     * @param array $conditions An array of conditions.
     * @return int
     */
    public static function count_records(array $conditions = array()) {
        global $DB;

        $count = $DB->count_records(static::TABLE, $conditions);
        return $count;
    }
}
