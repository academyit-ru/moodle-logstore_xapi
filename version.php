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

defined('MOODLE_INTERNAL') || die();

$plugin = isset($plugin) && is_object($plugin) ? $plugin : new \stdClass();
$plugin->component = 'logstore_xapi';
$plugin->version = 2021111903;
$plugin->release = 'v3.18.1-aplana-1.0.5';
$plugin->requires = 2014111000;
$plugin->maturity = MATURITY_STABLE;
