<?php
// application/index.php

// 1. Includo connessione, template engine e logica di base
require __DIR__ . '/include/dbms.inc.php';
require __DIR__ . '/include/template2.inc.php';
require __DIR__ . '/logic/Fisioterapisti.php';

// 2. Includo login, prenotazioni, certificazioni, media, recensioni, contatti
require __DIR__ . '/private/login.php';
require __DIR__ . '/public/prenotazioni.php';
require __DIR__ . '/private/certificazioni.php';
require __DIR__ . '/private/media.php';
require __DIR__ . '/public/recensioni.php';
require __DIR__ . '/public/contatti.php';

// 3. INCLUSIONE DELLE DUE NUOVE FUNZIONI PER I FISIOTERAPISTI
require __DIR__ . '/public/fisioterapisti.php';
require __DIR__ . '/public/dettagli_fisioterapista.php';

require __DIR__ . '/public/avvisi.php';
require __DIR__ . '/public/footer_news.php';

session_start();

// 4. Definizione delle pagine
$publicPages  = ['index', 'avvisi', 'chisiamo', 'contatti',
                 'form_prenotazione', 'recensioni', 'news-detail', 'fisioterapisti'];
$privatePages = ['dashboard', 'appuntamenti', 'disponibilita',
                 'servizi', 'richieste', 'certificazioni', 'media',
                 'messaggi', 'notifiche', 'profilo', 'login', 'logout'];

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
    // 1) Prenotazioni
    // ──────────────────────────────────────────────────────────────
    $showForm = false;
    $bodyHtml = '';
    handlePrenotazioneForm($showForm, $bodyHtml, $message);
    if ($showForm) {
        $base = 'dtml/2098_health/frame';
        $main = new Template($base);
        $footerHtml = getFooterNews(5);
    $main->setContent('footer_news', $footerHtml);
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
    $showCert = false;
    $bodyCert = '';
    handleCertificazioni($showCert, $bodyCert);
    if ($showCert) {
        $base = 'dtml/webarch/frame';
        $main = new Template($base);
        $main->setContent('body', $bodyCert);
        $main->close();
        exit;
    }

    // ──────────────────────────────────────────────────────────────
    // 4) Media (area privata)
    // ──────────────────────────────────────────────────────────────
    $showMedia    = false;
    $bodyMedia    = '';
    handleMedia($showMedia, $bodyMedia);
    if ($showMedia) {
        $base = 'dtml/webarch/frame';
        $main = new Template($base);
        $main->setContent('body', $bodyMedia);
        $main->close();
        exit;
    }

    // ──────────────────────────────────────────────────────────────
    // 5) Recensioni (area pubblica)
    // ──────────────────────────────────────────────────────────────
    $showRec     = false;
    $bodyRec     = '';
    handleRecensioni($showRec, $bodyRec);
    if ($showRec) {
        $base = 'dtml/2098_health/frame';
        $main = new Template($base);
        $footerHtml = getFooterNews(5);
    $main->setContent('footer_news', $footerHtml);
        $main->setContent('body', $bodyRec);
        $main->close();
        exit;
    }

    // ──────────────────────────────────────────────────────────────
    // 6) Contatti (area pubblica)
    // ──────────────────────────────────────────────────────────────
    $showContact     = false;
    $bodyContact     = '';
    handleContatti($showContact, $bodyContact);
    if ($showContact) {
        $base = 'dtml/2098_health/frame';
        $main = new Template($base);
        $footerHtml = getFooterNews(5);
    $main->setContent('footer_news', $footerHtml);
        $main->setContent('body', $bodyContact);
        $main->close();
        exit;
    }

    // ──────────────────────────────────────────────────────────────
    // 7) Lista Fisioterapisti (area pubblica)
    // ──────────────────────────────────────────────────────────────
    $showFisioList   = false;
    $bodyFisioList   = '';
    handleFisioterapisti($showFisioList, $bodyFisioList);
    if ($showFisioList) {
        $base = 'dtml/2098_health/frame';
        $main = new Template($base);
        
        $footerHtml = getFooterNews(5);
    $main->setContent('footer_news', $footerHtml);
        
        $main->setContent('body', $bodyFisioList);
        $main->close();
        exit;
    }

    // ──────────────────────────────────────────────────────────────
    // 8) Dettaglio Fisioterapista (area pubblica)
    // ──────────────────────────────────────────────────────────────
    $showFisioDetail = false;
    $bodyFisioDetail = '';
    handleDettagliFisio($showFisioDetail, $bodyFisioDetail);
    if ($showFisioDetail) {
        $base = 'dtml/2098_health/frame';
        $main = new Template($base);
        $main->setContent('body', $bodyFisioDetail);
        $main->close();
        exit;
    }
    
    // ──────────────────────────────────────────────────────────────
//  AVVISI / NEWS (AREA PUBBLICA)
// ──────────────────────────────────────────────────────────────
$showAvvisi     = false;
$bodyHtmlAvvisi = '';
handleAvvisi($showAvvisi, $bodyHtmlAvvisi);
if ($showAvvisi) {
    // Se siamo in avvisi, usiamo il frame pubblico e iniettiamo avvisi
    $base = 'dtml/2098_health/frame';
    $main = new Template($base);
    $main->setContent('body', $bodyHtmlAvvisi);

    // Prima di chiudere, inietto le ultime news nel footer:
    $footerHtml = getFooterNews(5);
    $main->setContent('footer_news', $footerHtml);

    $main->close();
    exit;
}

    // ──────────────────────────────────────────────────────────────
    // 9) (Opzionale) Protezione area privata
    // ──────────────────────────────────────────────────────────────
    /*
    if (in_array($page, $privatePages) && empty($_SESSION['fisio'])) {
        header('Location: index.php?page=login');
        exit;
    }
    */

    // ──────────────────────────────────────────────────────────────
    // 10) Routing “standard” per tutte le altre pagine
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
// 11) Rendering generale per tutte le altre pagine
// ──────────────────────────────────────────────────────────────
$main = new Template($base);
if (!empty($body) && file_exists(__DIR__ . "/$body.html")) {
    $main->setContent('body', (new Template($body))->get());
}

$footerHtml = getFooterNews(5);
$main->setContent('footer_news', $footerHtml);

$main->close();
?>
