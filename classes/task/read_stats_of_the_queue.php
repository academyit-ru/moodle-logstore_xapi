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
 * Задание по расписанию для сбора данных о процессе обработки очереди
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi\task;

use combined_progress_trace;
use core\task\scheduled_task;
use error_log_progress_trace;
use logstore_xapi\local\persistent\queue_stat;
use logstore_xapi\local\queue_monitor\measurements\base as measurement;
use logstore_xapi\local\queue_monitor\measurements\total_attachments;
use logstore_xapi\local\queue_monitor\measurements\total_lrs_records;
use logstore_xapi\local\queue_monitor\measurements\total_qitems;
use logstore_xapi\local\queue_service;
use moodle_exception;
use text_progress_trace;
use progress_trace;
use Throwable;

class read_stats_of_the_queue extends scheduled_task {

    /**
     * @var progress_trace
     */
    protected $progresstrace;

    /**
     * @inheritdoc
     */
    public function get_name() {
        return get_string('read_stats_of_the_queue_task_name', 'logstore_xapi');
    }

    /**
     * @inheritdoc
     */
    public function execute() {
        $measurements = $this->build_measurements_stack();
        $trace = $this->get_progress_trace();

        if ([] === $measurements) {
            $trace->output('Ne nastroeni pokazateli dlya sbora statistiki');
            return;
        }
        $trace->output('Nachinau sbor dannih');
        /** @var measurement $measurement */
        foreach($measurements as $measurement) {
            $trace->output('Running measurement: ' . $measurement->get_name(), 2);
            $measurement->run();
            if ($measurement->has_error()) {
                $error = $measurement->get_error();
                $logcontext = json_encode(
                    [
                        'error' => (string) $error,
                        'measurement' => [
                            'name' => $measurement->get_name()
                        ]
                    ], JSON_UNESCAPED_UNICODE);
                $trace->output(sprintf('[ERROR]: Sbor dannih pokazatelya vizval oshibku. context: %s', $logcontext));
            }
        }

        $trace->output('Sohranyau dannie');
        $this->save_results($measurements);

        $trace->output('Procedura zaverchena');
    }

    /**
     *
     * @return array
     */
    public function build_measurements_stack(): array {
        /** @var \moodle_database $DB */
        global $DB;

        $stack = [];

        $msrmnt = new total_qitems();
        $msrmnt->set_name('total:queueitems');
        $stack[] = $msrmnt;

        $msrmnt = (new total_qitems())->set_filter('queue', queue_service::QUEUE_EMIT_STATEMENTS);
        $msrmnt->set_name('total:queueitems_by_queue:' . queue_service::QUEUE_EMIT_STATEMENTS);
        $stack[] = $msrmnt;

        $msrmnt = (new total_qitems())->set_filter('queue', queue_service::QUEUE_PUBLISH_ATTACHMENTS);
        $msrmnt->set_name('total:queueitems_by_queue:' . queue_service::QUEUE_PUBLISH_ATTACHMENTS);
        $stack[] = $msrmnt;

        $msrmnt = (new total_qitems())->set_filter('isrunning', true);
        $msrmnt->set_name('total:queueitems_by_status:isrunning');
        $stack[] = $msrmnt;

        $msrmnt = (new total_qitems())->set_filter('isbanned', true);
        $msrmnt->set_name('total:queueitems_by_status:isbanned');
        $stack[] = $msrmnt;

        $sql = $this->get_stuck_running_filter_sql();
        $msrmnt = (new total_qitems())->set_filter_sql($sql);
        $msrmnt->set_name('total:queueitems_by_status:stuck_running');
        $stack[] = $msrmnt;

        $sql = <<<SQL
        isbanned = 0
        AND LENGTH(lasterror) > 0
SQL;
        $msrmnt = (new total_qitems())->set_filter_sql($sql);
        $msrmnt->set_name('total:queueitems_by_status:has_errors');
        $stack[] = $msrmnt;


        $msrmnt = new total_attachments();
        $msrmnt->set_name('total:attachments');
        $stack[] = $msrmnt;

        $msrmnt = new total_lrs_records();
        $msrmnt->set_name('total:lrs_records');
        $stack[] = $msrmnt;


        return $stack;
    }

    /**
     * @param measurement[] $measurements
     * @return void
     */
    public function save_results(array $measurements) {
        $now = time();
        $trace = $this->get_progress_trace();
        /** @var measurement $measurement */
        foreach ($measurements as $measurement) {
            try {
                $qstat = new queue_stat();
                $qstat->from_measurement($measurement);
                $qstat->set('timemeasured', $now);
                $qstat->save();
                $logcontext = ['name' => $qstat->get('name'), 'val' => $qstat->get('val')];
                $trace->output(sprintf('[INFO]: Dannie pokazatelya sohraneni. context: %s', json_encode($logcontext, JSON_UNESCAPED_UNICODE)), 2);
            } catch (Throwable $e) {
                $logcontext = [
                    'exception' => $e->getMessage(),
                    'measurement' => [
                        'name' => $measurement->get_name(),
                        'val' => $measurement->get_result(),
                        'error' => $measurement->get_error()
                    ]
                ];
                if ($e instanceof moodle_exception) {
                    $logcontext['debuginfo'] = $e->debuginfo;
                }

                $trace->output(sprintf('[ERROR]: Oshibka pri sohranenii dannih pokazatelya. context: %s', json_encode($logcontext, JSON_UNESCAPED_UNICODE)));
                throw $e; // Так в журнале фоновой задачи будет видна проблема
            }
        }
    }

    /**
     * TODO: write docbloc
     *
     *
     */
    protected function get_stuck_running_filter_sql(): string {
        /** @var \moodle_database $DB */
        global $DB;

        $sql = <<<SQL
        isrunning = 1
        AND timecompleted = 0
        AND (NOW() - TO_TIMESTAMP(timestarted) > INTERVAL '24h')
SQL;
        if ('mysql' === $DB->get_dbfamily()) {
            $sql = <<<SQL
            isrunning = 1
            AND timecompleted = 0
            AND TIMESTAMPDIFF(HOUR, FROM_UNIXTIME(timecreated), CURRENT_TIMESTAMP()) > 24;
SQL;
        }
        return $sql;
    }

    /**
     *
     * @return progress_trace
     */
    public function get_progress_trace(): progress_trace {
        if (null === $this->progresstrace) {
            $this->progresstrace = new combined_progress_trace([
                new error_log_progress_trace('[LOGSTORE_XAPI]'),
                new text_progress_trace()
            ]);
        }
        return $this->progresstrace;
    }

    /**
     * @param progress_trace $trace
     *
     * @return self
     */
    public function set_progress_trace(progress_trace $trace): self {
        $this->progresstrace = $trace;
        return $this;
    }
}
