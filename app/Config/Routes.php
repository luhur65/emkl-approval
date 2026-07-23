<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Login::index');
$routes->match(['get', 'post'], 'login', 'Login::index');
$routes->get('login/logout', 'Login::logout');
$routes->get('dashboard', 'Dashboard::index');

// Mengaktifkan Auto Routing untuk memudahkan kompatibilitas dengan Controller turunan CI3
$routes->setAutoRoute(true);
