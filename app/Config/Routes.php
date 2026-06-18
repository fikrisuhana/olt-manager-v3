<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Redirect root ke dashboard atau login
$routes->get('/', static function () {
    return session()->get('user_id')
        ? redirect()->to('/dashboard')
        : redirect()->to('/login');
});

// Auth (tidak perlu login)
$routes->get('login',           'AuthController::login');
$routes->post('login/process',  'AuthController::loginProcess');
$routes->get('register',        'AuthController::register');
$routes->post('register/save',  'AuthController::registerSave');
$routes->get('logout',          'AuthController::logout');

// Semua route berikut memerlukan login (filter: auth)
$routes->group('', ['filter' => 'auth'], static function ($routes) {

    // Dashboard
    $routes->get('dashboard', 'DashboardController::index');

    // OLT
    $routes->get('olts',                    'OltController::index');
    $routes->get('olts/create',             'OltController::create');
    $routes->post('olts/store',             'OltController::store');
    $routes->get('olts/(:num)',             'OltController::show/$1');
    $routes->get('olts/(:num)/edit',        'OltController::edit/$1');
    $routes->post('olts/(:num)/update',     'OltController::update/$1');
    $routes->get('olts/(:num)/delete',      'OltController::delete/$1');
    $routes->post('olts/test-telnet',           'OltController::testTelnet');       // AJAX — test koneksi
    $routes->post('olts/fetch-tcont',           'OltController::fetchTcont');       // AJAX — ambil TCONT profiles
    $routes->get('olts/(:num)/scan',           'OltController::scan/$1');          // AJAX — 1 cmd OLT
    $routes->get('olts/(:num)/refresh-cache', 'OltController::refreshCache/$1'); // AJAX — berat, jarang
    $routes->get('olts/(:num)/acs-status',   'OltController::acsStatus/$1');     // AJAX
    $routes->get('olts/(:num)/cache-data',   'OltController::cacheData/$1');     // AJAX

    // ONU
    $routes->post('olts/(:num)/onu/register', 'OnuController::register/$1'); // AJAX
    $routes->get('onus',                      'OnuController::index');
    $routes->get('onus/(:num)',               'OnuController::show/$1');
    $routes->post('onus/(:num)/delete',       'OnuController::delete/$1');    // AJAX
    $routes->get('onus/(:num)/signal',        'OnuController::signal/$1');    // AJAX
    $routes->get('onus/(:num)/acs-info',     'OnuController::acsInfo/$1');   // AJAX
    $routes->post('onus/(:num)/acs-set',     'OnuController::acsSet/$1');    // AJAX

    // Templates
    $routes->get('templates',               'TemplateController::index');
    $routes->get('templates/create',        'TemplateController::create');
    $routes->post('templates/store',        'TemplateController::store');
    $routes->get('templates/(:num)/edit',   'TemplateController::edit/$1');
    $routes->post('templates/(:num)/update','TemplateController::update/$1');
    $routes->get('templates/(:num)/delete', 'TemplateController::delete/$1');

    // ACS
    $routes->get('acs',                     'AcsController::index');
    $routes->get('acs/create',              'AcsController::create');
    $routes->post('acs/store',              'AcsController::store');
    $routes->get('acs/(:num)/delete',       'AcsController::delete/$1');
    $routes->get('acs/(:num)/default',      'AcsController::setDefault/$1');
    $routes->get('acs/(:num)/test',         'AcsController::test/$1');        // AJAX
});

// Admin-only routes
$routes->group('admin', ['filter' => 'admin'], static function ($routes) {
    $routes->get('users',                     'AdminController::users');
    $routes->get('users/create',              'AdminController::userCreate');
    $routes->post('users/store',              'AdminController::userStore');
    $routes->get('users/(:num)/edit',         'AdminController::userEdit/$1');
    $routes->post('users/(:num)/update',      'AdminController::userUpdate/$1');
    $routes->get('users/(:num)/delete',       'AdminController::userDelete/$1');

    $routes->get('olts',  'AdminController::olts');
    $routes->get('acs',   'AdminController::acs');
    $routes->get('logs',  'AdminController::logs');
});
