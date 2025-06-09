<?php
// application/private/fatturazioni.php
// Gestione pagina “fatturazioni”

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';
session_start();

/**
 *  Visualizza / inserisce / cancella fatturazioni.
 *  Ogni fisioterapista vede solo le proprie.
 */
function handleFatturazioni(bool &$show, string &$body, string &$flash): void
{
    $show  = false;
    $body  = '';
    $flash = '';

    // 1) Solo page=fatturazioni
    if (($_GET['page'] ?? '') !== 'fatturazioni') {
        return;
    }
    $show = true;

    $db = Db::getConnection();
    $db->set_charset('utf8');

    // Recupero id fisioterapista da sessione
    $fisioId = $_SESSION['fisio'] ?? 0;
    if ($fisioId <= 0) {
        header('Location: index.php?page=login'); exit;
    }

    // 2) DELETE
    if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $db->query("DELETE f FROM fatturazioni f
                     JOIN appuntamenti a ON f.appuntamento_id=a.appuntamento_id
                     WHERE f.fatturazione_id=$id
                       AND a.fisioterapista_id=$fisioId");
        header('Location: index.php?page=fatturazioni');
        exit;
    }

    // 3) SAVE
    if (($_GET['action'] ?? '') === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $fatId   = (int)($_POST['pagamento_id'] ?? 0);
        $appId   = (int)($_POST['appuntamento_id'] ?? 0);
        $importo = trim($_POST['importo'] ?? '');

        // validazione
        if ($appId <= 0 || $importo === '') {
            $flash = '<div class="alert alert-danger">Campi obbligatori mancanti.</div>';
        } else {
            // verifica che appartenga al fisio
            $chk = $db->query("SELECT 1 FROM appuntamenti
                               WHERE appuntamento_id=$appId
                                 AND fisioterapista_id=$fisioId");
            if (!$chk || $chk->num_rows === 0) {
                $flash = '<div class="alert alert-danger">Appuntamento non valido.</div>';
            } else {
                $impSql = $db->real_escape_string($importo);
                if ($fatId > 0) {
                    $sql = "UPDATE fatturazioni
                             SET importo='$impSql'
                             WHERE fatturazione_id=$fatId";
                } else {
                    $sql = "INSERT INTO fatturazioni (appuntamento_id, importo)
                              VALUES ($appId, '$impSql')";
                }
                if ($db->query($sql)) {
                    $_SESSION['fat_flash'] = '<div class="alert alert-success">Salvataggio riuscito.</div>';
                    header('Location: index.php?page=fatturazioni'); exit;
                } else {
                    $flash = '<div class="alert alert-danger">Errore SQL: ' . htmlspecialchars($db->error) . '</div>';
                }
            }
        }
    }

    // 4) FILTRI (su proprie)
    $where = ["a.fisioterapista_id=$fisioId"];
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
        $where[] = "a.servizio_id=$sid";
    }
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    // 5) LISTA
    $sqlList = "
        SELECT f.fatturazione_id,
               CONCAT(r.nome,' ',r.cognome) AS cliente,
               CONCAT(a.data,' ',TIME_FORMAT(a.orario,'%H:%i')) AS data_app,
               s.nome AS servizio,
               f.importo
        FROM fatturazioni f
        JOIN appuntamenti a ON f.appuntamento_id=a.appuntamento_id
        JOIN richieste r    ON a.richiesta_id=r.richiesta_id
        JOIN servizi s      ON a.servizio_id=s.servizio_id
        $whereSql
        ORDER BY f.fatturazione_id DESC";
    $tbl = '';
    if ($res = $db->query($sqlList)) {
        while ($row = $res->fetch_assoc()) {
            $tbl .= "<tr>"
                  ."<td>{$row['fatturazione_id']}</td>"
                  ."<td>{$row['cliente']}</td>"
                  ."<td>{$row['data_app']}</td>"
                  ."<td>{$row['servizio']}</td>"
                  ."<td>{$row['importo']}</td>"
                  ."<td><a href='index.php?page=fatturazioni&action=delete&id={$row['fatturazione_id']}'"
                  ." class='btn btn-outline-modern btn-xs text-danger' onclick=\"return confirm('Eliminare?');\">"
                  ."<i class='fas fa-trash-alt'></i></a></td>"
                  ."</tr>";
        }
    }

    // 6) DROPDOWN appuntamenti del fisio
    $optApp = '<option value="">-- Seleziona Appuntamento --</option>';
    $ra = $db->query("SELECT a.appuntamento_id,
                            CONCAT(r.nome,' ',r.cognome) AS cliente,
                            a.data, TIME_FORMAT(a.orario,'%H:%i') AS orario
                      FROM appuntamenti a
                      JOIN richieste r ON a.richiesta_id=r.richiesta_id
                      WHERE a.fisioterapista_id=$fisioId
                      ORDER BY a.data DESC, a.orario DESC");
    if ($ra) {
        while ($r = $ra->fetch_assoc()) {
            $sel   = ((int)($_POST['appuntamento_id'] ?? 0) === (int)$r['appuntamento_id']) ? 'selected' : '';
            $label = "{$r['appuntamento_id']} – {$r['cliente']} ({$r['data']} {$r['orario']})";
            $optApp .= "<option value='{$r['appuntamento_id']}' $sel>" . htmlspecialchars($label) . "</option>";
        }
    }

    // 7) servizi dropdown
    $optSrv='';
    if ($qs = $db->query("SELECT servizio_id,nome FROM servizi ORDER BY nome")) {
        while ($s = $qs->fetch_assoc()) {
            $sel = ((int)($_GET['servizio_id'] ?? 0) === (int)$s['servizio_id']) ? 'selected' : '';
            $optSrv .= "<option value='{$s['servizio_id']}' $sel>" . htmlspecialchars($s['nome']) . "</option>";
        }
    }

    // 8) template
    $tpl = new Template('dtml/webarch/fatturazioni');
    $tpl->setContent('messaggio_form', $flash ?: ($_SESSION['fat_flash'] ?? '')); unset($_SESSION['fat_flash']);
    $tpl->setContent('label_submit', ((int)($_POST['pagamento_id'] ?? 0) ? 'Modifica' : 'Aggiungi'));
    $tpl->setContent('old_importo', htmlspecialchars($_POST['importo'] ?? '', ENT_QUOTES));
    $tpl->setContent('old_pagamento_id', (int)($_POST['pagamento_id'] ?? 0));
    $tpl->setContent('lista_appuntamenti', $optApp);
    $tpl->setContent('lista_servizi', $optSrv);
    $tpl->setContent('lista_fatturazioni', $tbl);
    $body = $tpl->get();
}
?>