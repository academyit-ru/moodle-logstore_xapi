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

use coding_exception;
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
class log_event {

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
        // TODO: Проверить включено ли у задания отправка ответов в виде файлов и были ли загружены файлы студентом
        $eventnames = [
            '\mod_assign\event\submission_graded'
        ];
        $extraevents = get_config('logstore_xapi', 'events_with_attachments');
        if (!$extraevents) {
            $extraevents = [];
        }

        $eventnames = array_merge($eventnames, $extraevents);

        return (
            true === in_array($this->record->eventname, $eventnames)
        );
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        $result = $this->record->$name ?? null;
        if (!$result) {
            throw new coding_exception(static::class . ': неизвестное свойство записи', $name);
        }

        return $result;
    }
}