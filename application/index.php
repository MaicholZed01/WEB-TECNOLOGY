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
require __DIR__ . '/public/news-detail.php';
// 2b) ORA include della gestione dei messaggi (lato privato)
require __DIR__ . '/private/messaggi.php';
require __DIR__ . '/private/profilo.php';


require __DIR__ . '/private/macchinari.php';

require __DIR__ . '/private/richieste.php';
require __DIR__ . '/private/fissa_appuntamento.php';

session_start();

// 4. Definizione delle pagine
$publicPages  = ['index', 'avvisi', 'chisiamo', 'contatti', 'dettagli_fisioterapista', 'carriere',
                 'form_prenotazione', 'condizioni', 'privacy', 'recensioni', 'news-detail', 'fisioterapisti'];
$privatePages = ['dashboard', 'appuntamenti', 'disponibilita', 'macchinari', 'registrazione',
                 'servizi', 'richieste', 'certificazioni', 'media', 'avvisi2', 'recupero_password',
                 'messaggi', 'fatturazioni', 'fissa_appuntamento', 'aggiungi_appuntamento', 'profilo', 'login'];

try {
    $page = $_GET['page'] ?? 'index';
if (in_array($page, $privatePages, true)
    && ! in_array($page, ['login','process_login','logout'], true)
    && empty($_SESSION['fisio'])
) {
    header('Location: index.php?page=login');
    exit;
}
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
$message  = '';
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


// ───────────────── Richieste (area privata) ─────────────────
$showReq  = false;
$bodyReq  = '';
$flashReq = '';
handleRichieste($showReq, $bodyReq, $flashReq);
if ($showReq) {
    $base = 'dtml/webarch/frame';
    $main = new Template($base);
    $main->setContent('body', $bodyReq);
    $main->close();
    exit;
}

// ──────────────── Fissa Appuntamento ───────────────────────
$showFix = false;
$bodyFix = '';
$flashFix = '';
handleFissaAppuntamento($showFix, $bodyFix, $flashFix);
if ($showFix) {
    $base = 'dtml/webarch/frame';
    $main = new Template($base);
    $main->setContent('body', $bodyFix);
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
    //  Gestione MESSAGGI (AREA PRIVATA)
    // ──────────────────────────────────────────────────────────────
    $showMessages      = false;
    $bodyHtmlMessages  = '';
    $flashMessage      = '';
    handleMessages($showMessages, $bodyHtmlMessages, $flashMessage);
    if ($showMessages) {
        // Layout “privato” (webarch/frame) e inietto messaggi
        $base = 'dtml/webarch/frame';
        $main = new Template($base);
        $main->setContent('body', $bodyHtmlMessages);
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



$showMach = false; $bodyMach = '';
handleMacchinari($showMach, $bodyMach);
if ($showMach) {
  $base = 'dtml/webarch/frame';
  $main = new Template($base);
  $main->setContent('body', $bodyMach);
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
        $footerHtml = getFooterNews(5);
    	$main->setContent('footer_news', $footerHtml);
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
// AVVISI / NEWS (dettaglio) – pagina “news-detail”
// ──────────────────────────────────────────────────────────────
$showNewsDetail     = false;
$bodyHtmlNewsDetail = '';
handleNewsDetail($showNewsDetail, $bodyHtmlNewsDetail);
if ($showNewsDetail) {
    // Carico il frame pubblico e inietto il template di dettaglio
    $base = 'dtml/2098_health/frame';
    $main = new Template($base);
    $main->setContent('body', $bodyHtmlNewsDetail);

    // Inietto anche le ultime news nel footer (opzionale)
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
$showProf = false;
$bodyProf = '';
handleProfile($showProf, $bodyProf);
if ($showProf) {
    $main = new Template('dtml/webarch/frame');
    $main->setContent('body', $bodyProf);
    $main->close();
    exit;
}
    // ──────────────────────────────────────────────────────────────
    // 10) Routing “standard” per tutte le altre pagine
    // ──────────────────────────────────────────────────────────────
    if ($page === 'login' || $page === 'registrazione' || $page === 'recupero_password') {
        $base = "dtml/webarch/$page";
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
