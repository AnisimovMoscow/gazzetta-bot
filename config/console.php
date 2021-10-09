<?php
return [
    'id' => 'gazzetta-bot',
    'language' => 'ru-RU',
    'timeZone' => 'Europe/Moscow',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'app\commands',
    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['info'],
                    'categories' => ['send'],
                    'logFile' => '@runtime/logs/send.log',
                    'logVars' => [],
                ],
                [
                    'class' => 'notamedia\sentry\SentryTarget',
                    'dsn' => 'https://235d40192d42466a8f5fbc6758e374d8@o102782.ingest.sentry.io/5999662',
                    'levels' => ['error', 'warning'],
                    'context' => true,
                ],
            ],
        ],
        'db' => require(__DIR__ . '/db.php'),
    ],
    'params' => require(__DIR__ . '/params.php'),
];
