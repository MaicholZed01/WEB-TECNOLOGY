<?php
// application/public/prenotazioni.php

use function Fisioterapisti\listAll as listFisioterapisti;
use function Servizi\listAll       as listServizi;
use function FasceDisponibilita\byFisio;

require_once __DIR__ . '/../logic/Fisioterapisti.php';
require_once __DIR__ . '/../logic/Servizi.php';
require_once __DIR__ . '/../logic/FasceDisponibilita.php';
require_once __DIR__ . '/../logic/Richieste.php';
require_once __DIR__ . '/../logic/Appuntamenti.php';
require_once __DIR__ . '/../include/template2.inc.php';

//
// Questa funzione:
//
function handlePrenotazioneForm(&$showForm, &$bodyHtml, &$message) {
    // Inizializza
    $message  = '';
    $showForm = false;
    $bodyHtml = '';

    // 1) Solo se la page richiesta è "form_prenotazione"
    if ($_GET['page'] !== 'form_prenotazione') {
        return;
    }

    // 2) Se arrivo in POST, valido e inserisco
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 2.a) Controllo campi obbligatori
        if (
            empty($_POST['nome']) ||
            empty($_POST['cognome']) ||
            empty($_POST['servizio_id']) ||
            empty($_POST['fascia_id'])
        ) {
            $message  = "<span style='color:red;'>Compila tutti i campi obbligatori.</span>";
            $showForm = true;
        } else {
            // 2.b) Creo la richiesta
            $rid = Richieste::create($_POST);
            if (!$rid) {
                $message  = "<span style='color:red;'>Errore nella creazione della richiesta.</span>";
                $showForm = true;
            } else {
                // 2.c) Creo l’appuntamento
                $ok = Appuntamenti::create($rid, $_POST);
                if ($ok) {
                    // 2.c.1) REDIRECT PRG → evito duplicati su refresh
                    header('Location: index.php?page=form_prenotazione&success=1');
                    exit;
                } else {
                    $message  = "<span style='color:red;'>Errore nella creazione dell’appuntamento.</span>";
                    $showForm = true;
                }
            }
        }
    }
    // 3) Se è GET (o redirect dopo POST), mostro il form
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // 3.a) Se vengo da redirect con ?success=1
        if (isset($_GET['success']) && $_GET['success'] === '1') {
            $message = "<span style='color:green;'>Prenotazione avvenuta con successo!</span>";
        }
        $showForm = true;
    }

    // 4) Se devo mostrare il form, popolo le <select> e preparo $bodyHtml
    if ($showForm) {
        // 4.a) Prendo tutti i servizi
        $servizi = Servizi::listAll();         // array di [servizio_id,nome]
        // 4.b) Prendo tutti i fisioterapisti
        $fisios  = Fisioterapisti::listAll();   // array di [id,nome,cognome]
        // 4.c) Scelgo il default per le fasce
        $defaultFisioId = $_POST['fisioterapista_id'] 
                       ?? ($fisios[0]['id'] ?? 0);
        $fasce = FasceDisponibilita::byFisio($defaultFisioId);

        // 5) Creo le stringhe <option> per ciascun select
        //   − lista_servizi
        $htmlServizi = '';
        foreach ($servizi as $s) {
            $sel = (($_POST['servizio_id'] ?? '') == $s['servizio_id']) ? 'selected' : '';
            $nomeEsc = htmlspecialchars($s['nome']);
            $htmlServizi .= "<option value=\"{$s['servizio_id']}\" $sel>$nomeEsc</option>\n";
        }
        //   − lista_fisioterapisti
        $htmlFisios = "<option value=\"\">Qualunque disponibile</option>\n";
        foreach ($fisios as $f) {
            $val   = "{$f['id']}";
            $sel   = (($_POST['fisioterapista_id'] ?? '') == $val) ? 'selected' : '';
            $label = htmlspecialchars($f['nome'] . ' ' . $f['cognome']);
            $htmlFisios .= "<option value=\"$val\" $sel>$label</option>\n";
        }
        //   − lista_fasce
        $htmlFasce = "<option value=\"\">-- Scegli una fascia --</option>\n";
        foreach ($fasce as $f) {
            $val   = "{$f['fascia_id']}";
            $sel   = (($_POST['fascia_id'] ?? '') == $val) ? 'selected' : '';
            $label = htmlspecialchars($f['inizio'] . ' - ' . $f['fine']);
            $htmlFasce .= "<option value=\"$val\" $sel>$label</option>\n";
        }

        // 6) Carico il template HTML e inietto i placeholder
        $bodyTpl = new Template('dtml/2098_health/form_prenotazione');
        $bodyTpl->setContent('lista_servizi', $htmlServizi);
        $bodyTpl->setContent('lista_fisioterapisti', $htmlFisios);
        $bodyTpl->setContent('lista_fasce', $htmlFasce);
        $bodyTpl->setContent('messaggio_form', $message);
        $bodyHtml = $bodyTpl->get();
    }
}
?>