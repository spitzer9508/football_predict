<?php

require __DIR__ . '/vendor/autoload.php';

use App\Support\Env;

Env::load(__DIR__ . '/.env');

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'UTC');
