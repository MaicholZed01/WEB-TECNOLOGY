<?php
// application/private/fissa_appuntamento.php
// Controller per la pagina "Fissa Appuntamento"

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';
session_start();

/**
 * handleFissaAppuntamento: recupera i dati di una richiesta e, su POST, inserisce un appuntamento
 * @param bool   &$show      se true mostra la pagina
 * @param string &$bodyHtml  HTML generato della pagina
 */
function handleFissaAppuntamento(&$show, &$bodyHtml) {
    $show     = false;
    $bodyHtml = '';

    // Esegui solo se page=fissa_appuntamento
    if (($_GET['page'] ?? '') !== 'fissa_appuntamento') {
        return;
    }
    $show = true;

    $db = Db::getConnection();
    $db->set_charset('utf8');

    // ID richiesta
    $reqId = (int)($_REQUEST['richiesta_id'] ?? 0);
    if ($reqId <= 0) {
        header('Location: index.php?page=richieste');
        exit;
    }

    // Recupera i dati della richiesta
    $resRq = $db->query("SELECT * FROM richieste WHERE richiesta_id=$reqId");
    if (!$resRq || $resRq->num_rows === 0) {
        header('Location: index.php?page=richieste');
        exit;
    }
    $rq = $resRq->fetch_assoc();

    // Messaggio di esito
    $msg = '';

    // Se arriva POST && action=save => salva appuntamento
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'save') {
        $data    = trim($_POST['data'] ?? '');
        $ora     = trim($_POST['orario'] ?? '');
        $sala    = (int)($_POST['sala_id'] ?? 0);

        // Validazione
        if ($data === '' || $ora === '' || $sala <= 0) {
            $msg = "<div class='alert alert-danger'>Compilare tutti i campi obbligatori.</div>";
        } else {
            // Escape
            $d = $db->real_escape_string($data);
            $o = $db->real_escape_string($ora);
            // Inserimento (usa colonne `data` e `orario` come da struttura)
            $sql = "INSERT INTO appuntamenti
                      (richiesta_id, fisioterapista_id, servizio_id, data, orario, sala_id, stato, prenotato_il)
                     VALUES
                      ({$rq['richiesta_id']}, {$rq['fisioterapista_id']}, {$rq['servizio_id']}, '\$d', '\$o', $sala, 'Prenotato', NOW())";
            if ($db->query($sql)) {
                $msg = "<div class='alert alert-success'>Appuntamento fissato con successo.</div>";
            } else {
                $msg = "<div class='alert alert-danger'>Errore SQL: " . htmlspecialchars(\$db->error) . "</div>";
            }
        }
    }

    // Costruisci dropdown sale
    $optS = "<option value=''>-- Seleziona Sala --</option>";
    $qr = $db->query("SELECT sala_id, nome_sala FROM sale ORDER BY nome_sala");
    if ($qr) {
        while ($r = $qr->fetch_assoc()) {
            $sel = ((int)($_POST['sala_id'] ?? 0) === (int)$r['sala_id']) ? ' selected' : '';
            $optS .= "<option value='{$r['sala_id']}'$sel>" . htmlspecialchars(\$r['nome_sala']) . "</option>";
        }
    }

    // Carica template
    $tpl = new Template('dtml/webarch/fissa_appuntamento');
    $tpl->setContent('messaggio_form',      $msg);
    // Dati readonly
    $tpl->setContent('richiesta_nome',        htmlspecialchars(\$rq['nome']));
    $tpl->setContent('richiesta_cognome',     htmlspecialchars(\$rq['cognome']));
    $tpl->setContent('richiesta_email',       htmlspecialchars(\$rq['email']));
    $tpl->setContent('richiesta_telefono',    htmlspecialchars(\$rq['telefono']));
    $tpl->setContent('richiesta_data_nascita',htmlspecialchars(\$rq['data_nascita']));
    $tpl->setContent('richiesta_sesso',       htmlspecialchars(\$rq['sesso']));
    // Servizio
    $srv = \$db->query("SELECT nome FROM servizi WHERE servizio_id={$rq['servizio_id']}")->fetch_assoc()['nome'] ?? '';
    $tpl->setContent('richiesta_servizio',    htmlspecialchars(\$srv));
    // Data preferita e fascia
    $tpl->setContent('richiesta_data_preferita',htmlspecialchars(\$rq['data_preferita']));
    $f = \$db->query("SELECT CONCAT(inizio,' – ',fine) AS fascia FROM fasce_disponibilita WHERE fascia_id={$rq['fascia_id']}")->fetch_assoc()['fascia'] ?? '';
    $tpl->setContent('richiesta_fascia',      htmlspecialchars(\$f));
    // Hidden
    $tpl->setContent('richiesta_id',          \$reqId);
    // Dropdown sale e valori old
    $tpl->setContent('lista_sale',            \$optS);
    $tpl->setContent('old_data',              htmlspecialchars(\$_POST['data'] ?? ''));
    $tpl->setContent('old_orario',            htmlspecialchars(\$_POST['orario'] ?? ''));

    $bodyHtml = \$tpl->get();
}
?>
