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
 * Задание по расписанию для размещения в очередь работ по отправке данных в LRS и S3
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi\task;

use logstore_xapi\local\log_event;
use logstore_xapi\local\queue_service;
use moodle_database;

class enqueue_jobs extends \core\task\scheduled_task {

    const ENQUEUE_JOB_BATCHLIMIT = 5000;

    /**
     * @inheritdoc
     */
    public function get_name() {
        return get_string('enqueue_jobs_task_name', 'logstore_xapi');
    }

    /**
     * @inheritdoc
     */
    public function execute() {

        $queueservice = queue_service::instance();
        $events = $this->find_unhandled_events();

        $eventsqueues = $this->map_queues($events);

        foreach ($eventsqueues as $tuple) {
            list($events, $queuename) = $tuple;
            $queueservice->push($events, $queuename);
        }
    }

    /**
     * Вернёт записи которые не были обработаны
     * @param log_event[]
     */
    protected function find_unhandled_events() {
        /** @var moodle_database $DB */
        global $DB;
        $sql = <<<SQL
        SELECT log.*
        FROM {logstore_xapi_log} log
        LEFT JOIN {logstore_xapi_records} registered ON log.id = registered.eventid
        LEFT JOIN {logstore_xapi_queue} q ON log.id = q.logrecordid
        WHERE
            registered.id IS NULL
            AND q.id IS NULL
SQL;
        $limitnum = get_config('logstore_xapi', 'enqueue_jobs_batchlimit');
        if (false === $limitnum) {
            $limitnum = static::ENQUEUE_JOB_BATCHLIMIT;
        }
        $records = $DB->get_records_sql($sql, [], 0, $limitnum);

        return array_map(fn($r) => new log_event($r), $records);
    }

    /**
     * @param log_event[] $events
     *
     * @return <$eventrecords[], $queuename>[]
     */
    protected function map_queues(array $events) {
        $tuples = [];
        $map = [
            queue_service::QUEUE_EMIT_STATEMENTS => [],
            queue_service::QUEUE_PUBLISH_ATTACHMENTS => [],
        ];
        foreach ($events as $event) {
            if ($this->has_attachments($event)) {
                $map[queue_service::QUEUE_PUBLISH_ATTACHMENTS][] = $event;
            } else {
                $map[queue_service::QUEUE_EMIT_STATEMENTS][] = $event;
            }
        }
        foreach ($map as $queuename => $events) {
            $tuples[] = [$events, $queuename];
        }

        return $tuples;
    }

    /**
     * Определит по типу события нужно ли публиковать артефакты
     *
     * @param \stdClass $eventrecord
     *
     * @return bool
     */
    protected function has_attachments($eventrecord) {
        $logevent = new log_event($eventrecord);

        return $logevent->has_attachments();
    }
}
