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
 * Класс для создания коллекции виджетов
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\local\queue_monitor;

use logstore_xapi\local\queue_monitor\measurements\measurements_repository;
use logstore_xapi\local\queue_monitor\widget\total_queue_items;
use logstore_xapi\output\renderable\widget\chart_widget;
use logstore_xapi\output\renderable\widget\data_point_widget;
use moodle_database;

class widget_factory {

    /**
     * @var moodle_database
     */
    protected $db;

    /**
     *
     * @param moodle_database $db
     */
    public function __construct(moodle_database $db) {
        $this->db = $db;
    }

    /**
     *
     * @return templatable[]
     */
    public function make_widget_collection() {
        $measurementsrepo = $this->make_measurements_repository($this->db);
        $constructors = [
            total_queue_items::class => null
            // chart_widget      - Количество задач в очереди (всего) за последние 6 месяцев
            // chart_widget      - Количество задач в очереди EMIT_STATEMENTS за последние 6 месяцев
            // chart_widget      - Количество задач в очереди PUBLISH_ATTACHMENTS за последние 6 месяцев
            // chart_widget      - Количество задач isbanned за последние 6 месяцев
            // chart_widget      - Артефактов отправлено attachments published за последние 6 месяцев
            // chart_widget      - Записей ЦС отправлено LRS records published за последние 6 месяцев

            // data_point_widget - Количество задач в очереди (всего) на данный момент
            // data_point_widget - Количество задач в очереди EMIT_STATEMENTS на данный момент
            // data_point_widget - Количество задач в очереди PUBLISH_ATTACHMENTS на данный момент
            // data_point_widget - Количество задач в очереди (всего) isrunning
            // data_point_widget - Количество задач в очереди (всего) stuck running
            // data_point_widget - Количество задач в очереди (всего) isbanned
            // data_point_widget - Артефактов отправлено attachments published
            // data_point_widget - Записей ЦС отправлено LRS records published
        ];


        $widgets = [];
        foreach ($constructors as $widgetclass => $constructor) {
            if (is_callable($constructor)) {
                $widget = call_user_func($constructor);
            } else {
                $widget = new $widgetclass($measurementsrepo);
            }
            $widgets[] = $widget;
        }

        return $widgets;
    }

    /**
     * @param moodle_database $db
     * @return measurements_repository
     */
    protected function make_measurements_repository(moodle_database $db) {
        return new measurements_repository($db);
    }
}
