<?php
    /*
    require __DIR__ . "/include/template2.inc.php";

    $page = $_GET['page'] ?? 'index';          // index.html di default
    $layout = 'dtml/2098_health/frame';
    $body   = "dtml/2098_health/$page";        // es. servizi → servizi.html

    // se il file non esiste mostra 404 personalizzato
    if (!file_exists(__DIR__ . "/$body.html")) {
        $body = "dtml/2098_health/404";
    }

    $main = new Template($layout);
    $bodyTpl = new Template($body);
    $main->setContent('body', $bodyTpl->get());
    $main->close();
    */
    require __DIR__.'/include/template2.inc.php';
    //session_start();

    $publicPages  = ['index', 'avvisi', 'chisiamo', 'contatti', 'form_prenotazione', 'news-detail', 'dottori'];
    $privatePages = ['dashboard','appuntamenti','disponibilita','servizi',
                    'media','certificazioni','messaggi','notifiche','profilo','logout', 'login'];

    $page = $_GET['page'] ?? 'index';

    /* area privata → se non loggato vai al login 
    if (in_array($page, $privatePages) && empty($_SESSION['user_id'])) {
        header('Location: index.php?page=login');
        exit;
    }*/

    /* seleziona il template base */
    if (in_array($page, $privatePages)) {
        $base = 'dtml/webarch/frame';
        $body = "dtml/webarch/$page";      // es. dashboard.html
    } else {
        $base = 'dtml/2098_health/frame';
        $body = "dtml/2098_health/$page";  // es. servizi.html
    }

    /* fallback 404 se il file .html manca */
    if (!file_exists(__DIR__."/$body.html")) {
        $body = 'dtml/2098_health/404';    // pagina 404 generica
    }

    /* rendering */
    $main = new Template($base);
    $bodyTpl = new Template($body);
    $main->setContent('body', $bodyTpl->get());
    $main->close();

?>
