<?php
// application/index.php

// 1. Includo connessione, template e logica
require __DIR__ . '/include/dbms.inc.php';
require __DIR__ . '/include/template2.inc.php';
require __DIR__ . '/logic/Fisioterapisti.php';

// Includo login e prenotazione (funzioni globali)
require __DIR__ . '/private/login.php';
require __DIR__ . '/public/prenotazioni.php';  // se serve gestire prenotazioni

session_start();

// Definizione delle pagine
$publicPages  = ['index', 'avvisi', 'chisiamo', 'contatti',
                 'form_prenotazione', 'recensioni', 'news-detail', 'fisioterapisti'];
$privatePages = ['dashboard', 'appuntamenti', 'disponibilita',
                 'servizi', 'richieste', 'media',
                 'certificazioni', 'messaggi', 'notifiche',
                 'profilo', 'login'];

try {
    $page = $_GET['page'] ?? 'index';
	
    // Gestione logout (senza sessione)
if ($page === 'logout') {
    require __DIR__ . '/private/logout.php';
}

    // ──────────────────────────────────────────────────────────────
    // 1) Gestione del form di prenotazione (delegata a prenotazioni.php)
    // ──────────────────────────────────────────────────────────────
    $showForm = false;
    $bodyHtml = '';
    $message  = '';
    handlePrenotazioneForm($showForm, $bodyHtml, $message);
    if ($showForm) {
        $base = 'dtml/2098_health/frame';
        $main = new Template($base);
        $main->setContent('body', $bodyHtml);
        $main->close();
        exit;
    }

    // ──────────────────────────────────────────────────────────────
    // 2) Gestione del login (GET o POST su process_login)
    // ──────────────────────────────────────────────────────────────
    $errorLogin = null;
    if ($page === 'process_login') {
        $errorLogin = handleLogin();
    }
    if ($page === 'login' || $page === 'process_login') {
        $base = 'dtml/webarch/login';
        $main = new Template($base);
        // Inietto messaggio di errore (se non null)
        $main->setContent('error_login', $errorLogin ?? '');
        $main->close();
        exit;
    }

    // ──────────────────────────────────────────────────────────────
    // 3) Area privata protetta (se vuoi abilitare)
    // ──────────────────────────────────────────────────────────────
    /*
    if (in_array($page, $privatePages) && empty($_SESSION['fisio'])) {
        header('Location: index.php?page=login');
        exit;
    }
    */

    // ──────────────────────────────────────────────────────────────
    // 4) Routing “standard” per tutte le altre pagine
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
    if ($body && !file_exists(__DIR__ . "/$body.html")) {
        $body = 'dtml/2098_health/404';
    }

} catch (Exception $e) {
    $base = 'dtml/2098_health/frame';
    $body = 'dtml/2098_health/500';
}

// ──────────────────────────────────────────────────────────────
// 5) Rendering generale per tutte le altre pagine
// ──────────────────────────────────────────────────────────────
$main = new Template($base);
if ($body && file_exists(__DIR__ . "/$body.html")) {
    $main->setContent('body', (new Template($body))->get());
}
$main->close();
?>