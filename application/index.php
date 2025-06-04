<?php
// application/index.php

// 1. Includo connessione, template engine e logica di base
require __DIR__ . '/include/dbms.inc.php';
require __DIR__ . '/include/template2.inc.php';
require __DIR__ . '/logic/Fisioterapisti.php';

// 2. Includo login, prenotazioni, certificazioni, media e recensioni (funzioni globali)
require __DIR__ . '/private/login.php';
require __DIR__ . '/public/prenotazioni.php';
require __DIR__ . '/private/certificazioni.php';
require __DIR__ . '/private/media.php';
require __DIR__ . '/public/recensioni.php';

// 3. INCLUIDO ORA anche il gestore CONTATTI
require __DIR__ . '/public/contatti.php';

session_start();

// 4. Definizione delle pagine
$publicPages  = ['index', 'avvisi', 'chisiamo', 'contatti',
                 'form_prenotazione', 'recensioni', 'news-detail', 'fisioterapisti'];
$privatePages = ['dashboard', 'appuntamenti', 'disponibilita',
                 'servizi', 'richieste', 'certificazioni', 'media',
                 'messaggi', 'notifiche', 'profilo', 'login', 'logout'];

try {
    $page = $_GET['page'] ?? 'index';

    // … (gestione logout, prenotazioni, login, certificazioni, media, recensioni) …

    // ──────────────────────────────────────────────────────────────
    // 5) CONTATTI (AREA PUBBLICA)
    // ──────────────────────────────────────────────────────────────
    $showContact      = false;
    $bodyHtmlContact  = '';
    handleContatti($showContact, $bodyHtmlContact);
    if ($showContact) {
        // Carico il “frame” pubblico e inietto il contenuto di contatti
        $base = 'dtml/2098_health/frame';
        $main = new Template($base);
        $main->setContent('body', $bodyHtmlContact);
        $main->close();
        exit;
    }

    // ──────────────────────────────────────────────────────────────
    // 6) (Opzionale) Protezione area privata
    // ──────────────────────────────────────────────────────────────
    /*
    if (in_array($page, $privatePages) && empty($_SESSION['fisio'])) {
        header('Location: index.php?page=login');
        exit;
    }
    */

    // ──────────────────────────────────────────────────────────────
    // 7) Routing “standard” per tutte le altre pagine
    // ──────────────────────────────────────────────────────────────
    if ($page === 'login') {
        $base = 'dtml/webarch/login';
        $body = null;
    } elseif (in_array($page, $privatePages)) {
        $base = 'dtml/webarch/frame';
        $body = "dtml/webarch/$page";
    } else {
        $base = 'dtml/2098_health/frame';
        $body = "dtml/2098_health/$page";
    }

    // fallback 404 se manca il file
    if (!empty($body) && !file_exists(__DIR__ . "/$body.html")) {
        $body = 'dtml/2098_health/404';
    }

} catch (Exception $e) {
    $base = 'dtml/2098_health/frame';
    $body = 'dtml/2098_health/500';
}

// ──────────────────────────────────────────────────────────────
// 8) Rendering generale per tutte le altre pagine
// ──────────────────────────────────────────────────────────────
$main = new Template($base);
if (!empty($body) && file_exists(__DIR__ . "/$body.html")) {
    $main->setContent('body', (new Template($body))->get());
}
$main->close();
?>
