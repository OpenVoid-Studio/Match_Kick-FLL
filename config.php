<?php
session_start();

/*
|--------------------------------------------------------------------------
| Allowed Domains
|--------------------------------------------------------------------------
| This prevents the app from being accessed through unexpected hostnames.
| You can add staging domains here if needed.
*/
$allowedDomains = ['fllapp.glitchedreality.com', 'fllapp2.glitchedreality.com'];

if (!in_array($_SERVER['HTTP_HOST'], $allowedDomains, true)) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access forbidden.');
}

/*
|--------------------------------------------------------------------------
| Load Environment Variables
|--------------------------------------------------------------------------
| Keeps credentials out of the repo. Your .env file should contain:
| DB_HOST=localhost
| DB_NAME=fllapp
| DB_USER=andy
| DB_PASS=yourpassword
*/
$env = parse_ini_file(__DIR__ . '/.env');
if ($env === false) {
    exit('Environment configuration missing.');
}

$db_host = $env['DB_HOST'];
$db_name = $env['DB_NAME'];
$db_user = $env['DB_USER'];
$db_pass = $env['DB_PASS'];

/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    exit('Database connection failed.');
}

$conn->set_charset('utf8mb4');

if(isset($_SESSION["pw_change"]) && $_SESSION["pw_change"] === true) {
    $passwordFiles = ['/admin/change-password.php', '/admin/change-password', '/admin/logout.php', '/admin/logout'];
    $currentFile = $_SERVER['PHP_SELF'];
    if(!in_array($currentFile, $passwordFiles)) {
        header("Location: /admin/change-password");
        exit;
    }
}
?>
