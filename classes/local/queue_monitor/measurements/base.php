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
 * Базовый класс queue_monitor\measurements
 *
 * @package   logstore_xapi
 * @author    Victor Kilikaev vkilikaev@it.ru
 * @copyright academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi\local\queue_monitor\measurements;

abstract class base {

    /**
     * @var mixed Реузультат сбора
     */
    protected $result;

    /**
     * @var null|string|Throwable Хранит ошибку возникшую во время сбора данных
     */
    protected $error;

    /**
     * @var string
     */
    protected $name;

    public function __construct() {
        $this->name = __CLASS__;
    }

    /**
     * @return void
     */
    abstract public function run();

    /**
     *
     * @return null|string|Throwable
     */
    public function get_error() {
        return $this->error;
    }

    /**
     * @return mixed
     */
    public function get_result() {
        return $this->result;
    }

    /**
     * @param string $name
     * @return self
     */
    public function set_name(string $name) {
        $this->name = $name;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Очистит собранные данные
     *
     * @return self
     */
    public function clear() {
        unset($this->error);
        unset($this->result);
        return $this;
    }

    /**
     *
     * @return mixed
     */
    public function __invoke() {
        if (! $this->get_result()) {
            $this->run();
        }
        return $this->get_result();
    }
}