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
 * Виджет для вывода графика о составе очереди за период
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\local\queue_monitor\widget;

use logstore_xapi\local\queue_monitor\measurements\measurements_repository;
use core\chart_bar;
use core\chart_base;
use core\chart_series;

class total_queue_items extends base_chart_widget {

    /**
     * @var string
     */
    protected $title;

    /**
     * @inheritdoc
     */
    public function __construct(measurements_repository $measurementsrepo) {

        parent::__constructor($measurementsrepo);
        $this->title = get_string('widget_title_total_queue_items', 'logstore_xapi');
    }

    /**
     * @return chart_base
     */
    protected function build_chart_instance() {

        $chart = new chart_bar();
        $chart->set_title($this->title);

        $data = $this->measurementsrepo->get_total_qitems_series();

        $totalqitems = new chart_series(
            get_string('queue_items', 'logstore_xapi'),
            array_values($data)
        );

        $chart->add_series($totalqitems);
        $chart->set_labels(array_keys($data));

        return $chart;
    }
}
