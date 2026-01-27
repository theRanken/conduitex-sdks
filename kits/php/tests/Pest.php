<?php

require_once __DIR__ . '/../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

function conduitexLive(): bool {
    return getenv('CONDUITEX_LIVE_TESTS') === '1';
}

uses()->group('sdk');
