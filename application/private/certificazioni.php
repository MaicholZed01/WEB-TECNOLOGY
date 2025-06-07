<?php
// application/private/certificazioni.php

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

/**
 * Ritorna tutte le certificazioni del fisioterapista $fisioId sotto forma di array associativo.
 * In caso di errore SQL, restituisce un array vuoto e imposta $_SESSION['certif_sql_error'].
 */
function listAllCertificazioni(int $fisioId): array {
    $conn = Db::getConnection();
    $rows = [];

    // Ho sostituito ORDER BY creato_il con ORDER BY certificazione_id
    $res = $conn->query("
        SELECT certificazione_id, nome, ente_emittente, data_rilascio, data_scadenza, url_documento
        FROM certificazioni
        WHERE fisioterapista_id = $fisioId
        ORDER BY certificazione_id DESC
    ");

    if ($res === false) {
        // Memorizzo l'errore SQL in sessione per mostrarlo a schermo
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['certif_sql_error'] = "Errore SQL: " . $conn->error;
        return [];
    }

    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    return $rows;
}

/**
 * Se $_GET['page'] == 'certificazioni', imposta $showCert true e popola $bodyHtmlCert.
 * Gestisce insert/update/delete con PRG (Post-Redirect-Get) e flash message.
 */
function handleCertificazioni(bool &$showCert, string &$bodyHtmlCert): void {
    $showCert     = false;
    $bodyHtmlCert = '';

    if (!isset($_GET['page']) || $_GET['page'] !== 'certificazioni') {
        return;
    }
    $showCert = true;

    // 1) Fisso $fisioId = 1 per test a scatola chiusa
    $fisioId = 1;

    // 2) Flash message (es. successo inserimento, update, delete)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $flash = $_SESSION['certif_flash'] ?? '';
    unset($_SESSION['certif_flash']);

    // 3) Verifico se c'è un errore SQL da mostrare
    $sqlError = $_SESSION['certif_sql_error'] ?? '';
    unset($_SESSION['certif_sql_error']);

    // 4) Variabili per il template
    $messaggio             = $flash;
    if ($sqlError) {
        // Se c'è un errore SQL, mostro prima quello
        $messaggio .= "<div class=\"alert alert-danger\">{$sqlError}</div>";
    }
    $old_nome              = '';
    $old_ente_emittente    = '';
    $old_data_rilascio     = '';
    $old_data_scadenza     = '';
    $old_url_documento     = '';
    $old_certificazione_id = '';
    $label_submit          = 'Aggiungi';

    // 5) Decodifico azione
    $action = $_GET['action'] ?? '';

    // 6) Connessione DB
    $conn = Db::getConnection();

    // ──────────────────────────────────────────────────────────────
    // 7) DELETE
    // ──────────────────────────────────────────────────────────────
    if ($action === 'delete' && isset($_GET['id'])) {
        $cid = (int) $_GET['id'];
        $check = $conn->query("
            SELECT certificazione_id
            FROM certificazioni
            WHERE certificazione_id = $cid
              AND fisioterapista_id = $fisioId
        ");
        if ($check && $check->num_rows === 1) {
            $conn->query("DELETE FROM certificazioni WHERE certificazione_id = $cid");
            $_SESSION['certif_flash'] = '<div class="alert alert-success">Certificazione eliminata correttamente.</div>';
            header('Location: index.php?page=certificazioni');
            exit;
        } else {
            $messaggio .= '<div class="alert alert-danger">Impossibile eliminare: certificazione non trovata.</div>';
        }
    }

    // ──────────────────────────────────────────────────────────────
    // 8) EDIT (popolo il form)
    // ──────────────────────────────────────────────────────────────
    if ($action === 'edit' && isset($_GET['id'])) {
        $cid = (int) $_GET['id'];
        $res = $conn->query("
            SELECT nome, ente_emittente, data_rilascio, data_scadenza, url_documento
            FROM certificazioni
            WHERE certificazione_id = $cid
              AND fisioterapista_id = $fisioId
            LIMIT 1
        ");
        if ($res && $res->num_rows === 1) {
            $row = $res->fetch_assoc();
            $old_certificazione_id = $cid;
            $old_nome              = htmlspecialchars($row['nome'], ENT_QUOTES);
            $old_ente_emittente    = htmlspecialchars($row['ente_emittente'], ENT_QUOTES);
            $old_data_rilascio     = $row['data_rilascio'];
            $old_data_scadenza     = $row['data_scadenza'];
            $old_url_documento     = htmlspecialchars($row['url_documento'], ENT_QUOTES);
            $label_submit          = 'Modifica';
        } else {
            $messaggio .= '<div class="alert alert-danger">Certificazione non trovata.</div>';
        }
    }

    // ──────────────────────────────────────────────────────────────
    // 9) SAVE (INSERT o UPDATE)
    // ──────────────────────────────────────────────────────────────
    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $cid  = isset($_POST['certificazione_id']) ? (int) $_POST['certificazione_id'] : 0;
        $nome = $conn->real_escape_string(trim($_POST['nome'] ?? ''));
        $ente = $conn->real_escape_string(trim($_POST['ente_emittente'] ?? ''));
        $dril = trim($_POST['data_rilascio'] ?? '');
        $dsca = trim($_POST['data_scadenza'] ?? '');
        $url  = $conn->real_escape_string(trim($_POST['url_documento'] ?? ''));

        if ($nome === '') {
            $messaggio .= '<div class="alert alert-danger">Il campo "Nome" è obbligatorio.</div>';
            $old_certificazione_id = $cid;
            $old_nome              = htmlspecialchars($_POST['nome'] ?? '', ENT_QUOTES);
            $old_ente_emittente    = htmlspecialchars($_POST['ente_emittente'] ?? '', ENT_QUOTES);
            $old_data_rilascio     = $dril;
            $old_data_scadenza     = $dsca;
            $old_url_documento     = htmlspecialchars($_POST['url_documento'] ?? '', ENT_QUOTES);
            $label_submit          = $cid ? 'Modifica' : 'Aggiungi';
        } else {
            if ($cid > 0) {
                // UPDATE
                $sql = "
                    UPDATE certificazioni SET
                        nome = '$nome',
                        ente_emittente = '$ente',
                        data_rilascio = " . ($dril ? "'$dril'" : "NULL") . ",
                        data_scadenza = " . ($dsca ? "'$dsca'" : "NULL") . ",
                        url_documento = '$url'
                    WHERE certificazione_id = $cid
                      AND fisioterapista_id = $fisioId
                ";
                if ($conn->query($sql)) {
                    $_SESSION['certif_flash'] = '<div class="alert alert-success">Certificazione modificata correttamente.</div>';
                    header('Location: index.php?page=certificazioni');
                    exit;
                } else {
                    $messaggio .= '<div class="alert alert-danger">Errore durante l\'aggiornamento.</div>';
                    $old_certificazione_id = $cid;
                    $old_nome              = htmlspecialchars($_POST['nome'] ?? '', ENT_QUOTES);
                    $old_ente_emittente    = htmlspecialchars($_POST['ente_emittente'] ?? '', ENT_QUOTES);
                    $old_data_rilascio     = $dril;
                    $old_data_scadenza     = $dsca;
                    $old_url_documento     = htmlspecialchars($_POST['url_documento'] ?? '', ENT_QUOTES);
                    $label_submit          = 'Modifica';
                }
            } else {
                // INSERT
                $sql = "
                    INSERT INTO certificazioni
                        (fisioterapista_id, nome, ente_emittente, data_rilascio, data_scadenza, url_documento)
                    VALUES (
                        $fisioId,
                        '$nome',
                        '$ente',
                        " . ($dril ? "'$dril'" : "NULL") . ",
                        " . ($dsca ? "'$dsca'" : "NULL") . ",
                        '$url'
                    )
                ";
                if ($conn->query($sql)) {
                    $_SESSION['certif_flash'] = '<div class="alert alert-success">Certificazione aggiunta correttamente.</div>';
                    header('Location: index.php?page=certificazioni');
                    exit;
                } else {
                    $messaggio .= '<div class="alert alert-danger">Errore durante l\'inserimento.</div>';
                    $old_certificazione_id = '';
                    $old_nome              = htmlspecialchars($_POST['nome'] ?? '', ENT_QUOTES);
                    $old_ente_emittente    = htmlspecialchars($_POST['ente_emittente'] ?? '', ENT_QUOTES);
                    $old_data_rilascio     = $dril;
                    $old_data_scadenza     = $dsca;
                    $old_url_documento     = htmlspecialchars($_POST['url_documento'] ?? '', ENT_QUOTES);
                    $label_submit          = 'Aggiungi';
                }
            }
        }
    }

    // ──────────────────────────────────────────────────────────────
    // 10) Recupero sempre le certificazioni aggiornate
    // ──────────────────────────────────────────────────────────────
    $rows = listAllCertificazioni($fisioId);

    // 11) Costruisco stringa delle righe e punto di debug
    $lista_certificazioni = '';

    // DEBUG: numero di righe trovate
    $numRows = count($rows);
    $debugComment = "<!-- listAllCertificazioni ha trovato {$numRows} righe per fisioId={$fisioId} -->";

    foreach ($rows as $row) {
        $cid   = (int) $row['certificazione_id'];
        $nome  = htmlspecialchars($row['nome'], ENT_QUOTES);
        $ente  = htmlspecialchars($row['ente_emittente'], ENT_QUOTES);
        $dril  = $row['data_rilascio'] ?: '';
        $dsca  = $row['data_scadenza'] ?: '';
        $url   = htmlspecialchars($row['url_documento'], ENT_QUOTES);

        $linkDoc = $url
                 ? "<a href=\"$url\" target=\"_blank\">Scarica</a>"
                 : '-';

        $lista_certificazioni .= "
            <tr>
              <td>{$nome}</td>
              <td>{$ente}</td>
              <td>{$dril}</td>
              <td>{$dsca}</td>
              <td>{$linkDoc}</td>
              <td>
                <a href=\"index.php?page=certificazioni&action=edit&id={$cid}\" class=\"btn btn-warning btn-xs\">Modifica</a>
                <a href=\"index.php?page=certificazioni&action=delete&id={$cid}\" class=\"btn btn-danger btn-xs\" onclick=\"return confirm('Sei sicuro di voler eliminare questa certificazione?');\">Elimina</a>
              </td>
            </tr>
        ";
    }

    // ──────────────────────────────────────────────────────────────
    // 12) Carico il template e popolo i placeholder (incluso il debug comment)
    // ──────────────────────────────────────────────────────────────
    $tpl = new Template('dtml/webarch/certificazioni');
    $tpl->setContent('messaggio_form', $messaggio);
    $tpl->setContent('old_nome', $old_nome);
    $tpl->setContent('old_ente_emittente', $old_ente_emittente);
    $tpl->setContent('old_data_rilascio', $old_data_rilascio);
    $tpl->setContent('old_data_scadenza', $old_data_scadenza);
    $tpl->setContent('old_url_documento', $old_url_documento);
    $tpl->setContent('old_certificazione_id', $old_certificazione_id);
    $tpl->setContent('label_submit', $label_submit);
    $tpl->setContent('lista_certificazioni', $lista_certificazioni);
    $tpl->setContent('debug_comment', $debugComment);

    $bodyHtmlCert = $tpl->get();
}
?>