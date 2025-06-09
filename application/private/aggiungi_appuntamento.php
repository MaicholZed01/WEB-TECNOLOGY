<?php
// application/private/aggiungi_appuntamento.php
// Controller per la pagina "aggiungi_appuntamento" che prima crea una richiesta poi fissa un appuntamento

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

function handleAggiungiAppuntamento(bool &$show, string &$bodyHtml): void {
    $show     = false;
    $bodyHtml = '';

    // 1) Verifica pagina
    if (($_GET['page'] ?? '') !== 'aggiungi_appuntamento') {
        return;
    }
    $show = true;

    // 2) Sessione e flash
    if (session_status() === PHP_SESSION_NONE) session_start();
    $flash     = $_SESSION['aggiungi_flash'] ?? '';
    unset($_SESSION['aggiungi_flash']);
    $sqlError  = $_SESSION['aggiungi_error'] ?? '';
    unset($_SESSION['aggiungi_error']);

    // 3) Connessione DB
    $db = Db::getConnection();
    $db->set_charset('utf8');

    // 4) Recupera eventuali valori POST (form richiamo)
    $old = [
        'nome'         => '',
        'cognome'      => '',
        'email'        => '',
        'telefono'     => '',
        'data_nascita' => '',
        'sesso'        => '',
        'servizio_id'  => 0,
        'data'         => '',
        'orario'       => '',
        'sala_id'      => 0,
        'note'         => ''
    ];
    $message = '';

    // 5) Salvataggio (POST + action=save)
    $action = $_GET['action'] ?? '';
    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Riempi $old
        foreach ($old as $k => $_) {
            if (isset($_POST[$k])) {
                $old[$k] = trim($_POST[$k]);
            }
        }
        $old['servizio_id'] = (int)$old['servizio_id'];
        $old['sala_id']     = (int)$old['sala_id'];

        // validazioni minime
        if ($old['nome']==='' || $old['cognome']==='' || $old['email']==='' ||
            $old['servizio_id']<=0 || $old['data']==='' || $old['orario']==='' || $old['sala_id']<=0) {
            $message = '<div class="alert alert-danger">Compila tutti i campi obbligatori (*)</div>';
        } else {
            // escape
            $n   = $db->real_escape_string($old['nome']);
            $c   = $db->real_escape_string($old['cognome']);
            $e   = $db->real_escape_string($old['email']);
            $t   = $db->real_escape_string($old['telefono']);
            $dn  = $old['data_nascita'] ? "'".$db->real_escape_string($old['data_nascita'])."'" : 'NULL';
            $s   = $old['sesso']        ? "'".$db->real_escape_string($old['sesso'])."'"       : 'NULL';
            $sid = $old['servizio_id'];
            $dat = $db->real_escape_string($old['data']);
            $ora = $db->real_escape_string($old['orario']);
            $sal = $old['sala_id'];
            $note= $db->real_escape_string($old['note']);

            // 5a) INSERT in richieste
            $sqlReq = "INSERT INTO richieste
                (nome, cognome, email, telefono, data_nascita, sesso,
                 servizio_id, data_preferita, fascia_id, note)
             VALUES
                ('{$n}','{$c}','{$e}','{$t}',{$dn},{$s},{$sid},'{$dat}',NULL,'{$note}')";
            if (! $db->query($sqlReq)) {
                $_SESSION['aggiungi_error'] = 'Errore SQL insert richiesta: '.htmlspecialchars($db->error, ENT_QUOTES);
                header('Location: index.php?page=aggiungi_appuntamento');
                exit;
            }
            // recupero il nuovo ID richiesta
            $newReqId = $db->insert_id;

            // 5b) Controllo se la sala è già occupata
            $d = $db->real_escape_string($dat);
            $o = $db->real_escape_string($ora);
            $sqlCheck = "SELECT 1
                         FROM appuntamenti
                         WHERE sala_id = $sal
                           AND data = '$d'
                           AND orario = '$o'
                         LIMIT 1";
            $resultCheck = $db->query($sqlCheck);
            if ($resultCheck && $resultCheck->num_rows > 0) {
                $_SESSION['aggiungi_error'] = '<div class="alert alert-danger">
                    La sala selezionata è già occupata per la data e l’orario indicati.
                </div>';
                $sqlDel = "DELETE FROM richieste WHERE richiesta_id = $newReqId";
                $db->query($sqlDel);
                header('Location: index.php?page=aggiungi_appuntamento');
                exit;
            }
            // 5c) Inserimento in appuntamenti
            $fisio = (int) ($_SESSION['fisio'] ?? 0);
            $sqlApp = "INSERT INTO appuntamenti
                        (richiesta_id, fisioterapista_id, servizio_id, data, orario, sala_id, stato, prenotato_il)
                      VALUES
                        ($newReqId, $fisio, $sid, '$d', '$o', $sal, 'Prenotato', NOW())";
            if ($db->query($sqlApp)) {
                $_SESSION['aggiungi_flash'] = '<div class="alert alert-success">Richiesta e appuntamento creati.</div>';
                // INVIO EMAIL DI CONFERMA
                $to      = $e;
                $subject = 'Conferma Appuntamento Prenotato';
                $msg     = "Gentile {$n} {$c},\r\n\r\n" .
                           "Il Suo appuntamento è stato fissato.\r\n" .
                           "ID Appuntamento: {$db->insert_id}\r\n" .
                           "Data: {$dat}\r\nOrario: {$ora}\r\nSala: {$sal}\r\n\r\n" .
                           "Grazie.\r\n";
                $hdr     = "From: CentroFisioterapico <noreply@tuodominio.it>\r\n" .
                           "Reply-To: info@tuodominio.it\r\n";
                if (mail($to, $subject, $message, $headers)) {
                        $_SESSION['fissa_flash'] .= '<div class="alert alert-info">Email di conferma inviata a ' . htmlspecialchars($to) . '.</div>';
                    }

                header('Location: index.php?page=aggiungi_appuntamento');
                exit;
            } else {
                $_SESSION['aggiungi_error'] = 'Errore SQL insert appuntamento: ' . htmlspecialchars($db->error, ENT_QUOTES);
                header('Location: index.php?page=aggiungi_appuntamento');
                exit;
            }
        }
    }

    // 6) Popola dropdown servizi
    $optServ = "<option value=''>-- Seleziona Servizio --</option>";
    $rs1 = $db->query("SELECT servizio_id, nome FROM servizi ORDER BY nome");
    while ($r = $rs1->fetch_assoc()) {
        $sel = ((int)$r['servizio_id'] === $old['servizio_id']) ? ' selected' : '';
        $optServ .= "<option value='{$r['servizio_id']}'$sel>".
                    htmlspecialchars($r['nome'], ENT_QUOTES).
                    "</option>";
    }

    // 7) Popola dropdown sale
    $optSala = "<option value=''>-- Seleziona Sala --</option>";
    $rs2 = $db->query("SELECT sala_id, nome_sala FROM sale ORDER BY nome_sala");
    while ($r = $rs2->fetch_assoc()) {
        $sel = ((int)$r['sala_id'] === $old['sala_id']) ? ' selected' : '';
        $optSala .= "<option value='{$r['sala_id']}'$sel>".
                    htmlspecialchars($r['nome_sala'], ENT_QUOTES).
                    "</option>";
    }

    // 8) Render template
    $tpl = new Template('dtml/webarch/aggiungi_appuntamento');
    $tpl->setContent('messaggio_form', $flash . ($sqlError ? '<div class="alert alert-danger">'.$sqlError.'</div>' : '') . $message);
    // remplaza old values
    foreach (['nome','cognome','email','telefono','data_nascita','data','orario','note'] as $f) {
        $tpl->setContent('old_'.$f, htmlspecialchars($old[$f], ENT_QUOTES));
    }
    foreach (['Maschio','Femmina','Altro'] as $sex) {
        $tpl->setContent('sel_sesso_'.$sex, $old['sesso']===$sex ? 'selected' : '');
    }
    $tpl->setContent('lista_servizi', $optServ);
    $tpl->setContent('lista_sale',     $optSala);

    $bodyHtml = $tpl->get();
}
?>
