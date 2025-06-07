<?php
// application/private/fissa_appuntamento.php
// Controller per la pagina "fissa_appuntamento" che trasforma una richiesta in un appuntamento

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

function handleFissaAppuntamento(bool &$show, string &$bodyHtml): void {
    $show = false;
    $bodyHtml = '';

    // 1) Verifica pagina
    if (($_GET['page'] ?? '') !== 'fissa_appuntamento') {
        return;
    }
    $show = true;

    // 2) Avvia sessione e controlla login (se richiesto)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // opzionale: if empty session['fisio'] redirect login
    // if (empty($_SESSION['fisio'])) { header('Location:index.php?page=login'); exit; }

    // 3) Flash message via session
    $flash    = $_SESSION['fissa_flash'] ?? '';
    unset($_SESSION['fissa_flash']);
    $sqlError = $_SESSION['fissa_error'] ?? '';
    unset($_SESSION['fissa_error']);

    $reqId = (int) ($_REQUEST['richiesta_id'] ?? 0);
    if ($reqId <= 0) {
        header('Location: index.php?page=richieste');
        exit;
    }

    $db = Db::getConnection();
    $db->set_charset('utf8');

    // 4) Recupera richiesta
    $res = $db->query("SELECT * FROM richieste WHERE richiesta_id=$reqId");
    $rq  = ($res && $res->num_rows === 1) ? $res->fetch_assoc() : null;
    if (!$rq) {
        header('Location: index.php?page=richieste');
        exit;
    }

    // 5) Salvataggio (PRG)
    $action = $_GET['action'] ?? '';
    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = trim($_POST['data'] ?? '');
        $ora  = trim($_POST['orario'] ?? '');
        $sala = (int) ($_POST['sala_id'] ?? 0);

        // validazioni
        if ($data === '' || $ora === '' || $sala <= 0) {
            $flash = '<div class="alert alert-danger">Compila tutti i campi obbligatori (data, orario, sala).</div>';
        } else {
            // escape
            $d = $db->real_escape_string($data);
            $o = $db->real_escape_string($ora);
            // fisio id da sessione
            $fisio = (int) ($_SESSION['fisio'] ?? $rq['fisioterapista_id']);
            // inserimento appuntamento
            $sql = "INSERT INTO appuntamenti
                        (richiesta_id, fisioterapista_id, servizio_id, data, orario, sala_id, stato, prenotato_il)
                    VALUES
                        ($reqId, $fisio, {$rq['servizio_id']}, '$d', '$o', $sala, 'Prenotato', NOW())";
            if ($db->query($sql)) {
                $_SESSION['fissa_flash'] = '<div class="alert alert-success">Appuntamento creato correttamente.</div>';
                // 3) INVIO EMAIL DI CONFERMA
                $to      = $rq['email'];                           // l’indirizzo preso dalla richiesta
                $subject = 'Conferma Appuntamento Prenotato';
                // Prepara il corpo (puoi arricchirlo in HTML)
                $message = "
                  Gentile {$rq['nome']} {$rq['cognome']},\r\n\r\n
                  il Suo appuntamento è stato fissato con successo.\r\n
                  Data: $data\r\n
                  Orario: $ora\r\n
                  Sala: " . htmlspecialchars($_POST['sala_id']) . "\r\n\r\n
                  La ringraziamo e Le auguriamo una buona giornata.\r\n
                ";
                // Header per mail in plain-text (o HTML se vuoi)
                $headers  = "From: CentroFisioterapico <noreply@tuodominio.it>\\r\\n";
                $headers .= "Reply-To: info@tuodominio.it\\r\\n";
                // Se vuoi inviare HTML, aggiungi:
                // $headers .= \"MIME-Version: 1.0\\r\\n\";
                // $headers .= \"Content-type: text/html; charset=UTF-8\\r\\n\";

                // Esegui l’invio
                if (mail($to, $subject, $message, $headers)) {
                    // opzionale: un altro flash in sessione
                    $_SESSION['fissa_flash'] .= '<div class="alert alert-info">Email di conferma inviata a ' . htmlspecialchars($to) . '.</div>';
                } 
				/*ELIMINA APPUNTAMENTO DA ELENCO RICHIESTE
                $delSql = "DELETE FROM richieste WHERE richiesta_id = $reqId";
                if (!$db->query($delSql)) {
                    // opzionale: logga o mostra errore di cancellazione
                    error_log("Errore eliminazione richiesta $reqId: " . $db->error);
                }
                // 3) redirect PRG
                header('Location: index.php?page=fissa_appuntamento&richiesta_id=' . $reqId);
                exit;
                else {
                // SQL error
                $_SESSION['fissa_error'] = 'Errore SQL: ' . htmlspecialchars($db->error, ENT_QUOTES);
                header('Location: index.php?page=fissa_appuntamento&richiesta_id=' . $reqId);
                exit;*/
            }
        }
     }
    
        

    // 6) Prepara dropdown sale
    $optSala = "<option value=''>-- Seleziona Sala --</option>";
    $rs      = $db->query("SELECT sala_id, nome_sala FROM sale ORDER BY nome_sala");
    if ($rs) {
        while ($row = $rs->fetch_assoc()) {
            $sel     = ((int) ($_POST['sala_id'] ?? 0) === (int) $row['sala_id']) ? ' selected' : '';
            $optSala .= "<option value='{$row['sala_id']}'$sel>" . htmlspecialchars($row['nome_sala'], ENT_QUOTES) . "</option>";
        }
    }

    // 7) Carica template fissa_appuntamento.html
    $tpl = new Template('dtml/webarch/fissa_appuntamento');
    // flash e errori
    $tpl->setContent('messaggio_form', $flash . ($sqlError ? '<div class="alert alert-danger">' . $sqlError . '</div>' : ''));
    // dati read-only
    $tpl->setContent('richiesta_nome',         htmlspecialchars($rq['nome'], ENT_QUOTES));
    $tpl->setContent('richiesta_cognome',      htmlspecialchars($rq['cognome'], ENT_QUOTES));
    $tpl->setContent('richiesta_email',        htmlspecialchars($rq['email'], ENT_QUOTES));
    $tpl->setContent('richiesta_telefono',     htmlspecialchars($rq['telefono'], ENT_QUOTES));
    $tpl->setContent('richiesta_data_nascita', htmlspecialchars($rq['data_nascita'], ENT_QUOTES));
    $tpl->setContent('richiesta_sesso',        htmlspecialchars($rq['sesso'], ENT_QUOTES));
    // servizio
    $srv = $db->query("SELECT nome FROM servizi WHERE servizio_id={$rq['servizio_id']}");
    $servizioNome = ($srv && $srv->num_rows === 1) ? $srv->fetch_assoc()['nome'] : '';
    $tpl->setContent('richiesta_servizio', htmlspecialchars($servizioNome, ENT_QUOTES));
    // data preferita e fascia
    $tpl->setContent('richiesta_data_preferita', htmlspecialchars($rq['data_preferita'], ENT_QUOTES));
    $fas = $db->query("SELECT CONCAT(inizio,' – ',fine) AS fascia FROM fasce_disponibilita WHERE fascia_id={$rq['fascia_id']}");
    $tpl->setContent('richiesta_fascia', htmlspecialchars($fas && $fas->num_rows === 1 ? $fas->fetch_assoc()['fascia'] : '', ENT_QUOTES));
    // hidden e dropdown
    $tpl->setContent('richiesta_id',   $reqId);
    $tpl->setContent('lista_sale',     $optSala);
    $tpl->setContent('old_data',       htmlspecialchars($_POST['data'] ?? '', ENT_QUOTES));
    $tpl->setContent('old_orario',     htmlspecialchars($_POST['orario'] ?? '', ENT_QUOTES));

    $bodyHtml = $tpl->get();
}
?>