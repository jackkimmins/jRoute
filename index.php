<?php

session_start();
$_SESSION['role'] = 'admin';

// Include jRoute
require './jRoute/_load.php';

$RouterOptions = new RouterOptions(
    debugMode: false,
    urlPrefix: '/jroute',
    cspLevel: 'medium'
);

// Create a new instance of jRoute
$route = new jRoute($RouterOptions);

// Register routes
$route->Route(['get', 'post'], '/', function() {
    return 'Hello, world!';
}, ['admin']);

$route->Route(['get', 'post'], '/test/{id}', "contents/includes/test.php");

$route->Route(['get'], '/user/{id}', function ($userId) {
    return 'User with id: ' . $userId;
});

$route->Route(['get'], '/user/{id}/{name}', function ($userId, $name) {
    return 'User with id: ' . $userId . ' and name: ' . $name;
});

$_SESSION['role'] = 'admin';
$route->AddDir('/public', './contents/public', ['admin', 'staff']);

// Dispatch request
echo $route->Dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);