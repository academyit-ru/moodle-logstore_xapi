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

namespace src\loader;

use coding_exception;

defined('MOODLE_INTERNAL') || die();

function handler(array $config, array $statements) {
    $loadername = $config['loader'];
    if (is_string($loadername) && is_callable("\src\loader\\$loadername\load")) {
        $load = "\src\loader\\$loadername\load";
    } else if (is_callable($loadername)) {
        $load = $loadername;
    } else {
        throw new coding_exception('Invalid loader', $loadername);
    }
    return $load($config, $statements);
}