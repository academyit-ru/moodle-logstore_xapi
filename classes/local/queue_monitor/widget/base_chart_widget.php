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

abstract class base_chart_widget implements chart_widget_interface {

    /**
     * @var chart_base
     */
    protected $chart;

    /**
     * @var measurements_repository
     */
    protected $measurementsrepo;

    /**
     * @param measurements_repository $measurementsrepo
     *
     */
    public function __constructor(measurements_repository $measurementsrepo) {
        $this->measurementsrepo = $measurementsrepo;
    }

    /**
     * @return chart_base
     *
     */
    public function get_chart_instance() {
        if (!$this->chart) {
            $this->chart = $this->build_chart_instance();
        }

        return $this->chart;
    }


    /**
     * @return chart_base
     */
    abstract protected function build_chart_instance();

}
