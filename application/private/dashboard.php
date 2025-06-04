<?php
require '../include/session.inc.php';
require '../include/template2.inc.php';
$tpl = new Template('privato_theme/dashboard');
// Popola eventuali widget (es. notifiche, stats)
// ...
$tpl->parse();
?>