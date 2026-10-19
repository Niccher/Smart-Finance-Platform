<?php

namespace Config;

// Create a new instance of our RouteCollection class.
$routes = Services::routes();

/*
 * --------------------------------------------------------------------
 * Router Setup
 * --------------------------------------------------------------------
 */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
// The Auto Routing (Legacy) is very dangerous. It is easy to create vulnerable apps
// where controller filters or CSRF protection are bypassed.
// If you don't want to define all routes, please use the Auto Routing (Improved).
// Set `$autoRoutesImproved` to true in `app/Config/Feature.php` and set the following to true.
// $routes->setAutoRoute(false);

/*
 * --------------------------------------------------------------------
 * Route Definitions
 * --------------------------------------------------------------------
 */

// We get a performance increase by specifying the default
// route since we don't have to scan directories.
//$routes->get('/', 'AuthController::index');
$routes->get('/', 'Home::index');
$routes->get('/health', 'Home::health');
$routes->get('/dashboard', 'DashboardController::index', ['filter' => 'session']);
$routes->get('/dashboard/chat', 'ChatController::index', ['filter' => 'session']);
$routes->post('/dashboard/chat/send', 'ChatController::send', ['filter' => 'session']);
$routes->post('/dashboard/chat/clear', 'ChatController::clear', ['filter' => 'session']);
$routes->get('/dashboard/chat/info', 'ChatController::info', ['filter' => 'session']);
$routes->get('/dashboard/graph', 'Graph::index', ['filter' => 'session']);
$routes->get('/dashboard/graph/category-details', 'Graph::categoryDetails', ['filter' => 'session']);
$routes->addRedirect('/dashboard/search', '/dashboard/transactions');
$routes->post('/dashboard/rescan', 'Home::rescan', ['filter' => 'session']);
$routes->post('/dashboard/rescan/all', 'Home::rescanAll', ['filter' => 'session']);
$routes->get('/dashboard/rescan/progress', 'Home::progress', ['filter' => 'session']);
$routes->post('/dashboard/device/link', 'DashboardController::linkDevice', ['filter' => 'session']);
$routes->get('/dashboard/analyse', 'AnalysisCallbackController::index', ['filter' => 'session']);
$routes->post('/dashboard/analyse/rule', 'AnalysisCallbackController::saveRule', ['filter' => 'session']);
$routes->get('/dashboard/errors/test/(:num)', 'Debug::error/$1');
$routes->get('/dashboard/transactions', 'Transactions::index', ['filter' => 'session']);
$routes->get('/dashboard/transactions/export', 'Transactions::export', ['filter' => 'session']);
    $routes->get('/dashboard/history', 'HistoryController::index', ['filter' => 'session']);
    $routes->get('/dashboard/history/jobs', 'HistoryController::jobs', ['filter' => 'session']);
    $routes->post('/dashboard/history/jobs/stop', 'HistoryController::stopJob', ['filter' => 'session']);
$routes->get('/dashboard/info', 'Info::index', ['filter' => 'session']);
$routes->post('/dashboard/info/generate-token', 'Info::generateToken', ['filter' => 'session']);
$routes->post('/dashboard/info/revoke-token', 'Info::revokeToken', ['filter' => 'session']);

// Devices
$routes->get('/dashboard/devices', 'Devices::index', ['filter' => 'session']);
$routes->get('/dashboard/devices/detail/(:any)', 'Devices::detail/$1', ['filter' => 'session']);
$routes->get('/dashboard/devices/tokens', 'Devices::tokenUsage', ['filter' => 'session']);
$routes->get('/dashboard/devices/activity', 'Devices::apiActivity', ['filter' => 'session']);

// Settings
$routes->get('/dashboard/settings', 'Settings::index', ['filter' => 'session']);
$routes->get('/dashboard/settings/profile', 'Settings::profile', ['filter' => 'session']);
$routes->post('/dashboard/settings/profile/save', 'Settings::saveProfile', ['filter' => 'session']);
$routes->get('/dashboard/settings/notifications', 'Settings::notifications', ['filter' => 'session']);
$routes->post('/dashboard/settings/notifications/save', 'Settings::saveNotifications', ['filter' => 'session']);
$routes->get('/dashboard/settings/preferences', 'Settings::preferences', ['filter' => 'session']);
$routes->post('/dashboard/settings/preferences/save', 'Settings::savePreferences', ['filter' => 'session']);
$routes->get('/dashboard/settings/security', 'Settings::security', ['filter' => 'session']);
$routes->post('/dashboard/settings/security/revoke-device', 'Settings::revokeDevice', ['filter' => 'session']);
$routes->get('/dashboard/settings/data', 'Settings::data', ['filter' => 'session']);
$routes->post('/dashboard/settings/data/purge', 'Settings::purgeData', ['filter' => 'session']);
$routes->post('/dashboard/settings/data/delete-upload', 'Settings::deleteUpload', ['filter' => 'session']);
    $routes->post('/dashboard/settings/data/delete-non-finance', 'Settings::deleteNonFinance', ['filter' => 'session']);
$routes->get('/dashboard/settings/export/csv', 'Settings::exportCsv', ['filter' => 'session']);
$routes->get('/dashboard/settings/export/json', 'Settings::exportJson', ['filter' => 'session']);
$routes->get('/dashboard/settings/data/export-settings', 'Settings::exportSettings', ['filter' => 'session']);
$routes->post('/dashboard/settings/data/import-settings', 'Settings::importSettings', ['filter' => 'session']);
$routes->get('/dashboard/settings/data/export-rules', 'Settings::exportCategoryRules', ['filter' => 'session']);
$routes->post('/dashboard/settings/data/import-rules', 'Settings::importCategoryRules', ['filter' => 'session']);

// Tags
$routes->get('/dashboard/settings/tags', 'Settings::tags', ['filter' => 'session']);
$routes->post('/dashboard/settings/tags/save', 'Settings::saveTag', ['filter' => 'session']);
$routes->post('/dashboard/settings/tags/delete', 'Settings::deleteTag', ['filter' => 'session']);

// Blocklist
$routes->get('/dashboard/blocklist', 'Blocklist::status', ['filter' => 'session']);
$routes->get('/dashboard/blocklist/status', 'Blocklist::status', ['filter' => 'session']);
$routes->get('/dashboard/blocklist/blocked', 'Blocklist::blocked', ['filter' => 'session']);
$routes->get('/dashboard/blocklist/allowed', 'Blocklist::allowed', ['filter' => 'session']);
$routes->get('/dashboard/blocklist/unknown', 'Blocklist::unknown', ['filter' => 'session']);
$routes->post('/dashboard/blocklist/block', 'Blocklist::block', ['filter' => 'session']);
$routes->post('/dashboard/blocklist/unblock', 'Blocklist::unblock', ['filter' => 'session']);
$routes->post('/dashboard/blocklist/allow', 'Blocklist::allow', ['filter' => 'session']);
$routes->post('/dashboard/blocklist/bulk-block', 'Blocklist::bulkBlock', ['filter' => 'session']);
$routes->post('/dashboard/blocklist/bulk-unblock', 'Blocklist::bulkUnblock', ['filter' => 'session']);
$routes->post('/dashboard/blocklist/bulk-allow', 'Blocklist::bulkAllow', ['filter' => 'session']);
$routes->post('/dashboard/blocklist/delete-unwanted-sms', 'Blocklist::deleteUnwantedSms', ['filter' => 'session']);

// Notes (AJAX)
$routes->post('/dashboard/settings/notes/save', 'Settings::saveNote', ['filter' => 'session']);
$routes->get('/dashboard/settings/notes/get/(:num)', 'Settings::getNote/$1', ['filter' => 'session']);

// Spending Goals
$routes->get('/dashboard/settings/goals', 'Settings::goals', ['filter' => 'session']);
$routes->post('/dashboard/settings/goals/save', 'Settings::saveGoal', ['filter' => 'session']);
$routes->post('/dashboard/settings/goals/delete', 'Settings::deleteGoal', ['filter' => 'session']);

// Recurring Transactions
$routes->get('/dashboard/settings/recurring', 'Settings::recurring', ['filter' => 'session']);
$routes->post('/dashboard/settings/recurring/save', 'Settings::saveRecurring', ['filter' => 'session']);
$routes->post('/dashboard/settings/recurring/delete', 'Settings::deleteRecurring', ['filter' => 'session']);

// Report Scheduling
$routes->get('/dashboard/settings/report-schedule', 'Settings::reportSchedule', ['filter' => 'session']);
$routes->post('/dashboard/settings/report-schedule/save', 'Settings::saveReportSchedule', ['filter' => 'session']);

// Analytics & Insights
$routes->get('/dashboard/reports', 'ReportsController::index', ['filter' => 'session']);
$routes->get('/dashboard/reports/print', 'ReportsController::printView', ['filter' => 'session']);
$routes->get('/dashboard/budget', 'Budget::index', ['filter' => 'session']);
$routes->match(['get', 'post'], '/dashboard/budget/save', 'Budget::save', ['filter' => 'session']);
$routes->match(['get', 'post'], '/dashboard/budget/delete', 'Budget::delete', ['filter' => 'session']);

$routes->get('/home', 'Home::index');
$routes->get('/android-app', 'Home::androidApp');
$routes->get('/ml-backend', 'Home::mlBackend');
$routes->get('/setup', 'Home::setup');
$routes->get('/faq', 'Home::faq');

$routes->group('auth', function ($routes) {
    $routes->add('login', 'AuthController::login');
    $routes->add('register', 'AuthController::register');
    $routes->add('search', 'AuthController::user_info');
    $routes->add('logout', 'AuthController::user_logout');
});

$routes->group('api/v1', ['namespace' => 'App\Controllers\Api\V1'], function ($routes) {
    // Ecosystem Release Info
    $routes->get('system/version', '\App\Controllers\Home::systemVersion');

    // Auth & Core Device Endpoints
    $routes->post('auth/login', 'AuthController::login');
    $routes->post('auth/register', 'AuthController::register');
    $routes->post('auth/verify', 'AuthController::verifyToken');
    $routes->post('upload', 'UploadsController::upload');
    $routes->post('device', 'AuthController::devicePrint');

    // Sync & Scan Process
    $routes->post('process/scan', 'UploadsController::scanTrigger');
    $routes->post('process/progress', 'UploadsController::scanProgress');

    // Financial Aggregations & List Endpoints (Dual GET/POST for REST compliance & mobile backward compatibility)
    $routes->match(['get', 'post'], 'financial/overview', 'AnalyticsController::financialOverview');
    $routes->match(['get', 'post'], 'financial/categories', 'AnalyticsController::transactionsByCategory');
    $routes->match(['get', 'post'], 'financial/senders', 'AnalyticsController::senderProfiles');
    $routes->match(['get', 'post'], 'financial/uploads', 'UploadsController::uploadListing');
    $routes->match(['get', 'post'], 'financial/uploads-count', 'UploadsController::lootUploadedCount');
    $routes->match(['get', 'post'], 'financial/uploads-summary', 'UploadsController::uploadSummaryCalculation');

    // Financial Analyst Endpoints (Dual GET/POST)
    $routes->match(['get', 'post'], 'financial/health', 'AnalyticsController::financialHealth');
    $routes->match(['get', 'post'], 'financial/alerts', 'AnalyticsController::financialAlerts');
    $routes->match(['get', 'post'], 'financial/recurring', 'AnalyticsController::financialRecurring');
    $routes->match(['get', 'post'], 'financial/trends', 'AnalyticsController::financialTrends');
    $routes->match(['get', 'post'], 'financial/insights', 'AnalyticsController::financialInsights');

    // User Settings & Utilities
    $routes->match(['get', 'post'], 'settings/profile', 'SettingsController::profile');
    $routes->post('settings/profile/update', 'SettingsController::updateProfile');
    $routes->match(['get', 'post'], 'settings/preferences', 'SettingsController::preferences');
    $routes->post('settings/preferences/save', 'SettingsController::savePreferences');
    $routes->post('settings/delete-account', 'UploadsController::deleteAccount');
    $routes->post('settings/delete-data', 'UploadsController::deleteData');
    
    $routes->get('notes/get/(:num)', 'NotesController::noteGet/$1');
    $routes->post('notes/save', 'NotesController::noteSave');
    $routes->get('export/(:any)', 'UploadsController::export/$1');

    // AI Chat & Assistant (Mobile Gateway)
    $routes->post('chat', 'ChatController::apiChat');
    $routes->get('chat/info', 'ChatController::apiInfo');
    $routes->get('chat/history', 'ChatController::history');
    $routes->delete('chat/history', 'ChatController::clearHistory');
});


// Admin routes
$routes->group('admin', ['filter' => ['session', 'admin']], function ($routes) {
    $routes->get('/', 'Admin\Overview::index');
    $routes->get('', 'Admin\Overview::index');

    // ML Backend
    $routes->get('ml', 'Admin\Ml::index');
    $routes->get('ml/models', 'Admin\Ml::models');
    $routes->get('ml/config', 'Admin\Ml::config');
    $routes->get('ml/test', 'Admin\Ml::test');
    $routes->get('ml/prompts', 'Admin\Ml::prompts');
    $routes->post('ml/prompts/save', 'Admin\Ml::promptSave');
    $routes->post('ml/prompts/activate', 'Admin\Ml::promptActivate');
    $routes->post('ml/prompts/delete', 'Admin\Ml::promptDelete');
    $routes->get('ml/senders', 'Admin\Ml::senders');
    $routes->post('ml/config/save', 'Admin\Ml::saveConfig');
    $routes->post('ml/config/test', 'Admin\Ml::testConnection');
    $routes->post('ml/config/test-url', 'Admin\Ml::testBackendUrl');
    $routes->post('ml/models/activate', 'Admin\Ml::activateModel');
    $routes->post('ml/models/upload', 'Admin\Ml::uploadModel');
    $routes->post('ml/models/download', 'Admin\Ml::modelDownload');
    $routes->get('ml/models/download-status/(:segment)', 'Admin\Ml::modelDownloadStatus/$1');
    $routes->post('ml/models/delete', 'Admin\Ml::deleteModel');
    $routes->post('ml/config/test-local', 'Admin\Ml::testLocal');
    $routes->post('ml/test/run', 'Admin\Ml::runTest');
    $routes->post('ml/senders/set-finance', 'Admin\Ml::setSenderFinance');
    $routes->get('ml/allowed', 'Admin\Ml::allowed');
    $routes->get('ml/jobs', 'Admin\Ml::jobs');
    $routes->post('ml/jobs/stop', 'Admin\Ml::stopJob');
    $routes->post('ml/jobs/auto', 'Admin\Ml::toggleAuto');
    $routes->post('ml/allowed/add', 'Admin\Ml::allowedAdd');
    $routes->post('ml/allowed/remove', 'Admin\Ml::allowedRemove');
    $routes->post('ml/allowed/reset', 'Admin\Ml::allowedReset');
    $routes->post('ml/allowed/bulk-categorize', 'Admin\Ml::allowedBulkCategorize');
    $routes->post('ml/allowed/bulk-remove', 'Admin\Ml::allowedBulkRemove');
    $routes->post('ml/allowed/seed', 'Admin\Ml::allowedSeed');



    // Cron jobs
    $routes->get('crons', 'Admin\Crons::index');
    $routes->post('crons/save', 'Admin\Crons::save');
    $routes->post('crons/delete', 'Admin\Crons::delete');
    $routes->post('crons/toggle', 'Admin\Crons::toggle');
    $routes->post('crons/run', 'Admin\Crons::run');
    $routes->post('crons/output', 'Admin\Crons::output');
    $routes->post('crons/history', 'Admin\Crons::history');
    $routes->get('crons/daemon-status', 'Admin\Crons::daemonStatus');
    $routes->get('crons/daemon-log', 'Admin\Crons::daemonLog');

    // User management
    $routes->get('users', 'Admin\Users::index');
    $routes->post('users/toggle', 'Admin\Users::toggle');
    $routes->post('users/change-group', 'Admin\Users::changeGroup');
    $routes->post('users/delete', 'Admin\Users::delete');

    // Devices
    $routes->get('devices', 'Admin\Devices::index');
    $routes->get('devices/detail/(:any)', 'Admin\Devices::detail/$1');

    // Email notifications
    $routes->get('notifications', 'Admin\Notifications::index');
    $routes->post('notifications/save-config', 'Admin\Notifications::saveConfig');
    $routes->post('notifications/send-test-email', 'Admin\Notifications::sendTestEmail');
    $routes->post('notifications/save-triggers', 'Admin\Notifications::saveTriggers');

    // System Utilities
    $routes->get('system', 'Admin\DbInfo::index');
    $routes->get('system/db-info', 'Admin\DbInfo::index');

    $routes->get('system/logs', 'Admin\Logs::index');
    $routes->get('system/logs/view/(:any)', 'Admin\Logs::view/$1');
    $routes->get('system/logs/download/(:any)', 'Admin\Logs::download/$1');
    $routes->post('system/logs/delete', 'Admin\Logs::delete');
    $routes->post('system/logs/delete-all', 'Admin\Logs::deleteAll');

    $routes->get('system/maintenance', 'Admin\Maintenance::index');
    $routes->post('system/maintenance/clear-cache', 'Admin\Maintenance::clearCache');
    $routes->post('system/maintenance/clear-sessions', 'Admin\Maintenance::clearSessions');
    $routes->post('system/maintenance/clean-expired-sessions', 'Admin\Maintenance::cleanExpiredSessions');
    $routes->post('system/maintenance/save-retention', 'Admin\Maintenance::saveRetention');

    $routes->get('system/backup', 'Admin\Backup::index');
    $routes->get('system/backup/download', 'Admin\Backup::download');
    $routes->get('system/backup/scheduled', 'Admin\Backup::scheduled');
    $routes->get('system/backup/download-file/(:any)', 'Admin\Backup::downloadFile/$1');
    $routes->post('system/backup/delete', 'Admin\Backup::delete');

    // Audit Trail
    $routes->get('audit', 'Admin\Audit::index');
    $routes->get('audit/export', 'Admin\Audit::export');

    // Container Telemetry
    $routes->get('telemetry', 'Admin\Telemetry::index');
    $routes->get('telemetry/live', 'Admin\Telemetry::live');

    // Migration runner (protected by MIGRATE_KEY env var)
    $routes->get('migrate', 'Admin\Migrate::index');
    $routes->get('migrate/status', 'Admin\Migrate::status');
});

// Explicit Shield Authentication Routes
$routes->group('', ['namespace' => '\CodeIgniter\Shield\Controllers'], static function ($routes) {
    // Login/out
    $routes->get('login', 'LoginController::loginView', ['as' => 'login']);
    $routes->post('login', 'LoginController::loginAction');
    $routes->get('logout', 'LoginController::logoutAction', ['as' => 'logout']);
    
    // Registration
    $routes->get('register', 'RegisterController::registerView', ['as' => 'register']);
    $routes->post('register', 'RegisterController::registerAction');
    
    // Auth Actions (2FA, Email Activation)
    $routes->get('auth/a/show', 'ActionController::show', ['as' => 'auth-action-show']);
    $routes->post('auth/a/handle', 'ActionController::handle', ['as' => 'auth-action-handle']);
    $routes->post('auth/a/verify', 'ActionController::verify', ['as' => 'auth-action-verify']);
    
    // Forgot Password / Magic Link (support both /magic-link and /login/magic-link)
    $routes->get('magic-link', 'MagicLinkController::loginView', ['as' => 'magic-link']);
    $routes->get('login/magic-link', 'MagicLinkController::loginView');
    $routes->post('magic-link', 'MagicLinkController::loginAction');
    $routes->post('login/magic-link', 'MagicLinkController::loginAction');
    $routes->get('magic-link/verify', 'MagicLinkController::verify', ['as' => 'verify-magic-link']);
    $routes->get('login/verify-magic-link', 'MagicLinkController::verify');
});

/*
 * --------------------------------------------------------------------
 * Additional Routing
 * --------------------------------------------------------------------
 *
 * There will often be times that you need additional routing and you
 * need it to be able to override any defaults in this file. Environment
 * based routes is one such time. require() additional route files here
 * to make that happen.
 *
 * You will have access to the $routes object within that file without
 * needing to reload it.
 */
if (is_file(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php')) {
    require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}
