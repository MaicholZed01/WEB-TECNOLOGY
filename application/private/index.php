<?php
// application/index.php

// 1. Includo connessione, template engine e logica base
require __DIR__ . '/include/dbms.inc.php';
require __DIR__ . '/include/template2.inc.php';
require __DIR__ . '/logic/Fisioterapisti.php';

// 2. Includo login, prenotazioni e certificazioni (funzioni globali)
require __DIR__ . '/private/login.php';
require __DIR__ . '/public/prenotazioni.php';
require __DIR__ . '/private/certificazioni.php';

session_start();

// 3. Definizione delle pagine
$publicPages  = [
    'index', 'avvisi', 'chisiamo', 'contatti',
    'form_prenotazione', 'recensioni', 'news-detail', 'fisioterapisti'
];
$privatePages = [
    'dashboard', 'appuntamenti', 'disponibilita',
    'servizi', 'richieste', 'media',
    'certificazioni', 'messaggi', 'notifiche',
    'profilo', 'login', 'logout'
];

try {
    $page = $_GET['page'] ?? 'index';

    // ──────────────────────────────────────────────────────────────
    // Gestione logout (semplice redirect a login)
    // ──────────────────────────────────────────────────────────────
    if ($page === 'logout') {
        require __DIR__ . '/private/logout.php';
        exit;
    }

    // ──────────────────────────────────────────────────────────────
    // 1) Prenotazioni (delegata a prenotazioni.php)
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
    // 2) Login (GET o POST su process_login)
    // ──────────────────────────────────────────────────────────────
    $errorLogin = null;
    if ($page === 'process_login') {
        $errorLogin = handleLogin();
    }
    if ($page === 'login' || $page === 'process_login') {
        $base = 'dtml/webarch/login';
        $main = new Template($base);
        $main->setContent('error_login', $errorLogin ?? '');
        $main->close();
        exit;
    }

    // ──────────────────────────────────────────────────────────────
    // 3) Certificazioni (area privata)
    // ──────────────────────────────────────────────────────────────
    $showCert     = false;
    $bodyHtmlCert = '';
    handleCertificazioni($showCert, $bodyHtmlCert);
    if ($showCert) {
        $base = 'dtml/webarch/frame';
        $main = new Template($base);
        $main->setContent('body', $bodyHtmlCert);
        $main->close();
        exit;
    }

    // ──────────────────────────────────────────────────────────────
    // 4) (Opzionale) Protezione area privata
    // ──────────────────────────────────────────────────────────────
    /*
    if (in_array($page, $privatePages) && empty($_SESSION['fisio'])) {
        header('Location: index.php?page=login');
        exit;
    }
    */

    // ──────────────────────────────────────────────────────────────
    // 5) Routing “standard” per tutte le altre pagine
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
// 6) Rendering generale per tutte le altre pagine
// ──────────────────────────────────────────────────────────────
$main = new Template($base);
if (!empty($body) && file_exists(__DIR__ . "/$body.html")) {
    $main->setContent('body', (new Template($body))->get());
}
$main->close();
?>
