<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Login::index');
$routes->match(['get', 'post'], 'login', 'Login::index');
$routes->get('login/logout', 'Login::logout');
$routes->get('home', 'Home::index');
$routes->get('approvaltop', 'ApprovalTop::index');
$routes->get('approvaltop/ajax_list', 'ApprovalTop::ajax_list');
$routes->get('approvaltop/get_detail/(:any)', 'ApprovalTop::get_detail/$1');
$routes->get('approvaltop/reverse', 'ApprovalTop::reverse');
$routes->post('approvaltop/approved', 'ApprovalTop::approved');

// Approval PO Module
$routes->get('approvalpo', 'ApprovalPo::index');
$routes->get('approvalpo/ajax_list', 'ApprovalPo::ajax_list');
$routes->post('approvalpo/approved', 'ApprovalPo::approved');

$routes->get('harilibur', 'Harilibur::index');

// Mengaktifkan Auto Routing untuk memudahkan kompatibilitas dengan Controller turunan CI3
$routes->setAutoRoute(true);
