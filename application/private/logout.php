<?php
// application/private/logout.php

// Redirect alla pagina di login distruggendo la sessione corrente
session_start();
session_destroy();
header('Location: index.php?page=login');
exit();
?>