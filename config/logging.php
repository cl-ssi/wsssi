<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */


    'channels' => [
        'google_cloud_logging' => [
            'driver' => 'custom',
            'projectId' => env('GOOGLE_CLOUD_PROJECT_ID'),
            'logName' => 'esmeralda',
            'labels' => [
                'APP_NAME' => json_encode(env('APP_NAME')),
                'APP_ENV' => env('APP_ENV'),
                'APP_DEBUG' => json_encode(env('APP_DEBUG')),
                'APP_URL' => env('APP_URL'),
            ],
            'handler' => App\Logging\GoogleCloudHandler::class,
            'via' => App\Logging\GoogleCloudLogging::class,
            'level' => 'debug',
        ],

        'stack' => [
            'driver' => 'stack',
            'channels' => ['teams'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
        ],

        'integracionEpivigila' => [
            'driver' => 'single',
            'path' => storage_path('logs/integracion_epivigila.log'),
            'level' => 'debug',
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('APP_NAME'),
            'emoji' => ':boom:',
            'level' => 'debug',
        ],

        'teams' => [
            'driver' => 'custom',//#1
            'via' => LogMonolog::class,//#2
            'webhookDsn' => env('LOG_TEAMS_WEBHOOK_URL'),//#3
            'level' => 'debug',//#6
            'title' => 'Log Siremx',//#4
            'subject' => 'Message Subject',//#5 
            'emoji' => '&#x1F3C1',//#7
            'color' => '#fd0404',//#8
            'format' => '[%datetime%] %channel%.%level_name%: %message%'//#9
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => 'debug',
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => 'debug',
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => 'debug',
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        'incoming_hl7' => [
            'driver' => 'single',
            'path' => storage_path('logs/incoming_hl7.log'),
        ],

        'suspect_cases_json' => [
            'driver' => 'single',
            'path' => storage_path('logs/suspect_cases_json.log'),
        ],

    ],

];
