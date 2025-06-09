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
    // opzionale: if empty($_SESSION['fisio']) { header('Location:index.php?page=login'); exit; }

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

        // aggiunge i secondi mancanti: HH:MM -> HH:MM:SS
        if ($ora && strlen($ora) === 5) {
            $ora .= ':00';
        }

        if ($data === '' || $ora === '' || $sala <= 0) {
            $flash = '<div class="alert alert-danger">
                        Compila tutti i campi obbligatori (data, orario, sala).
                      </div>';
        } else {
            // Sanitizzazione
            $d = $db->real_escape_string($data);
            $o = $db->real_escape_string($ora);

            /* ──────────────────────────────────────────────
               ➊ Controllo sala / data / ora già occupata
            ────────────────────────────────────────────── */
            $sqlCheck = "SELECT 1
                         FROM appuntamenti
                         WHERE sala_id = $sala
                           AND data    = '$d'
                           AND orario  = '$o'
                         LIMIT 1";
            $resultCheck = $db->query($sqlCheck);
            $busy = ($resultCheck && $resultCheck->num_rows > 0);

            if ($busy) {
                $_SESSION['fissa_flash'] = '<div class="alert alert-danger">
                            La sala selezionata è già occupata per la data e l’orario indicati.
                          </div>';
            } else {
                /* ──────────────────────────────────────────
                   ➋ Inserimento appuntamento
                ────────────────────────────────────────── */
                $fisio = (int) ($_SESSION['fisio'] ?? $rq['fisioterapista_id']);
                $sql   = "INSERT INTO appuntamenti
                             (richiesta_id, fisioterapista_id, servizio_id,
                              data, orario, sala_id, stato, prenotato_il)
                          VALUES
                             ($reqId, $fisio, {$rq['servizio_id']},
                              '$d', '$o', $sala, 'Prenotato', NOW())";

                if ($db->query($sql)) {
                    $_SESSION['fissa_flash'] =
                        '<div class="alert alert-success">Appuntamento creato correttamente.</div>';

                    /* –– INVIO EMAIL DI CONFERMA –– */
                    $to      = $rq['email'];
                    $subject = 'Conferma Appuntamento Prenotato';
                    $message = "Gentile {$rq['nome']} {$rq['cognome']},
                    il Suo appuntamento è stato fissato con successo.
                    Data: $data
                    Orario: $ora
                    Sala: " . htmlspecialchars($_POST['sala_id']) . "

                    La ringraziamo e Le auguriamo una buona giornata.
                    ";
                    $headers  = "From: CentroFisioterapico <noreply@tuodominio.it>\r\n";
                    $headers .= "Reply-To: info@tuodominio.it\r\n";

                    if (mail($to, $subject, $message, $headers)) {
                        $_SESSION['fissa_flash'] .= '<div class="alert alert-info">Email di conferma inviata a ' . htmlspecialchars($to) . '.</div>';
                    }

                    /*
                    // ELIMINA APPUNTAMENTO DA ELENCO RICHIESTE
                    $delSql = "DELETE FROM richieste WHERE richiesta_id = $reqId";
                    if (!$db->query($delSql)) {
                        error_log("Errore eliminazione richiesta $reqId: " . $db->error);
                    }
                    */

                    header('Location: index.php?page=fissa_appuntamento&richiesta_id=' . $reqId);
                    exit;
                } else {
                    $_SESSION['fissa_error'] = 'Errore SQL: ' . htmlspecialchars($db->error, ENT_QUOTES);
                    header('Location: index.php?page=fissa_appuntamento&richiesta_id=' . $reqId);
                    exit;
                }
            }
        }

        // Preserva i valori nel form in caso di errore
        $old_data   = htmlspecialchars($data, ENT_QUOTES);
        $old_ora    = htmlspecialchars(substr($ora, 0, 5), ENT_QUOTES);
        $old_salaId = $sala;

        header('Location: index.php?page=fissa_appuntamento&richiesta_id=' . $reqId);
        exit;
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