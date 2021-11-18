# [Moodle Logstore xAPI](https://moodle.org/plugins/view/logstore_xapi)
> Emits [xAPI](https://github.com/adlnet/xAPI-Spec/blob/master/xAPI.md) statements using the [Moodle](https://moodle.org/) Logstore.

- Install the plugin using [our zip installation guide](/docs/install-with-zip.md).
- Process events before the plugin was installed using [our historical events guide](/docs/historical-events.md).
- Ask questions with the [Gitter chat room](https://gitter.im/LearningLocker/learninglocker).
- Report bugs and suggest features with the [Github issue tracker](https://github.com/xAPI-vle/moodle-logstore_xapi/issues).
- View the supported events in [our `get_event_function_map` function](/src/transformer/get_event_function_map.php).
- Change existing statements for the supported events using [our change statements guide](/docs/change-statements.md).
- Create statements using [our new statements guide](/docs/new-statements.md).


Что бы задать id модулей **Итоговой аттестации** нужно задать массив *finalassementcmids* в настройках плагина.
Для этого в *config.php* в свойство *$CFG->forced_plugin_settings* нужно добавить массив *logstore_xapi*, в котором задать массив *finalassementcmids*.
Массив должен содержать id модулей из таблицы course_modules.
```php
// Пример настройки для config.php
$CFG->forced_plugin_settings = [
    'logstore_xapi' => [
        'finalassementcmids' => [111, 222]
        // 'finalassementcmids' => json_encode([111, 222])
    ],
];

```