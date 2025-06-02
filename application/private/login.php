<?php
require '../include/dbms.inc.php';
require '../logic/Fisioterapisti.php';
session_start();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id = Fisioterapisti::login($_POST['email'],$_POST['password']);
    if ($id) {
        $_SESSION['fisio']=$id;
        header('Location: dashboard.php');
        exit;
    } else {
        $err = 'Credenziali errate';
    }
}
require '../include/template2.inc.php';
$tpl = new Template('privato_theme/login');
if (isset($err)) $tpl->setContent('error',$err);
$tpl->parse();
