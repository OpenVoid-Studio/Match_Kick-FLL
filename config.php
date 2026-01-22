<?php
session_start();

/*
|--------------------------------------------------------------------------
| Load Environment Variables
|--------------------------------------------------------------------------
| Keeps credentials out of the repo. Your .env file should contain:
| DB_HOST=ip_address_or_hostname
| DB_NAME=database
| DB_USER=user
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
