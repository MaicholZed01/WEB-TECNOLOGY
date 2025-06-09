<?php
// application/private/avvisi2.php
// Gestione degli avvisi (annunci) per l'area privata
// Tabella SQL: annunci (annuncio_id, fisioteraprista_id, titolo, contenuto, pubblicato_il)
// Template     : dtml/webarch/avvisi2.html

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

/**
 * Controller Avvisi 2
 * Popola il template con:
 *   <[messaggio_form]>
 *   <[label_submit]>
 *   <[old_titolo]>
 *   <[old_contenuto]>
 *   <[old_annuncio_id]>
 *   <[lista_annunci]>
 */
function handleAvvisi2(bool &$show, string &$bodyHtml): void
{
    $show     = false;
    $bodyHtml = '';

    /* Mostra solo se page=avvisi2 */
    if (($_GET['page'] ?? '') !== 'avvisi2') {
        return;
    }
    $show = true;

    /* ───── Sessione & login opzionale ───── */
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Id del fisioterapista loggato – se non esiste uso 0 (annuncio "di sistema")
    $fisioId = (int)($_SESSION['fisio'] ?? 0);

    /* Flash dal redirect */
    $flash = $_SESSION['avi_flash'] ?? '';
    unset($_SESSION['avi_flash']);

    /* Connessione DB */
    $db = Db::getConnection();
    $db->set_charset('utf8');

    /* ───── Variabili form (old) ───── */
    $old_id        = 0;
    $old_titolo    = '';
    $old_contenuto = '';
    $label_submit  = 'Aggiungi';
    $messaggio_form = '';

    /* ─────────────────────────────────────
       DELETE
       ───────────────────────────────────── */
    if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
        $delId = (int)$_GET['id'];
        $db->query("DELETE FROM annunci WHERE annuncio_id = $delId");
        $_SESSION['avi_flash'] = '<div class="alert alert-success">Avviso eliminato correttamente.</div>';
        header('Location: index.php?page=avvisi2');
        exit;
    }

    /* ─────────────────────────────────────
       EDIT → popola form
       ───────────────────────────────────── */
    if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
        $editId = (int)$_GET['id'];
        $resE = $db->query("SELECT titolo, contenuto FROM annunci WHERE annuncio_id = $editId LIMIT 1");
        if ($resE && $resE->num_rows === 1) {
            $r = $resE->fetch_assoc();
            $old_id        = $editId;
            $old_titolo    = $r['titolo'];
            $old_contenuto = $r['contenuto'];
            $label_submit  = 'Modifica';
        }
    }

    /* ─────────────────────────────────────
       SAVE (insert/update) – PRG pattern
       ───────────────────────────────────── */
    if (($_GET['action'] ?? '') === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $aid      = isset($_POST['annuncio_id']) ? (int)$_POST['annuncio_id'] : 0;
        $titolo   = trim($_POST['titolo'] ?? '');
        $conten   = trim($_POST['contenuto'] ?? '');

        if ($titolo === '') {
            $messaggio_form = '<div class="alert alert-danger">Il titolo è obbligatorio.</div>';
            $old_id        = $aid;
            $old_titolo    = htmlspecialchars($titolo, ENT_QUOTES);
            $old_contenuto = htmlspecialchars($conten, ENT_QUOTES);
            $label_submit  = $aid ? 'Modifica' : 'Aggiungi';
        } else {
            // escape SQL
            $titoloSql = $db->real_escape_string($titolo);
            $contenSql = $db->real_escape_string($conten);

            if ($aid > 0) {
                // UPDATE
                $sql = "UPDATE annunci
                        SET titolo = '$titoloSql', contenuto = '$contenSql'
                        WHERE annuncio_id = $aid";
            } else {
                // INSERT (usa fisioteraprista_id della sessione se presente)
                $sql = "INSERT INTO annunci (fisioteraprista_id, titolo, contenuto)
                        VALUES ($fisioId, '$titoloSql', '$contenSql')";
            }

            if ($db->query($sql)) {
                $_SESSION['avi_flash'] = '<div class="alert alert-success">Avviso salvato correttamente.</div>';
                header('Location: index.php?page=avvisi2');
                exit;
            }
            $messaggio_form = '<div class="alert alert-danger">Errore SQL: '.htmlspecialchars($db->error, ENT_QUOTES).'</div>';
            $old_id        = $aid;
            $old_titolo    = htmlspecialchars($titolo, ENT_QUOTES);
            $old_contenuto = htmlspecialchars($conten, ENT_QUOTES);
            $label_submit  = $aid ? 'Modifica' : 'Aggiungi';
        }
    }

    /* ─────────────────────────────────────
       Elenco avvisi (join fisioterapisti)
       ───────────────────────────────────── */
    $lista_annunci = '';
    $sqlList = "SELECT a.annuncio_id, a.titolo, a.contenuto, a.pubblicato_il,
                       f.nome, f.cognome
                FROM   annunci a
                LEFT   JOIN fisioterapisti f ON a.fisioteraprista_id = f.fisioterapista_id
                ORDER  BY a.pubblicato_il DESC";

    if ($resL = $db->query($sqlList)) {
        while ($row = $resL->fetch_assoc()) {
            $fid  = (int)$row['annuncio_id'];
            $name = htmlspecialchars(trim(($row['nome'] ?? '') . ' ' . ($row['cognome'] ?? '')), ENT_QUOTES);
            if ($name === '') $name = '-';
            $titolo = htmlspecialchars($row['titolo'], ENT_QUOTES);
            $cont   = htmlspecialchars($row['contenuto'], ENT_QUOTES);
            $pubb   = $row['pubblicato_il'];

            $lista_annunci .= "<tr>
                                   <td>{$fid}</td>
                                   <td>{$name}</td>
                                   <td>{$titolo}</td>
                                   <td>{$cont}</td>
                                   <td>{$pubb}</td>
                                   <td>
                                       <a href='index.php?page=avvisi2&action=edit&id={$fid}'
                                          class='btn btn-outline-modern btn-xs me-1'>
                                           <i class='fas fa-edit'></i>
                                       </a>
                                       <a href='index.php?page=avvisi2&action=delete&id={$fid}'
                                          class='btn btn-outline-modern btn-xs text-danger'
                                          onclick='return confirm(\"Eliminare questo avviso?\");'>
                                           <i class='fas fa-trash-alt'></i>
                                       </a>
                                   </td>
                                 </tr>";
        }
    }

    if ($lista_annunci === '') {
        $lista_annunci = "<tr><td colspan='6' class='text-center text-muted'>Nessun avviso presente.</td></tr>";
    }

    /* ─────────────────────────────────────
       Render Template
       ───────────────────────────────────── */
    $tpl = new Template('dtml/webarch/avvisi2');
    $tpl->setContent('messaggio_form', $messaggio_form ?: $flash);
    $tpl->setContent('label_submit',   $label_submit);
    $tpl->setContent('old_titolo',     htmlspecialchars($old_titolo, ENT_QUOTES));
    $tpl->setContent('old_contenuto',  htmlspecialchars($old_contenuto, ENT_QUOTES));
    $tpl->setContent('old_annuncio_id',$old_id);
    $tpl->setContent('lista_annunci',  $lista_annunci);

    $bodyHtml = $tpl->get();
}
?>
