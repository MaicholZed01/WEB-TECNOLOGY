<?php
require '../include/session.inc.php';
require '../include/dbms.inc.php';
require '../include/template2.inc.php';
require '../logic/Fisioterapisti.php';

$id = $_SESSION['fisio'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (Fisioterapisti::updateProfile($id,$_POST)) {
        $msg = 'Profilo aggiornato';
    } else {
        $msg = 'Errore aggiornamento';
    }
}
$tpl = new Template('privato_theme/profilo');
if (isset($msg)) $tpl->setContent('message',$msg);
// carica dati correnti per form
$c = Db::getConnection();
$r = $c->query("SELECT * FROM fisioterapisti WHERE fisioterapista_id=$id")->fetch_assoc();
foreach(['nome','cognome','telefono','bio','tariffa_oraria','anni_esperienza'] as $f) {
    $tpl->setContent($f, $r[$f]);
}
$tpl->parse();
?>