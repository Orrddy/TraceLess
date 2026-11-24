<?php

use App\Http\Controllers\AccountExportController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReportDownloadController;
use App\Http\Controllers\ScanStatusController;
use App\Livewire\Admin\ActivityLog;
use App\Livewire\Admin\EmailDelivery;
use App\Livewire\Admin\FailedJobs;
use App\Livewire\Admin\Overview;
use App\Livewire\Admin\ScanInspector;
use App\Livewire\Admin\UsersIndex;
use App\Livewire\Credentials\CredentialExposures;
use App\Livewire\Dashboard\Dashboard;
use App\Livewire\Findings\FindingShow;
use App\Livewire\Findings\FindingsIndex;
use App\Livewire\Identity\IdentityIndex;
use App\Livewire\Identity\IdentitySetup;
use App\Livewire\Monitoring\MonitoringPage;
use App\Livewire\Removals\RemovalCenter;
use App\Livewire\Removals\RemovalShow;
use App\Livewire\Reports\ReportsPage;
use App\Livewire\Scans\ScanCreate;
use App\Livewire\Scans\ScanProgress;
use App\Livewire\Scans\ScansIndex;
use App\Livewire\Settings\SettingsPage;
use App\Livewire\Tools\IpCheck;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => view('landing'))->name('home');
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::view('/terms', 'pages.terms')->name('terms');
Route::view('/security', 'pages.security')->name('security');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');

    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    // Passwordless email OTP. The code page is shared by register and login.
    Route::get('/auth/verify', [AuthController::class, 'showVerify'])->name('auth.verify');
    Route::post('/auth/verify', [AuthController::class, 'verify'])
        ->middleware('throttle:12,1')
        ->name('auth.verify.submit');
    Route::post('/auth/verify/resend', [AuthController::class, 'resend'])
        ->middleware('throttle:4,1')
        ->name('auth.verify.resend');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated command center
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/identity', IdentityIndex::class)->name('identity');
    Route::get('/identity/create', IdentitySetup::class)->name('identity.create');

    Route::get('/scans', ScansIndex::class)->name('scans.index');
    Route::get('/scans/create', ScanCreate::class)->name('scans.create');
    Route::get('/scans/{scan}/status', ScanStatusController::class)->name('scans.status');
    Route::get('/scans/{scan}', ScanProgress::class)->name('scans.show');

    Route::get('/findings', FindingsIndex::class)->name('findings');
    Route::get('/findings/{finding}', FindingShow::class)->name('findings.show');

    Route::get('/credentials', CredentialExposures::class)->name('credentials');

    Route::get('/removals', RemovalCenter::class)->name('removals');
    Route::get('/removals/{removal}', RemovalShow::class)->name('removals.show');

    Route::get('/monitoring', MonitoringPage::class)->name('monitoring');
    Route::get('/alerts', MonitoringPage::class)->name('alerts');

    Route::get('/reports', ReportsPage::class)->name('reports');
    Route::get('/reports/{report}/download', ReportDownloadController::class)
        ->middleware('signed')
        ->name('reports.download');

    Route::get('/settings', SettingsPage::class)->name('settings');
    Route::get('/account/export', AccountExportController::class)->name('account.export');

    // Self-service tools. The password check runs entirely in the browser.
    Route::view('/tools/password-check', 'pages.password-check')->name('password.check');
    Route::get('/tools/ip-check', IpCheck::class)->name('ip.check');
});

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
|
| Access is enforced by the `admin` middleware on these routes, and again
| inside every mutating Livewire action: Livewire actions are dispatched
| through /livewire/update, which does not pass through route middleware.
|
*/
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Overview::class)->name('overview');
    Route::get('/users', UsersIndex::class)->name('users');
    Route::get('/activity', ActivityLog::class)->name('activity');
    Route::get('/scans/{scan}', ScanInspector::class)->name('scans.show');
    Route::get('/failed-jobs', FailedJobs::class)->name('failed-jobs');
    Route::get('/email', EmailDelivery::class)->name('email');
});
