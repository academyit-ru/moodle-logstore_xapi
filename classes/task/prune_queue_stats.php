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
 * Задание по расписанию для очистки записей из таблицы logstore_xapi_q_stats
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi\task;

use core\task\scheduled_task;
use core_date;
use DateInterval;
use DateTimeImmutable;
use logstore_xapi\local\persistent\queue_stat;
use moodle_database;
use Throwable;

class prune_queue_stats extends scheduled_task
{
    const DEFAULT_CUTOFF_INTERVAL = 'P6M'; // 6 месяцев

    /**
     * @inheritdoc
     */
    public function get_name()
    {
        return get_string('prune_queue_stats_task_name', 'logstore_xapi');
    }

    /**
     * @inheritdoc
     */
    public function execute() {
        list($sql, $params) = $this->build_condition();

        $toprunecount = $this->count_records_select($sql, $params);

        if (0 === $toprunecount) {
            mtrace('Net zapisey k udaleniu. Zaverchau');
            return;
        }

        mtrace('Zapisey k udaleniu: ' . $toprunecount);

        $this->prune_records_select($sql, $params);


        if (0 === $this->count_records_select($sql, $params)) {
            mtrace('Udaleno zapisey ' . $toprunecount);
        }
    }

    /**
     * @return array [string $sql, array $params]
     */
    public function build_condition(): array {
        $cutoffinterval = get_config('logstore_xapi', 'prune_queue_stats_cutoffperiod');
        try {
            $interval = new DateInterval($cutoffinterval);
        } catch (Throwable $e) {
            $interval = new DateInterval(static::DEFAULT_CUTOFF_INTERVAL);
        }

        $now = new DateTimeImmutable('now', core_date::get_server_timezone_object());
        $cutoffdate = $now->sub($interval);

        return ["timemeasured <= :cutoffdate", ['cutoffdate' => $cutoffdate->format('U')]];
    }

    /**
     * @param string $select условие отбора
     * @param array $params sql параметры
     * @return int
     */
    public function count_records_select($select, $params = null): int {
        return queue_stat::count_records_select($select, $params);
    }

    /**
     * @param string $select условие отбора
     * @param array $params sql параметры
     * @return bool
     */
    public function prune_records_select($select, $params = null) {
        /** @var moodle_database $DB */
        global $DB;

        return $DB->delete_records_select(queue_stat::TABLE, $select, $params);
    }
}
