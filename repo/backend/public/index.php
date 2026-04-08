<?php

namespace think;

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require __DIR__ . '/../vendor/autoload.php';

$http = (new App())->http;
$response = $http->run();
$response->send();
$http->end($response);
