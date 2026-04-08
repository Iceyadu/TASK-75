<?php
declare(strict_types=1);

use think\facade\Route;

/*
|--------------------------------------------------------------------------
| RideCircle Carpool Marketplace – API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api and return JSON envelopes.
| Middleware aliases are registered in app/middleware.php:
|   'cors'          => \app\middleware\CorsMiddleware::class,
|   'auth'          => \app\middleware\AuthMiddleware::class,
|   'rbac'          => \app\middleware\RbacMiddleware::class,
|   'org_isolation' => \app\middleware\OrgIsolationMiddleware::class,
|   'rate_limit'    => \app\middleware\RateLimitMiddleware::class,
|   'hotlink'       => \app\middleware\HotlinkMiddleware::class,
*/

// ─── Auth (public) ──────────────────────────────────────────────────────────
Route::group('api/auth', function () {
    Route::post('register', 'AuthController@register');
    Route::post('login', 'AuthController@login');
})->middleware('cors');

// ─── Auth (authenticated) ───────────────────────────────────────────────────
Route::group('api/auth', function () {
    Route::post('logout', 'AuthController@logout');
    Route::get('me', 'AuthController@me');
    Route::post('tokens', 'TokenController@store');
    Route::get('tokens', 'TokenController@index');
    Route::delete('tokens/:id', 'TokenController@destroy');
    Route::post('tokens/:id/rotate', 'TokenController@rotate');
})->middleware(['auth', 'org_isolation']);

// ─── Listings ───────────────────────────────────────────────────────────────
Route::group('api/listings', function () {
    Route::get('', 'ListingController@index');
    Route::post('', 'ListingController@store')->middleware('rbac:listing.create');
    Route::post('bulk-close', 'ListingController@bulkClose')->middleware('rbac:listing.admin');
    Route::get(':id', 'ListingController@show');
    Route::put(':id', 'ListingController@update');
    Route::delete(':id', 'ListingController@destroy');
    Route::post(':id/publish', 'ListingController@publish');
    Route::post(':id/unpublish', 'ListingController@unpublish');
    Route::post(':id/flag', 'ListingController@flag')->middleware('rbac:moderation.read');
    Route::get(':id/versions', 'VersionController@index');
    Route::get(':id/versions/:version', 'VersionController@show');
    Route::get(':id/versions/:v1/diff/:v2', 'VersionController@diff');
})->middleware(['auth', 'org_isolation']);

// ─── Search ─────────────────────────────────────────────────────────────────
Route::group('api/search', function () {
    Route::get('suggestions', 'SearchController@suggestions');
    Route::get('did-you-mean', 'SearchController@didYouMean');
})->middleware(['auth', 'org_isolation']);

// ─── Orders ─────────────────────────────────────────────────────────────────
Route::group('api/orders', function () {
    Route::get('', 'OrderController@index');
    Route::post('', 'OrderController@store');
    Route::get(':id', 'OrderController@show');
    Route::post(':id/accept', 'OrderController@accept');
    Route::post(':id/start', 'OrderController@start');
    Route::post(':id/complete', 'OrderController@complete');
    Route::post(':id/cancel', 'OrderController@cancel');
    Route::post(':id/dispute', 'OrderController@dispute');
    Route::post(':id/resolve', 'OrderController@resolve')->middleware('rbac:dispute.resolve');
})->middleware(['auth', 'org_isolation']);

// ─── Reviews ────────────────────────────────────────────────────────────────
Route::group('api/reviews', function () {
    Route::get('', 'ReviewController@index');
    Route::post('', 'ReviewController@store')->middleware('rate_limit:reviews,3,60');
    Route::put(':id', 'ReviewController@update');
    Route::delete(':id', 'ReviewController@destroy');
})->middleware(['auth', 'org_isolation']);

// ─── Media (hotlink-protected) ──────────────────────────────────────────────
Route::group('api/media', function () {
    Route::get(':id', 'MediaController@show');
    Route::get(':id/thumbnail', 'MediaController@thumbnail');
})->middleware(['hotlink']);

// ─── Moderation ─────────────────────────────────────────────────────────────
Route::group('api/moderation', function () {
    Route::get('queue', 'ModerationController@index')->middleware('rbac:moderation.read');
    Route::post('queue/:id/approve', 'ModerationController@approve')->middleware('rbac:moderation.update');
    Route::post('queue/:id/reject', 'ModerationController@reject')->middleware('rbac:moderation.update');
    Route::post('queue/:id/escalate', 'ModerationController@escalate')->middleware('rbac:moderation.update');
})->middleware(['auth', 'org_isolation']);

// ─── Governance (admin) ─────────────────────────────────────────────────────
Route::group('api/governance', function () {
    Route::get('quality-metrics', 'GovernanceController@metrics');
    Route::get('lineage', 'GovernanceController@lineage');
    Route::get('events', 'GovernanceController@events');
})->middleware(['auth', 'org_isolation', 'rbac:governance.view_dashboard']);

// ─── Audit (admin) ──────────────────────────────────────────────────────────
Route::group('api/audit', function () {
    Route::get('logs', 'AuditController@index');
})->middleware(['auth', 'org_isolation', 'rbac:audit.read']);

// ─── Org settings (admin) ───────────────────────────────────────────────────
Route::group('api/org', function () {
    Route::get('settings', 'OrgController@show')->middleware('rbac:org_settings.read');
    Route::put('settings', 'OrgController@update')->middleware('rbac:org_settings.update');
})->middleware(['auth', 'org_isolation']);

// ─── Users (admin) ──────────────────────────────────────────────────────────
Route::group('api/users', function () {
    Route::get('', 'UserController@index');
    Route::get(':id', 'UserController@show');
    Route::put(':id/roles', 'UserController@updateRoles')->middleware('rbac:role.manage');
    Route::post(':id/disable', 'UserController@disable');
    Route::post(':id/enable', 'UserController@enable');
})->middleware(['auth', 'org_isolation', 'rbac:user.manage']);

// ─── Catch-all 404 ─────────────────────────────────────────────────────────
Route::miss(function () {
    return json([
        'code'    => 40401,
        'message' => 'Endpoint not found',
        'data'    => null,
        'errors'  => null,
    ], 404);
});
