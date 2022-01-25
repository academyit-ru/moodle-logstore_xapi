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
 * Задание по расписанию для отправки xAPI выражений в LRS
 *
 * @package    logstore_xapi
 * @author    Victor Kilikaev vkilikaev@it.ru
 * @copyright academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi\task;

use logstore_xapi\local\emit_statements_batch_job;
use logstore_xapi\local\queue_service;
use moodle_database;
use moodle_exception;
use Throwable;

class emit_statement extends \core\task\scheduled_task {

    const DEFAULT_BATCH_SIZE = 150;

    /**
     * @inheritdoc
     */
    public function get_name() {
        return get_string('emit_statement_task_name', 'logstore_xapi');
    }

    /**
     * @inheritdoc
     */
    public function execute() {
        /** @var moodle_database $DB */
        global $DB;

        $batchsize = get_config('logstore_xapi', 'maxbatchsize');
        if (false === $batchsize) {
            $batchsize = static::DEFAULT_BATCH_SIZE;
        }
        $queueservice = queue_service::instance();

        try {
            mtrace(sprintf('Gettig items from queue %s...', queue_service::QUEUE_EMIT_STATEMENTS));
            $records = $queueservice->pop($batchsize, queue_service::QUEUE_EMIT_STATEMENTS);
            if (0 === count($records)) {
                mtrace('There are no log records to sent. Stopping.');
                return;
            }
            mtrace(sprintf('-- Queue items count %d', count($records)));
            $batchjob = new emit_statements_batch_job($records, $DB);
            $batchjob->run();
            $completeditems = $batchjob->result_success();
            $queueservice->complete($completeditems);
            $itemswitherror = $batchjob->result_error();
            if ([] !== $itemswitherror) {
                $queueservice->requeue($itemswitherror);
            }
            mtrace(sprintf('-- Completed %d; Has errors %d', count($completeditems), count($itemswitherror)));
        } catch (moodle_exception $e) {
            $errmsg = sprintf('[LOGSTORE_XAPI][ERROR] %s %s debug: %s', static::class, $e->getMessage(), $e->debuginfo);
            error_log($errmsg);
            debugging($errmsg, DEBUG_DEVELOPER);
            $queueservice->requeue($records);
        } catch (Throwable $e) {
            $errmsg = sprintf('[LOGSTORE_XAPI][ERROR] %s %s', static::class, $e->getMessage());
            error_log($errmsg);
            debugging($errmsg, DEBUG_DEVELOPER);
            $queueservice->requeue($records);
        }
    }
}
