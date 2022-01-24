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
 * После успешной регистрации xAPI выражения в LRS буде создана запись в logstore_xapi_records.
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi\event;

use core_date;
use logstore_xapi\local\persistent\xapi_record;

/**
 * Event класс события xapi_record_regisered.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int    lrs_uuid id записи в logstore_xapi_log
 *      - str    eventid название очереди
 *      - int    timeregistered метка времени завершения задачи
 * }
 */
class xapi_record_registered extends \core\event\base {

    /**
     * @param xapi_record $xapirecord
     */
    public static function create_from_record(xapi_record $xapirecord) {
        $event = self::create([
            'context' => \context_system::instance(),
            'objectid' => $xapirecord->id,
            'objecttable' => xapi_record::TABLE,
            'other' => [
                'lrs_uuid' => $xapirecord->get('logrecordid'),
                'eventid' => $xapirecord->get('eventid'),
                'timeregistered' => $xapirecord->get('timeregistered'),
            ]
        ]);

        $event->add_record_snapshot(xapi_record::TABLE, $xapirecord);

        return $event;
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('xapi_record_registered_event', 'logstore_xapi');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $timeregistered = new \DateTimeImmutable($this->other['timeregistered'], core_date::get_server_timezone_object());
        return sprintf(
            'xAPI выражение #%d для события #%d было зарегистрированов %s в LRS с uuid %s',
            $this->objectid,
            $this->other['eventid'],
            $timeregistered->format('c'),
            $this->other['lrs_uuid']
        );
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url(
            '/admin/tool/log/store/xapi/xapi_records/view.php',
            [
                'id' => $this->other['objectid'],
            ]
        );
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (false === isset($this->other['lrs_uuid'])) {
            throw new \coding_exception('Не указано значение для lrs_uuid');
        }
        if (false === isset($this->other['eventid'])) {
            throw new \coding_exception('Не указано значение для eventid');
        }
        if (false === isset($this->other['timeregistered'])) {
            throw new \coding_exception('Не указано значение для timeregistered');
        }
    }
}
