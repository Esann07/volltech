<?php
/**
 * Production configuration used by the Render Docker image.
 *
 * VOLTTECH_SQLITE_PATH can override the path if the Render disk is mounted
 * elsewhere. This file contains no credentials and is safe to commit.
 */
return [
    'debug'       => false,
    'driver'      => 'sqlite',
    'sqlite_path' => getenv('VOLTTECH_SQLITE_PATH') ?: '/var/lib/volttech/powerforge.sqlite',
    'force_https' => true,
    'trust_proxy' => true,
];
