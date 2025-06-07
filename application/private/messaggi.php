<?php
// application/private/messaggi.php

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

function handleMessages(bool &$showMsgs, string &$bodyHtmlMsgs, string &$flashMsg): void {
    $showMsgs     = false;
    $bodyHtmlMsgs = '';
    $flashMsg     = '';

    // Se non siamo su page=messaggi, esco
    if (!isset($_GET['page']) || $_GET['page'] !== 'messaggi') {
        return;
    }
    $showMsgs = true;

    // Avvio sessione per flash message
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['msg_flash'])) {
        $flashMsg = $_SESSION['msg_flash'];
        unset($_SESSION['msg_flash']);
    }

    $conn = Db::getConnection();

    // 1) Gestione DELETE tramite GET
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $mid = (int) $_GET['id'];
        if ($mid > 0) {
            $delSql = "DELETE FROM messaggi WHERE messaggio_id = $mid";
            if ($conn->query($delSql)) {
                $_SESSION['msg_flash'] = '<div class="alert alert-success">Messaggio eliminato con successo.</div>';
            } else {
                $_SESSION['msg_flash'] = '<div class="alert alert-danger">'
                                      . 'Errore durante l\'eliminazione: '
                                      . htmlspecialchars($conn->error, ENT_QUOTES)
                                      . '</div>';
            }
        }
        // Redirect per evitare duplicazione delete al refresh, mantenendo i filtri
        $redir = 'index.php?page=messaggi';
        if (!empty($_GET['data_inizio'])) {
            $redir .= '&data_inizio=' . urlencode($_GET['data_inizio']);
        }
        if (!empty($_GET['data_fine'])) {
            $redir .= '&data_fine=' . urlencode($_GET['data_fine']);
        }
        if (!empty($_GET['mittente'])) {
            $redir .= '&mittente=' . urlencode($_GET['mittente']);
        }
        if (!empty($_GET['email'])) {
            $redir .= '&email=' . urlencode($_GET['email']);
        }
        header("Location: $redir");
        exit;
    }

    // 2) Recupero valori “vecchi” dai filtri per riempire i campi del form
    $old_data_inizio = $_GET['data_inizio'] ?? '';
    $old_data_fine   = $_GET['data_fine']   ?? '';
    $old_mittente    = $_GET['mittente']    ?? '';
    $old_email       = $_GET['email']       ?? '';

    // 3) Costruzione clausole WHERE da filtri
    $whereClauses = [];
    if (!empty($old_data_inizio)) {
        $di = $conn->real_escape_string($old_data_inizio);
        $whereClauses[] = "inviato_il >= '$di 00:00:00'";
    }
    if (!empty($old_data_fine)) {
        $df = $conn->real_escape_string($old_data_fine);
        $whereClauses[] = "inviato_il <= '$df 23:59:59'";
    }
    if (!empty($old_mittente)) {
        $mtt = $conn->real_escape_string($old_mittente);
        $whereClauses[] = "mittente LIKE '%$mtt%'";
    }
    if (!empty($old_email)) {
        $mle = $conn->real_escape_string($old_email);
        $whereClauses[] = "email LIKE '%$mle%'";
    }
    $whereSql = '';
    if (count($whereClauses) > 0) {
        $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
    }

    // 4) Query per estrarre i messaggi applicando i filtri
    $sql = "
        SELECT
          messaggio_id,
          mittente,
          email,
          contenuto,
          DATE_FORMAT(inviato_il, '%Y-%m-%d %H:%i:%s') AS data_invio
        FROM messaggi
        $whereSql
        ORDER BY inviato_il DESC
    ";
    $res = $conn->query($sql);

    // 5) Costruzione righe tabella
    if ($res === false) {
        $tableRows = '<tr><td colspan="5" class="text-center text-danger">'
                   . 'Errore caricamento messaggi.</td></tr>';
    } else {
        if ($res->num_rows === 0) {
            $tableRows = '<tr><td colspan="5" class="text-center text-muted">'
                       . 'Nessun messaggio trovato.</td></tr>';
        } else {
            $tableRows = '';
            while ($r = $res->fetch_assoc()) {
                $mid       = (int) $r['messaggio_id'];
                $mittente  = htmlspecialchars($r['mittente'], ENT_QUOTES);
                $emailTxt  = htmlspecialchars($r['email'], ENT_QUOTES);
                $contenuto = $r['contenuto'];
                $preview   = mb_strlen($contenuto, 'UTF-8') > 50
                            ? mb_substr($contenuto, 0, 50, 'UTF-8') . '…'
                            : $contenuto;
                $preview   = nl2br(htmlspecialchars($preview, ENT_QUOTES));
                $dataInvio = htmlspecialchars($r['data_invio'], ENT_QUOTES);

                $mailto = 'mailto:' . rawurlencode($r['email'])
                        . '?subject=' . rawurlencode('Risposta al tuo messaggio');

                $delLink = 'index.php?page=messaggi&action=delete&id=' . $mid;
                if (!empty($old_data_inizio)) {
                    $delLink .= '&data_inizio=' . urlencode($old_data_inizio);
                }
                if (!empty($old_data_fine)) {
                    $delLink .= '&data_fine=' . urlencode($old_data_fine);
                }
                if (!empty($old_mittente)) {
                    $delLink .= '&mittente=' . urlencode($old_mittente);
                }
                if (!empty($old_email)) {
                    $delLink .= '&email=' . urlencode($old_email);
                }

                $tableRows .= "
                  <tr>
                    <td>{$dataInvio}</td>
                    <td>{$mittente}</td>
                    <td>{$emailTxt}</td>
                    <td>{$preview}</td>
                    <td>
                      <a href=\"{$mailto}\" class=\"btn btn-primary-modern btn-xs me-1\">
                        <i class=\"fas fa-reply me-1\"></i>Rispondi
                      </a>
                      <a href=\"{$delLink}\" class=\"btn btn-outline-modern btn-xs text-danger\" 
                         onclick=\"return confirm('Eliminare questo messaggio?');\">
                        <i class=\"fas fa-trash-alt me-1\"></i>Elimina
                      </a>
                    </td>
                  </tr>
                ";
            }
        }
    }

    // 6) Carico il template privato 'messaggi.html' sostituendo i placeholder
    $tpl = new Template('dtml/webarch/messaggi');
    $tpl->setContent('messaggio_form',    $flashMsg);
    $tpl->setContent('old_data_inizio',   htmlspecialchars($old_data_inizio, ENT_QUOTES));
    $tpl->setContent('old_data_fine',     htmlspecialchars($old_data_fine, ENT_QUOTES));
    $tpl->setContent('old_mittente',      htmlspecialchars($old_mittente, ENT_QUOTES));
    $tpl->setContent('old_email',         htmlspecialchars($old_email, ENT_QUOTES));
    $tpl->setContent('lista_messaggi',    $tableRows);

    $bodyHtmlMsgs = $tpl->get();
}
?>