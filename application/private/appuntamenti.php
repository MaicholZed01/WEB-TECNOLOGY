<?php
// application/private/appuntamenti.php

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

function handleAppuntamenti(bool &$showApp, string &$bodyHtmlApp): void {
    $showApp     = false;
    $bodyHtmlApp = '';

    // 1) Esci se non siamo in page=appuntamenti
    if (($_GET['page'] ?? '') !== 'appuntamenti') {
        return;
    }
    $showApp = true;

    // 2) Avvia sessione e prendi ID fisioterapista
    if (session_status() === PHP_SESSION_NONE) session_start();
    $fisioId = $_SESSION['fisio'] ?? 0;

    // 3) Connessione DB
    $db = Db::getConnection();
    $db->set_charset('utf8');

    // … (gestione DELETE e UPDATE STATO invariata) …

    // 5) Filtri GET
    $di          = trim($_GET['data_inizio']   ?? '');
    $df          = trim($_GET['data_fine']     ?? '');
    $nome        = trim($_GET['nome']          ?? '');
    $cogn        = trim($_GET['cognome']       ?? '');
    $statoFilter = trim($_GET['stato']         ?? '');
    $servFilter  = (int) ($_GET['servizio_id']  ?? 0);
    $salaFilter  = (int) ($_GET['sala_id']      ?? 0);

    $w = [];
    // ** Vincolo fisioterapista loggato **
    $w[] = "a.fisioterapista_id = $fisioId";

    if ($di)   $w[] = "a.data >= '" . $db->real_escape_string($di) . "'";
    if ($df)   $w[] = "a.data <= '" . $db->real_escape_string($df) . "'";
    if ($nome) $w[] = "r.nome LIKE '%" . $db->real_escape_string($nome) . "%'";
    if ($cogn) $w[] = "r.cognome LIKE '%" . $db->real_escape_string($cogn) . "%'";
    if ($statoFilter) $w[] = "a.stato='" . $db->real_escape_string($statoFilter) . "'";
    if ($servFilter)  $w[] = "a.servizio_id=$servFilter";
    if ($salaFilter)  $w[] = "a.sala_id=$salaFilter";
    $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';

    // 6) Dropdown per filtro
    function buildOpts($db, $table, $idCol, $labelCol, $selVal) {
        $opts = "<option value=''>Tutti</option>";
        $res = $db->query("SELECT $idCol, $labelCol FROM $table ORDER BY $labelCol");
        while ($r = $res->fetch_assoc()) {
            $sel = ((string)$r[$idCol] === (string)$selVal) ? ' selected' : '';
            $opts .= "<option value='{$r[$idCol]}'$sel>" . htmlspecialchars($r[$labelCol]) . "</option>";
        }
        return $opts;
    }

    $lista_servizi    = buildOpts($db, 'servizi',        'servizio_id',      'nome',       $servFilter);
    $lista_sala       = buildOpts($db, 'sale',            'sala_id',          'nome_sala',  $salaFilter);
    $lista_stato      = "<option value=''>Tutti</option>"
                      ."<option value='Prenotato'" . ($statoFilter==='Prenotato'?' selected':'') . ">Prenotato</option>"
                      ."<option value='Completato'" . ($statoFilter==='Completato'?' selected':'') . ">Completato</option>";

    // 7) Query elenco appuntamenti
    $sql = "SELECT
                a.appuntamento_id,
                a.data,
                a.orario,
                r.nome   AS cliente_nome,
                r.cognome AS cliente_cognome,
                s.nome   AS servizio_nome,
                sal.nome_sala AS sala_nome,
                a.stato
            FROM appuntamenti a
            JOIN richieste r ON a.richiesta_id = r.richiesta_id
            JOIN servizi   s ON a.servizio_id   = s.servizio_id
            JOIN sale      sal ON a.sala_id       = sal.sala_id
            $where
            ORDER BY a.data DESC, a.orario DESC";
    $resA = $db->query($sql);
    $tbl = '';
    while ($row = $resA->fetch_assoc()) {
        $aid  = (int)$row['appuntamento_id'];
        $data = htmlspecialchars($row['data']);
        $ora  = substr(htmlspecialchars($row['orario']),0,5);
        $cli  = htmlspecialchars($row['cliente_cognome'] . ' ' . $row['cliente_nome']);
        $srv  = htmlspecialchars($row['servizio_nome']);
        $sal  = htmlspecialchars($row['sala_nome']);
        $st   = htmlspecialchars($row['stato']);

        $tbl .= "<tr>"
              ."<td>{$data}</td>"
              ."<td>{$ora}</td>"
              ."<td>{$row['cliente_cognome']}</td>"
              ."<td>{$row['cliente_nome']}</td>"
              ."<td>{$srv}</td>"
              ."<td>{$sal}</td>"
              ."<td>{$st}</td>"
              ."<td>"
                ."<a href='index.php?page=appuntamenti&action=delete&id={$aid}' class='btn btn-outline-modern btn-xs text-danger me-1' onclick='return confirm(\"Eliminare questo appuntamento?\");'><i class='fas fa-trash-alt'></i></a>"
                ."<form action='index.php?page=appuntamenti&action=updateStato' method='post' class='d-inline'>"
                   ."<input type='hidden' name='appuntamento_id' value='{$aid}'>"
                   ."<button type='submit' name='stato' value='Completato' class='btn btn-outline-modern btn-xs'><i class='fas fa-check'></i></button>"
                ."</form>"
              ."</td>"
            ."</tr>";
    }

    // 8) Render template
    $tpl = new Template('dtml/webarch/appuntamenti');
    $tpl->setContent('messaggio_form',       $flash . ($sqlError ? '<div class="alert alert-danger">'.$sqlError.'</div>' : ''));
    $tpl->setContent('lista_servizi',        $lista_servizi);
    $tpl->setContent('lista_sala',           $lista_sala);
    $tpl->setContent('lista_stato',          $lista_stato);
    $tpl->setContent('lista_appuntamenti',   $tbl);
    
    $bodyHtmlApp = $tpl->get();
}
?>
