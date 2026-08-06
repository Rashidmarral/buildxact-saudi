<?php

use App\Core\Auth;
use App\Core\Router;
use App\Controllers\Site\HomeController;
use App\Controllers\Auth\AuthController;
use App\Controllers\User\DashboardController;
use App\Controllers\User\ProjectController;
use App\Controllers\User\ClientController;
use App\Controllers\User\EstimateController;
use App\Controllers\User\InvoiceController;
use App\Controllers\User\ScheduleController;
use App\Controllers\User\TeamController;
use App\Controllers\User\BillingController;
use App\Controllers\User\SettingsController;
use App\Controllers\Admin\AdminDashboardController;
use App\Controllers\Admin\CompanyController;
use App\Controllers\Admin\PlanController;
use App\Controllers\Admin\PaymentController;
use App\Controllers\Admin\AdminUserController;

/** @var Router $router */

// ---------- Public marketing site ----------
$router->get('/', [HomeController::class, 'index']);
$router->get('/features', [HomeController::class, 'features']);
$router->get('/pricing', [HomeController::class, 'pricing']);
$router->get('/about', [HomeController::class, 'about']);
$router->get('/contact', [HomeController::class, 'contact']);
$router->post('/contact', [HomeController::class, 'contactSubmit']);
$router->get('/privacy', [HomeController::class, 'privacy']);
$router->get('/terms', [HomeController::class, 'terms']);

// ---------- Auth ----------
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->post('/logout', [AuthController::class, 'logout']);

// ---------- User (company) panel ----------
$router->group([fn() => Auth::requireCompanyUser()], function (Router $router) {
    $router->get('/app', [DashboardController::class, 'index']);

    $router->get('/app/projects', [ProjectController::class, 'index']);
    $router->get('/app/projects/create', [ProjectController::class, 'create']);
    $router->post('/app/projects', [ProjectController::class, 'store']);
    $router->get('/app/projects/{id}', [ProjectController::class, 'show']);
    $router->get('/app/projects/{id}/edit', [ProjectController::class, 'edit']);
    $router->post('/app/projects/{id}', [ProjectController::class, 'update']);
    $router->post('/app/projects/{id}/delete', [ProjectController::class, 'destroy']);

    $router->get('/app/clients', [ClientController::class, 'index']);
    $router->get('/app/clients/create', [ClientController::class, 'create']);
    $router->post('/app/clients', [ClientController::class, 'store']);
    $router->get('/app/clients/{id}/edit', [ClientController::class, 'edit']);
    $router->post('/app/clients/{id}', [ClientController::class, 'update']);
    $router->post('/app/clients/{id}/delete', [ClientController::class, 'destroy']);

    $router->get('/app/estimates', [EstimateController::class, 'index']);
    $router->get('/app/estimates/create', [EstimateController::class, 'create']);
    $router->post('/app/estimates', [EstimateController::class, 'store']);
    $router->get('/app/estimates/{id}', [EstimateController::class, 'show']);
    $router->post('/app/estimates/{id}/status', [EstimateController::class, 'updateStatus']);
    $router->post('/app/estimates/{id}/delete', [EstimateController::class, 'destroy']);

    $router->get('/app/invoices', [InvoiceController::class, 'index']);
    $router->get('/app/invoices/create', [InvoiceController::class, 'create']);
    $router->post('/app/invoices', [InvoiceController::class, 'store']);
    $router->get('/app/invoices/{id}', [InvoiceController::class, 'show']);
    $router->post('/app/invoices/{id}/status', [InvoiceController::class, 'updateStatus']);
    $router->post('/app/invoices/{id}/delete', [InvoiceController::class, 'destroy']);

    $router->get('/app/schedule', [ScheduleController::class, 'index']);
    $router->post('/app/schedule', [ScheduleController::class, 'store']);
    $router->post('/app/schedule/{id}/status', [ScheduleController::class, 'updateStatus']);
    $router->post('/app/schedule/{id}/delete', [ScheduleController::class, 'destroy']);

    $router->get('/app/team', [TeamController::class, 'index']);
    $router->post('/app/team', [TeamController::class, 'store']);
    $router->post('/app/team/{id}/delete', [TeamController::class, 'destroy']);

    $router->get('/app/billing', [BillingController::class, 'index']);
    $router->post('/app/billing/upgrade', [BillingController::class, 'upgrade']);

    $router->get('/app/settings', [SettingsController::class, 'index']);
    $router->post('/app/settings', [SettingsController::class, 'update']);
});

// ---------- Platform admin panel ----------
$router->group([fn() => Auth::requireSuperAdmin()], function (Router $router) {
    $router->get('/admin', [AdminDashboardController::class, 'index']);

    $router->get('/admin/companies', [CompanyController::class, 'index']);
    $router->get('/admin/companies/{id}', [CompanyController::class, 'show']);
    $router->post('/admin/companies/{id}/status', [CompanyController::class, 'updateStatus']);

    $router->get('/admin/plans', [PlanController::class, 'index']);
    $router->get('/admin/plans/create', [PlanController::class, 'create']);
    $router->post('/admin/plans', [PlanController::class, 'store']);
    $router->get('/admin/plans/{id}/edit', [PlanController::class, 'edit']);
    $router->post('/admin/plans/{id}', [PlanController::class, 'update']);
    $router->post('/admin/plans/{id}/delete', [PlanController::class, 'destroy']);

    $router->get('/admin/payments', [PaymentController::class, 'index']);

    $router->get('/admin/admins', [AdminUserController::class, 'index']);
    $router->post('/admin/admins', [AdminUserController::class, 'store']);
    $router->post('/admin/admins/{id}/delete', [AdminUserController::class, 'destroy']);
});
