<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseConfig
{
    /**
     * Configures aliases for Filter classes to
     * make reading things nicer and simpler.
     */
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'admin'         => \App\Filters\AdminFilter::class,
        'user-area'     => \App\Filters\UserAreaFilter::class,
        'maintenance'   => \App\Filters\MaintenanceFilter::class,
        'json'          => \App\Filters\ForceJsonResponseFilter::class,
    ];

    /**
     * List of filter aliases that are always
     * applied before and after every request.
     */
    public array $globals = [
        'before' => [
            'invalidchars',
            'csrf' => ['except' => [
                'api/v1/*',
                'process/*',
                'health',
                'ml-backend',
                'dashboard/chat/send',
                'dashboard/chat/*',
                'dashboard/rescan',
                'dashboard/rescan/*',
                'dashboard/blocklist/*',
                'dashboard/history/*',
            ]],
            'session' => ['except' => ['/', '/home', 'health', 'health/*', 'android-app', 'ml-backend', 'setup', 'faq', 'auth/*', 'process/*', 'login', 'register', 'auth_login', 'auth_register', 'api/v1/*']],
            'maintenance' => ['except' => [
                'login', 'register', 'logout', 'magic-link', 'magic-link/*',
                'auth', 'auth/*',
                'process', 'process/*', 'api', 'api/*', 'api/v1/*',
                'health', 'ml-backend', 'android-app',
            ]],
        ],
        'after' => [
            'toolbar',
            'secureheaders',
        ],
    ];

    /**
     * List of filter aliases that works on a
     * particular HTTP method (GET, POST, etc.).
     *
     * Example:
     * 'post' => ['foo', 'bar']
     *
     * If you use this, you should disable auto-routing because auto-routing
     * permits any HTTP method to access a controller. Accessing the controller
     * with a method you don’t expect could bypass the filter.
     */
    public array $methods = [];

    /**
     * List of filter aliases that should run on any
     * before or after URI patterns.
     *
     * Example:
     * 'isLoggedIn' => ['before' => ['account/*', 'profiles/*']]
     */
    public array $filters = [
        'user-area' => [
            'before' => ['dashboard', 'dashboard/*'],
            'except' => ['dashboard/rescan', 'dashboard/rescan/*'],
        ],
        'json'      => ['before' => ['api/v1/*'], 'after' => ['api/v1/*']],
    ];
}
