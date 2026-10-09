<?php
/**
 * Router for PHP's built-in web server (local testing only):
 *
 *     php -S localhost:8000 router.php
 *     php -S 0.0.0.0:8000 router.php        <- share on your Wi-Fi
 *
 * The built-in server ignores .htaccess, so without this file anyone who can
 * reach the server could download data/powerforge.sqlite (every password hash)
 * or config/db_credentials.php. This router answers 403 for those paths and
 * lets everything else through. It is NOT used by Apache/Nginx/shared hosting.
 */
$path = rawurldecode((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));

$privateFolder = preg_match('#(^|/)(config|includes|data|tools|deploy)(/|$)#i', $path);
$hiddenFile    = preg_match('#(^|/)\.[^/]#', $path);
$privateType   = preg_match('#\.(sqlite|sqlite-wal|sqlite-shm|sql|md|log|bak|example)(\.php)?$#i', $path)
              || preg_match('#(^|/)(router|\.htaccess|\.gitignore)(\.php)?$#i', $path);

if ($privateFolder || $hiddenFile || $privateType) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "403 Forbidden\n";
    return true;
}
return false;   // let the built-in server handle it normally
