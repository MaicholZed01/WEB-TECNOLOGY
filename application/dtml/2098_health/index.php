<?php
// application/index.php  (o application/public/index.php a seconda di come lo hai collocato)

require_once __DIR__ . '/include/dbms.inc.php';
require_once __DIR__ . '/include/template2.inc.php';
require_once __DIR__ . '/logic/Servizi.php';
require_once __DIR__ . '/logic/Fisioterapisti.php';
require_once __DIR__ . '/logic/FasceDisponibilita.php';
require_once __DIR__ . '/logic/Richieste.php';
require_once __DIR__ . '/logic/Appuntamenti.php';

// Prendi il parametro "page" dalla query-string
$page = $_GET['page'] ?? '';

switch ($page) {

    // =========================
    // 1) MOSTRO IL FORM
    // =========================
    case 'form_prenotazione':
        // Prelevo i servizi
        $servs = Servizi::listAll();
        $lista_servizi = '';
        foreach ($servs as $s) {
            $id = intval($s['servizio_id']);
            $nome = htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8');
            $lista_servizi .= "<option value=\"$id\">$nome</option>\n";
        }

        // Prelevo i fisioterapisti
        $fisios = Fisioterapisti::listAll();
        $lista_fisioterapisti = '';
        foreach ($fisios as $f) {
            $fid = intval($f['id']);
            $nome = htmlspecialchars($f['nome'] . ' ' . $f['cognome'], ENT_QUOTES, 'UTF-8');
            $lista_fisioterapisti .= "<option value=\"$fid\">$nome</option>\n";
        }

        // Prelevo tutte le fasce future (per mostrare tutte sin da subito)
        $fasce = FasceDisponibilita::listAllFuture();
        $lista_fasce = '';
        foreach ($fasce as $f) {
            $fid_fascia = intval($f['fascia_id']);
            $inizio = htmlspecialchars(substr($f['inizio'], 11, 5), ENT_QUOTES, 'UTF-8');
            $fine   = htmlspecialchars(substr($f['fine'],   11, 5), ENT_QUOTES, 'UTF-8');
            $fid    = intval($f['fisioterapista_id']);
            $lista_fasce .= "<option value=\"$fid_fascia\">$inizio – $fine (Fisio #$fid)</option>\n";
        }

        // Carico il template e sostituisco i placeholder
        $tpl = new Template('2098_health/form_prenotazione');
        $tpl->setContent('lista_servizi',        $lista_servizi);
        $tpl->setContent('lista_fisioterapisti', $lista_fisioterapisti);
        $tpl->setContent('lista_fasce',          $lista_fasce);
        $tpl->setContent('messaggio_form',       '');    // nessun messaggio iniziale
        $tpl->parse();
        break;


    // =========================================
    // 2) PROCESSO IL FORM (POST da form_prenotazione)
    // =========================================
    case 'process_prenotazione':
        $messaggio_form = '';

        // 1) Creo la nuova richiesta
        $rid = Richieste::create($_POST);
        if (!$rid) {
            $messaggio_form = '<div class="alert alert-danger">Errore nel salvare i dati anagrafici. Riprova.</div>';
        } else {
            // 2) Creo l’appuntamento
            $ok = Appuntamenti::create($rid, $_POST);
            if ($ok) {
                $messaggio_form = '<div class="alert alert-success">Prenotazione ricevuta correttamente!</div>';
            } else {
                $messaggio_form = '<div class="alert alert-danger">Errore nel salvare l\'appuntamento. Riprova.</div>';
            }
        }

        // Dopo aver processato, ricarico le stesse liste perché devo ristampare il form
        $servs = Servizi::listAll();
        $lista_servizi = '';
        foreach ($servs as $s) {
            $id = intval($s['servizio_id']);
            $nome = htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8');
            $lista_servizi .= "<option value=\"$id\">$nome</option>\n";
        }

        $fisios = Fisioterapisti::listAll();
        $lista_fisioterapisti = '';
        foreach ($fisios as $f) {
            $fid = intval($f['id']);
            $nome = htmlspecialchars($f['nome'] . ' ' . $f['cognome'], ENT_QUOTES, 'UTF-8');
            $lista_fisioterapisti .= "<option value=\"$fid\">$nome</option>\n";
        }

        $fasce = FasceDisponibilita::listAllFuture();
        $lista_fasce = '';
        foreach ($fasce as $f) {
            $fid_fascia = intval($f['fascia_id']);
            $inizio = htmlspecialchars(substr($f['inizio'], 11, 5), ENT_QUOTES, 'UTF-8');
            $fine   = htmlspecialchars(substr($f['fine'],   11, 5), ENT_QUOTES, 'UTF-8');
            $fid    = intval($f['fisioterapista_id']);
            $lista_fasce .= "<option value=\"$fid_fascia\">$inizio – $fine (Fisio #$fid)</option>\n";
        }

        // Ricarico il template con il messaggio di risultato
        $tpl = new Template('/tech-web/application/dtml/2098_health/form_prenotazione');
        $tpl->setContent('lista_servizi',        $lista_servizi);
        $tpl->setContent('lista_fisioterapisti', $lista_fisioterapisti);
        $tpl->setContent('lista_fasce',          $lista_fasce);
        $tpl->setContent('messaggio_form',       $messaggio_form);
        $tpl->parse();
        break;


    // =========================
    // 3) DEFAULT: reindirizza a form_prenotazione
    // =========================
    default:
        header('Location: index.php?page=form_prenotazione');
        exit;
}
?>