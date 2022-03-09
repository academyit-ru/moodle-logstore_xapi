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
 * Класс для сбора статистики
 * общее число записей в очереди
 *
 * @package   logstore_xapi
 * @author    Victor Kilikaev vkilikaev@it.ru
 * @copyright academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi\local\queue_monitor\measurements;

use logstore_xapi\local\persistent\queue_item;
use logstore_xapi\local\queue_monitor\measurements\base as base_measurement;

class total_qitems extends base_measurement {

    protected $filters = [];

    /**
     * @var string Фрагмент sql запроса для оператора WHERE. Если атрибут задан то $filters игнорируется
     */
    protected $sql;

    /**
     * @var mixed[]
     */
    protected $sqlparams;

    /**
     *
     *
     */
    public function __construct() {
        parent::__construct();
        $this->filters['isbanned'] = false;
    }

    /**
     * @param string $field
     * @param mixed $value
     * @return self
     */
    public function set_filter($field, $value) {
        $this->filters[$field] = $value;
        return $this;
    }

    /**
     * Позволяет указать фрагмент sql запроса который будет применён к оператору WHERE.
     * Фильтры заданные через set_filter не будут учитыватся в запросе
     *
     * @param string $sql
     * @param mixed[] $params
     * @return self
     */
    public function set_filter_sql(string $sql, array $params = []) {
        $this->sql = $sql;
        $this->sqlparams = $params;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function run() {
        if ($this->sql) {
            $this->result = queue_item::count_records_select($this->sql, $this->sqlparams);
        } else {
            $this->result = queue_item::count_records($this->filters);
        }
    }
}
