<?php

require './jRoute/_load.php';

$route = new jRoute('/jroute', false);

// Register routes
$route->Route(['get', 'post'], '/', function() {
    return 'Hello world';
});

$route->Route(['get', 'post'], '/test/{id}', "contents/includes/test.php");

$route->Route(['get'], '/user/{id}', function ($userId) {
    return 'User with id: ' . $userId;
});

$route->Route(['get'], '/user/{id}/{name}', function ($userId, $name) {
    return 'User with id: ' . $userId . ' and name: ' . $name;
});

// Dispatch request
echo $route->Dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
