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
 * Скрипт для вывода инфомармации о состоянии очередей
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// TODO: Написать скрипт queues/view.php

страница выводит информацию из таблицы logstore_xapi_queue

рабита на 2 блока расположенных вертикально друг под другом.
В вернхем размещён виджет со списком задач в обработке

В нижнем виджете список разбитый на страницы с задачами которые ещё на переданы обработчикам.

На странице Админ может
- Заблокировать задачу из списка к обработке, указать причину
- Открыть страницу queues/view.php?id=12345 для просмотра подробностей задачи
- сбросить счётчик попыток выполнения
- посмотреть события связанные с задачей сгенерированные плагином logstore_xapi