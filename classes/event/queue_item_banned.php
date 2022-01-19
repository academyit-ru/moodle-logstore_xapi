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
 * Задача была возвращена в очередь после провальной обработки.
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi\event;

use logstore_xapi\local\queue_item;

/**
 * Event класс события queue_item_completed.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int    logrecordid id записи в logstore_xapi_log
 *      - str    queue название очереди
 *      - int    timecompleted метка времени завершения задачи
 *      - str    error ошибка которая из-за которой задача возвращена в очередь
 * }
 */
class queue_item_banned extends \core\event\base {

    protected $queueitembanreason = null;

    /**
     * @param queue_item $qitem
     */
    public static function create_from_record(queue_item $qitem) {
        $event = self::create([
            'context' => \context_system::instance(),
            'objectid' => $qitem->id,
            'objecttable' => queue_item::TABLE,
            'other' => [
                'logrecordid' => $qitem->get('logrecordid'),
                'queue' => $qitem->get('queue'),
                'timecompleted' => $qitem->get('timecompleted'),
                'error' => $qitem->get('lasterror'),
            ]
        ]);

        $event->add_record_snapshot(queue_item::TABLE, $qitem);

        return $event;
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('queue_item_requeued_event', 'logstore_xapi');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return sprintf(
            'Элемент очереди %s для xAPI выражения с id #%d был заблокирован Причина: %s. Error: %s',
            $this->other['queue'],
            $this->other['objectid'],
            $this->get_ban_reason(),
            $this->other['error']
        );
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url(
            '/admin/tool/log/store/xapi/queues/view.php',
            [
                'id' => $this->other['objectid'],
                'queue' => $this->other['queue'],
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
        if (false === isset($this->other['logrecordid'])) {
            throw new \coding_exception('Не указано значение для logrecordid');
        }
        if (false === isset($this->other['queue'])) {
            throw new \coding_exception('Не указано значение для queue');
        }
        if (false === isset($this->other['timecompleted'])) {
            throw new \coding_exception('Не указано значение для timecompleted');
        }
        if (false === isset($this->other['error'])) {
            throw new \coding_exception('Не указано значение для error');
        }
    }

    public static function get_other_mapping() {
        $othermapped = [];
        $othermapped['objectid'] = ['db' => 'logstore_xapi_queue', 'restore' => 'logstore_xapi_queue'];
        $othermapped['logrecordid'] = ['db' => 'logstore_xapi_log', 'restore' => 'logstore_xapi_log'];

        return $othermapped;
    }

    /**
     * @param string $reason
     *
     * @return self
     */
    public function set_ban_reason($reason) {
        $this->queueitembanreason = $reason;
        return $this;
    }
    /**
     *
     * @return string
     */
    protected function get_ban_reason() {
        $queueitembanreason = $this->queueitembanreason;
        if (null === $queueitembanreason) {
            $queueitembanreason = get_string('ban_reason_attemptslimit_reached', 'logstore_xapi');
        }

        return $queueitembanreason;
    }
}
