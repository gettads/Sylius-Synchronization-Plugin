<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (empty($_ENV['APP_ENV'])) {
    throw new RuntimeException("Environment variable 'APP_ENV' is not set");
}
(new \Symfony\Component\Filesystem\Filesystem())->remove(__DIR__ . '/../var/cache/' . $_ENV['APP_ENV']);
