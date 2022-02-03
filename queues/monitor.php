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
 * Скрипт для вывода инфомармации из монитора очереди
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

Страница отображает виджеты с информацией из монитора очереди

выводит текущие значения показателей:
    - всех задач:
        - всего
        - в каждой очереди
    - в обработке:
        - всего
        - в каждой очереди
    - задч заблокировано
        - всего
        - в каждой очереди
    - задач с ошибками
        - всего
        - в каждой очереди

Выводит графики динамики показателей:
    - изменение общего числа задач в очереди
    - изменение общего числа заблокированных задачь
    - изменение общего числа задач в обработке
    - изменение общего числа задач с ошибками

Частотное распределение ошибок
    - текст ошибки - количество раз возникновения ошибки за последние сутки