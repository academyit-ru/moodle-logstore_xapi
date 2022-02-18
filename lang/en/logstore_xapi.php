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

$string['endpoint'] = 'Endpoint';
$string['settings'] = 'General Settings';
$string['xapifieldset'] = 'Custom example fieldset';
$string['xapi'] = 'xAPI';
$string['password'] = 'Password';
$string['pluginadministration'] = 'Logstore xAPI administration';
$string['pluginname'] = 'Logstore xAPI';
$string['submit'] = 'Submit';
$string['username'] = 'Username';
$string['xapisettingstitle'] = 'Logstore xAPI Settings';
$string['backgroundmode'] = 'Send statements by scheduled task?';
$string['backgroundmode_desc'] = 'This will force Moodle to send the statements to the LRS in the background,
        via a cron task to avoid blocking page responses. This will make the process less close to real time, but will help to prevent unpredictable
        Moodle performance linked to the performance of the LRS.';
$string['maxbatchsize'] = 'Maximum batch size';
$string['maxbatchsize_desc'] = 'Statements are sent to the LRS in batches. This setting controls the maximum number of
        statements that will be sent in a single operation. Setting this to zero will cause all available statements to
        be sent at once, although this is not recommended.';
$string['taskemit'] = 'Emit records to LRS';
$string['routes'] = 'Include actions with these routes';
$string['filters'] = 'Filter logs';
$string['logguests'] = 'Log guest actions';
$string['filters_help'] = 'Enable filters that INCLUDE some actions to be logged.';
$string['mbox'] = 'Identify users by email';
$string['mbox_desc'] = 'Statements will identify users with their email (mbox) when this box is ticked.';
$string['send_username'] = 'Identify users by id';
$string['send_username_desc'] = 'Statements will identify users with their username when this box is ticked, but only if identifying users by email is disabled.';
$string['shortcourseid'] = 'Send short course name';
$string['shortcourseid_desc'] = 'Statements will contain the shortname for a course as a short course id extension';
$string['sendidnumber'] = 'Send course and activity ID number';
$string['sendidnumber_desc'] = 'Statements will include the ID number (admin defined) for courses and activities in the object extensions';
$string['send_response_choices'] = 'Send response choices';
$string['send_response_choices_desc'] = 'Statements for multiple choice question answers will be sent to the LRS with the correct response and potential choices';
$string['institution'] = 'АНО "Университет Национальной технологической инициативы 2035"';
$string['courses'] = 'Курсы';
$string['token'] = 'Токен';
$string['tasksync'] = 'Синхронизировать журналы событий';
$string['taskemitfailed'] = 'Отправка следа из журнала logstore_xapi_failed_log';
$string['emit_statement_task_name'] = 'Отправит xAPI выражение в LRS из очереди emit_statement';
$string['publish_attachment_task_name'] = 'Отправит артефакт обучения в S3';
$string['queue_item_requeued_event'] = 'Задача вернулась в очередь';
$string['ban_reason_attemptslimit_reached'] = 'Достигнут лимит попыток';
$string['xapi_record_registered_event'] = 'xAPI выражение было зарегистрировано в LRS';
$string['enqueue_jobs_task_name'] = 'Logstore_xAPI: Помещает в очередь задачи по обработке журнала';
$string['attachment_published_event'] = 'Артефакт обучения был отправлен в S3';
$string['queue_item_completed_event'] = 'Задача обработана';
$string['queue_item_banned_event'] = 'Задача была заблокирована';
$string['learning_artefacts_heading'] = 'Отправка артефактов обучения';
$string['learning_artefacts_heading_help'] = 'Настройки обеспечивающие отправку артефактов обучения';
$string['s3_endpoint_setting'] = 'S3 endpoint';
$string['s3_endpoint_setting_desc'] = 'ссылка для подключения к S3 хранилищу';
$string['s3_bucket_setting'] = 'S3 bucket';
$string['s3_bucket_setting_desc'] = 'S3 bucket в котором нужно размещать выгружаемые артефакты';
$string['u2035_aws_key_setting'] = 'Ключь к S3';
$string['u2035_aws_key_setting_desc'] = 'S3 ключ доступа (AWS access key ID)';
$string['u2035_aws_secrety_setting'] = 'Секрет к S3';
$string['u2035_aws_secrety_setting_desc'] = 'S3 секретный ключ (AWS secret access key)';
$string['read_stats_of_the_queue_task_name'] = 'Соберёт данные о процессе обработки очереди задач';
$string['prune_queue_stats_task_name'] = 'Очистит устаревшие данные статистики очереди';
$string['widget_title_total_queue_items'] = 'Количество задач в обработке';
