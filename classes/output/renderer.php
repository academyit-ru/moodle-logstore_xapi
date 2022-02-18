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
 * renderer класс плагина logstore_xapi
 *
 * @package    logstore_xapi
 * @author    Victor Kilikaev vkilikaev@it.ru
 * @copyright academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi\output;

use logstore_xapi\output\renderable\page_queue_monitor;
use plugin_renderer_base;

class renderer extends plugin_renderer_base {

    /**
     * @param page_queue_monitor $renderable
     *
     * @return string html
     */
    public function render_page_queue_monitor(page_queue_monitor $renderable) {
        return $this->render_from_template('logstore_xapi/page_queue_monitor', $renderable->export_for_template($this));
    }
}
