<?php
// application/public/prenotazioni.php
require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';
require_once __DIR__ . '/../logic/Servizi.php';
require_once __DIR__ . '/../logic/FasceDisponibilita.php';

function handlePrenotazioneForm(&$showForm, &$bodyHtml, &$message) {
    $showForm = false;
    $bodyHtml = '';
    $message  = '';

    // solo se page=form_prenotazione
    if (($_GET['page'] ?? '') !== 'form_prenotazione') {
        return;
    }

    $db = Db::getConnection();

    // selezioni correnti (da POST o GET)
    $sel_servizio = (int)($_REQUEST['servizio_id'] ?? 0);
    $sel_data     = trim($_REQUEST['data'] ?? '');
    $sel_fascia   = (int)($_REQUEST['fascia_id'] ?? 0);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // campi obbligatori
        $nome     = trim($_POST['nome']     ?? '');
        $cognome  = trim($_POST['cognome']  ?? '');
        $email    = trim($_POST['email']    ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $servizio = (int)($_POST['servizio_id'] ?? 0);

        if ($nome === '' || $cognome === '' || $email === '' || $telefono === '' || $servizio <= 0) {
            $message = "<div class='alert alert-danger'>Compila tutti i campi obbligatori.</div>";
        } else {
            // facoltativi
            $data_nascita = $db->real_escape_string($_POST['data_nascita'] ?? '');
            $sesso        = $db->real_escape_string($_POST['sesso'] ?? '');
            $data_pref    = $db->real_escape_string($_POST['data'] ?? '');
            $fascia_id    = (int)($_POST['fascia_id'] ?? 0) ?: 'NULL';
            $note         = $db->real_escape_string($_POST['note'] ?? '');

            // INSERT nella tabella richieste
            $sql = "
                INSERT INTO richieste
                  (nome,cognome,email,telefono,data_nascita,sesso,servizio_id,data_preferita,fascia_id,note,creato_il)
                VALUES (
                  '{$db->real_escape_string($nome)}',
                  '{$db->real_escape_string($cognome)}',
                  '{$db->real_escape_string($email)}',
                  '{$db->real_escape_string($telefono)}',
                  " . ($data_nascita ? "'$data_nascita'" : "NULL") . ",
                  " . ($sesso        ? "'$sesso'"        : "NULL") . ",
                  $servizio,
                  " . ($data_pref    ? "'$data_pref'"    : "NULL") . ",
                  $fascia_id,
                  '{$note}',
                  NOW()
                )
            ";
            if ($db->query($sql)) {
                $message = "<div class='alert alert-success'>Richiesta inviata con successo!</div>";
                // reset selezioni
                $_POST = [];
                $sel_servizio = $sel_data = '';
                $sel_fascia = 0;
            } else {
                $message = "<div class='alert alert-danger'>Errore nel salvataggio: {$db->error}</div>";
            }
        }
    }

    // mostra sempre form
    $showForm = true;

    // popola servizi
    $servizi = Servizi::listAll();
    $htmlServizi = '';
    foreach ($servizi as $s) {
        $sel = ($sel_servizio == $s['servizio_id']) ? 'selected' : '';
        $htmlServizi .= "<option value=\"{$s['servizio_id']}\" $sel>"
                      . htmlspecialchars($s['nome']) . "</option>\n";
    }


    // render template
    $tpl = new Template('dtml/2098_health/form_prenotazione');
    $tpl->setContent('messaggio_form',  $message);
    $tpl->setContent('lista_servizi',   $htmlServizi);
    $tpl->setContent('lista_fasce',     $htmlFasce);
    $bodyHtml = $tpl->get();
}
