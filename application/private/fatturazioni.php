<?php
// application/private/fatturazioni.php
// Gestione pagina “fatturazioni”

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

/**
 *  Visualizza / inserisce / cancella fatturazioni.
 *  - $show   → true se la pagina deve essere renderizzata
 *  - $body   → HTML già pronto (template compilato)
 *  - $flash  → eventuale messaggio (success / error) da mostrare
 */
function handleFatturazioni(bool &$show, string &$body, string &$flash): void
{
    $show  = false;
    $body  = '';
    $flash = '';

    /* ─────────────────────────────
       1) Questa logica gira solo su page=fatturazioni
    ───────────────────────────── */
    if (($_GET['page'] ?? '') !== 'fatturazioni') {
        return;
    }
    $show = true;

    /* ─────────────────────────────
       2) Connessione + charset
    ───────────────────────────── */
    $db = Db::getConnection();
    $db->set_charset('utf8');

    /* ─────────────────────────────
       3) DELETE
    ───────────────────────────── */
    if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $db->query("DELETE FROM fatturazioni WHERE fatturazione_id = $id");
        header('Location: index.php?page=fatturazioni');
        exit;
    }

    /* ─────────────────────────────
       4) SAVE (INSERT / UPDATE)
    ───────────────────────────── */
    if (($_GET['action'] ?? '') === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $fatId   = (int)($_POST['pagamento_id'] ?? 0);          // hidden del form
        $appId   = (int)($_POST['appuntamento_id'] ?? 0);
        $importo = trim($_POST['importo'] ?? '');

        // validazione minima
        if ($appId <= 0 || $importo === '') {
            $flash = '<div class="alert alert-danger">Compila correttamente i campi obbligatori.</div>';
        } else {
            $impSql = $db->real_escape_string($importo);

            if ($fatId > 0) {
                // UPDATE
                $sql = "UPDATE fatturazioni
                        SET appuntamento_id = $appId, importo = '$impSql'
                        WHERE fatturazione_id = $fatId";
            } else {
                // INSERT
                $sql = "INSERT INTO fatturazioni (appuntamento_id, importo)
                        VALUES ($appId, '$impSql')";
            }

            if ($db->query($sql)) {
                $_SESSION['fat_flash'] = '<div class="alert alert-success">Operazione completata.</div>';
                header('Location: index.php?page=fatturazioni');
                exit;
            }
            $flash = '<div class="alert alert-danger">Errore SQL: '
                   . htmlspecialchars($db->error, ENT_QUOTES) . '</div>';
        }
    }

    /* ─────────────────────────────
       5) Filtri (solo colonne realmente presenti!)
    ───────────────────────────── */
    $where = [];
    if (!empty($_GET['data_inizio'])) {
        $d0 = $db->real_escape_string($_GET['data_inizio']);
        $where[] = "a.data >= '$d0'";
    }
    if (!empty($_GET['data_fine'])) {
        $d1 = $db->real_escape_string($_GET['data_fine']);
        $where[] = "a.data <= '$d1'";
    }
    if (!empty($_GET['cliente'])) {
        $cl = $db->real_escape_string($_GET['cliente']);
        $where[] = "CONCAT(r.nome,' ',r.cognome) LIKE '%$cl%'";
    }
    if (!empty($_GET['servizio_id'])) {
        $sid = (int)$_GET['servizio_id'];
        $where[] = "a.servizio_id = $sid";
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    /* ─────────────────────────────
       6) Lista fatturazioni
    ───────────────────────────── */
    $sqlList = "
        SELECT  f.fatturazione_id,
                r.nome,
                r.cognome,
                CONCAT(a.data,' ',TIME_FORMAT(a.orario,'%H:%i')) AS data_appuntamento,
                s.nome AS servizio,
                f.importo
        FROM    fatturazioni f
        JOIN    appuntamenti a ON f.appuntamento_id = a.appuntamento_id
        JOIN    richieste     r ON a.richiesta_id   = r.richiesta_id
        JOIN    servizi       s ON a.servizio_id    = s.servizio_id
        $whereSql
        ORDER BY f.fatturazione_id DESC
    ";

    $tbl = '';
    if ($res = $db->query($sqlList)) {
        while ($row = $res->fetch_assoc()) {
            $tbl .= "<tr>
                       <td>{$row['fatturazione_id']}</td>
                       <td>{$row['nome']} {$row['cognome']}</td>
                       <td>{$row['data_appuntamento']}</td>
                       <td>{$row['servizio']}</td>
                       <td>{$row['importo']}</td>
                       <td>
                         <a href='index.php?page=fatturazioni&action=delete&id={$row['fatturazione_id']}'
                            class='btn btn-outline-modern btn-xs text-danger'
                            onclick=\"return confirm('Eliminare questa fatturazione?');\">
                           <i class='fas fa-trash-alt me-1'></i>Elimina
                         </a>
                       </td>
                     </tr>";
        }
    }

    /* ─────────────────────────────
       7) Dropdown appuntamenti (data + ora)
    ───────────────────────────── */
    $optApp = "<option value=''>-- Seleziona Appuntamento --</option>";
    $ra = $db->query("
        SELECT a.appuntamento_id,
               r.nome,
               r.cognome,
               a.data,
               TIME_FORMAT(a.orario,'%H:%i') AS orario
        FROM   appuntamenti a
        JOIN   richieste r ON a.richiesta_id = r.richiesta_id
        ORDER  BY a.data DESC, a.orario DESC
    ");
    if ($ra) {
        while ($r = $ra->fetch_assoc()) {
            $sel   = ((int)($_POST['appuntamento_id'] ?? 0) === (int)$r['appuntamento_id']) ? 'selected' : '';
            $label = "{$r['appuntamento_id']} – {$r['nome']} {$r['cognome']} ({$r['data']} {$r['orario']})";
            $optApp .= "<option value='{$r['appuntamento_id']}' $sel>"
                     . htmlspecialchars($label, ENT_QUOTES) . "</option>";
        }
    }

    /* ─────────────────────────────
       8) Dropdown servizi per filtro
    ───────────────────────────── */
    $optSrv = '';
    $qs = $db->query("SELECT servizio_id, nome FROM servizi ORDER BY nome");
    while ($s = $qs->fetch_assoc()) {
        $sel = ((int)($_GET['servizio_id'] ?? 0) === (int)$s['servizio_id']) ? 'selected' : '';
        $optSrv .= "<option value='{$s['servizio_id']}' $sel>"
                 . htmlspecialchars($s['nome'], ENT_QUOTES) . "</option>";
    }

    /* ─────────────────────────────
       9) Template
    ───────────────────────────── */
    $tpl = new Template('dtml/webarch/fatturazioni');
    $tpl->setContent('messaggio_form', $flash);
    $tpl->setContent('label_submit',   isset($_POST['pagamento_id']) && $_POST['pagamento_id'] ? 'Modifica' : 'Aggiungi');
    $tpl->setContent('old_importo',    htmlspecialchars($_POST['importo'] ?? '', ENT_QUOTES));
    $tpl->setContent('old_pagamento_id', (int)($_POST['pagamento_id'] ?? 0));
    $tpl->setContent('lista_appuntamenti', $optApp);
    $tpl->setContent('lista_servizi',      $optSrv);
    $tpl->setContent('lista_fatturazioni', $tbl);

    $body = $tpl->get();
}
?>
