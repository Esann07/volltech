<?php
/**
 * Production configuration used by the Render Docker image.
 *
 * VOLTTECH_SQLITE_PATH can override the default location. This file contains
 * no credentials and is safe to commit.
 */
return [
    'debug'       => false,
    'driver'      => 'sqlite',
    'sqlite_path' => getenv('VOLTTECH_SQLITE_PATH') ?: '/var/www/html/data/powerforge.sqlite',
    'force_https' => true,
    'trust_proxy' => true,
];
