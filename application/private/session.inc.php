<?php
// application/private/session.inc.php

session_start();
if (!isset($_SESSION['fisio'])) {
    header('Location: login.php');
    exit;
}
?>