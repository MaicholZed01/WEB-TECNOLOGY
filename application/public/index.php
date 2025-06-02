<?php
require '../include/dbms.inc.php';
require '../include/template2.inc.php';
require '../logic/Fisioterapisti.php';
require '../logic/Servizi.php';

$tpl = new Template('pubblico_theme/index');
$tpl->setContent('title','Prenota un appuntamento');
$fisios = Fisioterapisti::listAll();
$options = '';
foreach($fisios as $f) {
    $options .= "<option value=\"{$f['id']}\">{$f['nome']} {$f['cognome']}</option>";
}
$tpl->setContent('fisio_options', $options);

$servs = Servizi::listAll();
$options = '';
foreach($servs as $s) {
    $options .= "<option value=\"{$s['servizio_id']}\">{$s['nome']}</option>";
}
$tpl->setContent('servizio_options', $options);

$tpl->parse();
