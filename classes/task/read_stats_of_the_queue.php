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

use core\task\scheduled_task;
use logstore_xapi\local\persistent\queue_stat;
use logstore_xapi\local\queue_monitor\measurements\base as measurement;
use logstore_xapi\local\queue_monitor\measurements\total_attachments;
use logstore_xapi\local\queue_monitor\measurements\total_lrs_records;
use logstore_xapi\local\queue_monitor\measurements\total_qitems;
use logstore_xapi\local\queue_service;

class read_stats_of_the_queue extends scheduled_task {

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

        if ([] === $measurements) {
            mtrace('Ne nastroeni pokazateli dlya sbora statistiki');
            return;
        }
        mtrace('Nachinau sbor dannih');
        /** @var measurement $measurement */
        foreach($measurements as $measurement) {
            $measurement->run();
        }

        mtrace('Sohranyau dannie');
        $this->save_results($measurements);

        mtrace('Procedura zaverchena');
    }

    /**
     *
     * @return array
     */
    public function build_measurements_stack() {
        $stack = [];

        $msrmnt = new total_qitems();
        $msrmnt->set_name('totalqueueitems');
        $stack[] = $msrmnt;

        $msrmnt = (new total_qitems())->set_filter('queue', queue_service::QUEUE_EMIT_STATEMENTS);
        $msrmnt->set_name('totalqueueitems_by_queue:' . queue_service::QUEUE_EMIT_STATEMENTS);
        $stack[] = $msrmnt;

        $msrmnt = (new total_qitems())->set_filter('queue', queue_service::QUEUE_PUBLISH_ATTACHMENTS);
        $msrmnt->set_name('totalqueueitems_by_queue:' . queue_service::QUEUE_PUBLISH_ATTACHMENTS);
        $stack[] = $msrmnt;

        $msrmnt = (new total_qitems())->set_filter('isrunning', true);
        $msrmnt->set_name('totalqueueitems_by_status:isrunning');
        $stack[] = $msrmnt;

        $msrmnt = (new total_qitems())->set_filter('isbanned', true);
        $msrmnt->set_name('totalqueueitems_by_status:isbanned');
        $stack[] = $msrmnt;

        $sql = <<<SQL
        isrunning = 1
        AND timecompleted = 0
        AND (NOW() - TO_TIMESTAMP(timestarted) > INTERVAL '24h')
SQL;
        $msrmnt = (new total_qitems())->set_filter_sql($sql);
        $msrmnt->set_name('totalqueueitems_by_status:stuck_running');
        $stack[] = $msrmnt;

        $sql = <<<SQL
        isbanned = 0
        AND LENGTH(lasterror) > 0
SQL;
        $msrmnt = (new total_qitems())->set_filter_sql($sql);
        $msrmnt->set_name('totalqueueitems_by_status:has_errors');
        $stack[] = $msrmnt;


        $msrmnt = new total_attachments();
        $stack['total:attachments'] = $msrmnt;

        $msrmnt = new total_lrs_records();
        $stack['total:lrs_records'] = $msrmnt;


        return $stack;
    }

    /**
     * @param measurement[] $measurements
     * @return void
     */
    public function save_results(array $measurements) {
        $now = time();
        /** @var measurement $measurement */
        foreach ($measurements as $measurement) {
            $qstat = new queue_stat();
            $qstat->from_measurement($measurement);
            $qstat->set('timemeasured', $now);
            $qstat->save();
        }
    }

}