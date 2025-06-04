<?php
// application/index.php

require __DIR__ . '/include/dbms.inc.php';
require __DIR__ . '/include/template2.inc.php';
require __DIR__ . '/logic/Fisioterapisti.php';
require __DIR__ . '/logic/Servizi.php';
require __DIR__ . '/logic/FasceDisponibilita.php';
require __DIR__ . '/logic/Richieste.php';
require __DIR__ . '/logic/Appuntamenti.php';

// Includo la logica di prenotazione
require __DIR__ . '/public/prenotazioni.php';

// Le pagine accessibili senza login
$publicPages  = [
    'index', 'avvisi', 'chisiamo', 'contatti',
    'form_prenotazione', 'recensioni', 'news-detail', 'fisioterapisti'
];
// Le pagine dell’area privata (se in futuro reintroduci la sessione)
$privatePages = [
    'dashboard', 'appuntamenti', 'disponibilita',
    'servizi', 'richieste', 'media',
    'certificazioni', 'messaggi', 'notifiche',
    'profilo', 'login'
];

try {
    $page = $_GET['page'] ?? 'index';

    // ──────────────────────────────────────────────────────────────
    // 1) Gestione del form di prenotazione DELEGATA A prenotazioni.php
    // ──────────────────────────────────────────────────────────────
    // Preparo le variabili che la funzione popolerà
    $showForm = false;
    $bodyHtml = '';
    $message  = '';

    // Chiamo la funzione che gestisce GET/POST su form_prenotazione
    handlePrenotazioneForm($showForm, $bodyHtml, $message);

    // Se handlePrenotazioneForm ha deciso di mostrare il form:
    if ($showForm) {
        // Usa il layout pubblico e inietta $bodyHtml
        $base = 'dtml/2098_health/frame';
        $main = new Template($base);
        $main->setContent('body', $bodyHtml);
        $main->close();
        exit;
    }

    // ──────────────────────────────────────────────────────────────
    // 2) Se NON sto mostrando form_prenotazione, proseguo col routing “standard”
    // ──────────────────────────────────────────────────────────────

    // Se avrai in futuro sessioni per area privata, potresti sbloccare questo:
    /*
    if (in_array($page, $privatePages) && empty($_SESSION['user_id'])) {
        header('Location: index.php?page=login');
        exit;
    }
    */

    // seleziona il template base
    if ($page === 'login') {
        $base = 'dtml/webarch/login';  // layout minimale
        $body = null;
    } elseif (in_array($page, $privatePages)) {
        $base = 'dtml/webarch/frame';
        $body = "dtml/webarch/$page";
    } else {
        $base = 'dtml/2098_health/frame';
        $body = "dtml/2098_health/$page";
    }

    // fallback 404 se il file .html manca
    if ($body && !file_exists(__DIR__ . "/$body.html")) {
        $body = 'dtml/2098_health/404';
    }

} catch (Exception $e) {
    $base = 'dtml/2098_health/frame';
    $body = 'dtml/2098_health/500';
}

// ──────────────────────────────────────────────────────────────
// 3) Rendering generale per tutte le altre pagine
// ──────────────────────────────────────────────────────────────
$main = new Template($base);
if ($body && file_exists(__DIR__ . "/$body.html")) {
    $main->setContent('body', (new Template($body))->get());
}
$main->close();
?>
