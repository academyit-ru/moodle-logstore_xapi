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

use core_date;
use DateTimeImmutable;
use logstore_xapi\local\log_event;
use logstore_xapi\local\persistent\xapi_attachment;
use logstore_xapi\local\queue_service;
use moodle_database;
use moodle_exception;
use Throwable;

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
        mtrace('Getting log events to handle...');
        try {
            $events = $this->find_unhandled_events();
            mtrace(sprintf('-- Found %d events', count($events)));
            if ([] === $events) {
                mtrace(sprintf('-- No events found. Stopping execution.'));
                return;
            }

            mtrace('Sorting events into queues...');
            $eventsqueues = $this->map_queues($events);

            foreach ($eventsqueues as $tuple) {
                list($events, $queuename) = $tuple;
                mtrace(sprintf('-- Queue: %s; Events count: %d', $queuename, count($events)));
                foreach ($events as $logevent) {
                    $queueservice->push($logevent, $queuename);
                }
            }
        } catch (moodle_exception $e) {
            mtrace('Exception was thrown. More info in logs');
            $errmsg = sprintf('[LOGSTORE_XAPI][ERROR] %s %s debug: %s trace: %s', static::class, $e->getMessage(), $e->debuginfo, $e->getTraceAsString());
            error_log($errmsg);
            debugging($errmsg, DEBUG_DEVELOPER);
        } catch (Throwable $e) {
            mtrace('Exception was thrown. More info in logs');
            $errmsg = sprintf('[LOGSTORE_XAPI][ERROR] %s trace: %s', static::class, $e->getMessage(), $e->getTraceAsString());
            error_log($errmsg);
            debugging($errmsg, DEBUG_DEVELOPER);
        }


    }

    /**
     * Вернёт записи которые не были обработаны
     *
     * @return log_event[]
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
            %s
SQL;
        $params = [];
        $extrasql = '';

        $limitnum = get_config('logstore_xapi', 'enqueue_jobs_batchlimit');
        if (! $limitnum) {
            $limitnum = static::ENQUEUE_JOB_BATCHLIMIT;
        }
        $cutoffdate = get_config('logstore_xapi', 'enqueue_jobs_task_cutoffdate');
        try {
            if ($cutoffdate) {
                $cutoffdate = new DateTimeImmutable($cutoffdate, core_date::get_server_timezone_object());
            }
        } catch (Throwable $e) {
            $cutoffdate = false;
        }
        if ($cutoffdate) {
            $extrasql = "AND log.timecreated > :cutoffdate";
            $params = ['cutoffdate' => $cutoffdate->format('U')];
            mtrace(sprintf('---- Excluding events older than %s', $cutoffdate->format('c')));
        }
        $sql = sprintf($sql, $extrasql);
        $records = $DB->get_records_sql($sql, $params, 0, $limitnum);
        if (debugging() && !PHPUNIT_TEST) {
            debugging(sprintf('[LOGSTORE_XAPI][DEBUG]: Naideno zapisey: %d ', count($records)), DEBUG_DEVELOPER);
        }

        return array_map(function($r) {return new log_event($r);}, $records);
    }

    /**
     * @param log_event[] $events
     *
     * @return mixed[log_event[], string] 2ой элемент это название очереди
     */
    protected function map_queues(array $events) {
        $tuples = [];
        $map = [
            queue_service::QUEUE_EMIT_STATEMENTS => [],
            queue_service::QUEUE_PUBLISH_ATTACHMENTS => [],
        ];
        foreach ($events as $event) {
            if ($event->has_attachments() && xapi_attachment::is_registered_for($event)) {
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
}
