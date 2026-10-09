<?php
/**
 * VoltTech - configuration TEMPLATE (contains no real secrets)
 *
 * Copy this file to `db_credentials.php` (same folder) and edit the copy.
 * `db_credentials.php` is listed in .gitignore - never commit it, and never
 * leave a copy where a browser can download it (config/.htaccess already
 * blocks that on Apache; on a real server it is even better to keep the
 * file OUTSIDE the web folder and point the VOLTTECH_CONFIG environment
 * variable at it).
 */

return [
    // true  = show PHP errors on screen (local development ONLY).
    // false = visitors see a friendly error page; details go to the server log.
    // ALWAYS false on a live server.
    'debug' => false,

    // 'sqlite' = one file, zero setup. Fine for a single server and a modest
    //            number of users (class project, small community).
    // 'mysql'  = recommended when many people write at the same time.
    'driver' => 'mysql',

    // SQLite file location. null = <project>/data/powerforge.sqlite
    // On a live server, put the file ABOVE the web folder and use an absolute
    // path, e.g. '/home/youruser/volttech-data/powerforge.sqlite'
    'sqlite_path' => null,

    // Redirect every http:// request to https:// (and send HSTS).
    // Turn this on as soon as your SSL certificate is installed.
    'force_https' => false,

    // Set to true ONLY if the site sits behind a proxy/CDN (Cloudflare,
    // a load balancer...) that sets X-Forwarded-Proto / X-Forwarded-For.
    'trust_proxy' => false,

    // Only used when 'driver' => 'mysql'
    'mysql' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'dbname'  => 'powerforge',
        'user'    => 'Volttech',
        'pass'    => 'pogesiesan',
        'charset' => 'utf8mb4',
    ],
];
