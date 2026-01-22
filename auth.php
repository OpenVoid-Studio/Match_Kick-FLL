<?php
session_start();
//check if logged in
if (!isset($_SESSION["user"])) {
    header("Location: /admin");
    exit;
}
?>