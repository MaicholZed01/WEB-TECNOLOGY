<?php
    
    require __DIR__.'/include/template2.inc.php';
    //session_start();

    $publicPages  = ['index', 'avvisi', 'chisiamo', 'contatti', 'form_prenotazione', 'news-detail', 'fisioterapisti'];
    $privatePages = ['dashboard','appuntamenti','disponibilita','servizi',
                    'media','certificazioni','messaggi','notifiche','profilo', 'login'];

    try {            
        $page = $_GET['page'] ?? 'index';

        /* area privata → se non loggato vai al login 
        if (in_array($page, $privatePages) && empty($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }*/

        /* seleziona il template base */
        /* Se page = login, scegli un layout minimale   */
        if ($page === 'login') {
            $base = 'dtml/webarch/login';        // contiene già il modulo
            $body = null;                        // nessun sotto-template
        } else if (in_array($page, $privatePages)) {
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
    }
    catch (Exception $e) {
        // Log error or handle exception
        $base = 'dtml/2098_health/frame';
        $body = 'dtml/2098_health/500'; // fallback to 404 page
    }

    /* rendering */
    /* Se esiste un sotto-template lo carico, altrimenti solo frame */
    $main = new Template($base);
    if ($body && file_exists(__DIR__."/$body.html")) {
        $main->setContent('body', (new Template($body))->get());
    }
    $main->close();

?>
