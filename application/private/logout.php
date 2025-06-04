<?php
// application/private/logout.php

// Solo redirect alla pagina di login, senza distruggere alcuna sessione
header('Location: index.php?page=login');
exit;
?>