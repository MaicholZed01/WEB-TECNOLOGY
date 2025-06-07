<?php
// application/private/richieste.php
require_once __DIR__ . '/../include/dbms.inc.php';

function handleRichieste(&$show, &$bodyHtml, &$flash) {
    $show = false;
    $bodyHtml = '';
    $flash = '';

    if (($page = $_GET['page'] ?? '') !== 'richieste') return;

    $show = true;
    $db = Db::getConnection();

    // 1) DELETE
    if ($_GET['action'] === 'delete' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $db->query("DELETE FROM richieste WHERE richiesta_id=$id");
        header('Location: index.php?page=richieste');
        exit;
    }

    // 2) FILTRI
    $w = [];
    if (!empty($_GET['data_inizio'])) {
        $d0 = $db->real_escape_string($_GET['data_inizio']);
        $w[] = "r.creato_il >= '$d0 00:00:00'";
    }
    if (!empty($_GET['data_fine'])) {
        $d1 = $db->real_escape_string($_GET['data_fine']);
        $w[] = "r.creato_il <= '$d1 23:59:59'";
    }
    if (!empty($_GET['nome'])) {
        $n = $db->real_escape_string($_GET['nome']);
        $w[] = "r.nome LIKE '%$n%'";
    }
    if (!empty($_GET['cognome'])) {
        $c = $db->real_escape_string($_GET['cognome']);
        $w[] = "r.cognome LIKE '%$c%'";
    }
    if (!empty($_GET['servizio_id'])) {
        $sid = (int)$_GET['servizio_id'];
        $w[] = "r.servizio_id=$sid";
    }
    $where = $w ? 'WHERE '. implode(' AND ', $w) : '';

    
    // 3) LISTA RICHIESTE (escludo quelle già fissate in appuntamenti)
    $sql = "
      SELECT
        r.richiesta_id,
        r.creato_il,
        r.nome,
        r.cognome,
        r.email,
        r.telefono,
        s.nome AS servizio,
        r.data_preferita,
        CONCAT(fd.inizio,' – ',fd.fine) AS fascia
      FROM richieste r
      LEFT JOIN appuntamenti ap
        ON r.richiesta_id = ap.richiesta_id
      LEFT JOIN servizi s
        ON r.servizio_id    = s.servizio_id
      LEFT JOIN fasce_disponibilita fd
        ON r.fascia_id      = fd.fascia_id
      WHERE ap.richiesta_id IS NULL
      " 
      // se ci sono filtri, li appendo qui dopo l’IS NULL
      . ( $where
          ? ' AND ' . substr($where, strlen('WHERE ')) 
          : '' )
      . "
      ORDER BY r.creato_il DESC
    ";

    $res = $db->query($sql);
    $tbl = '';
    while ($row = $res->fetch_assoc()) {
        $tbl .= "<tr>
          <td>{$row['creato_il']}</td>
          <td>{$row['nome']}</td>
          <td>{$row['cognome']}</td>
          <td>{$row['email']}</td>
          <td>{$row['telefono']}</td>
          <td>{$row['servizio']}</td>
          <td>{$row['data_preferita']}</td>
          <td>{$row['fascia']}</td>
          <td>
            <a href='index.php?page=fissa_appuntamento&richiesta_id={$row['richiesta_id']}'
               class='btn btn-outline-modern btn-xs text-success'>
              <i class='fas fa-plus'></i> Aggiungi
            </a>
            <a href='index.php?page=richieste&action=delete&id={$row['richiesta_id']}'
               class='btn btn-outline-modern btn-xs text-danger'
               onclick='return confirm(\"Eliminare questa richiesta?\");'>
              <i class='fas fa-trash-alt'></i> Elimina
            </a>
          </td>
        </tr>";
    }

    // 4) SELECT SERVIZI per filtro
    $qs = $db->query("SELECT servizio_id, nome FROM servizi ORDER BY nome");
    $optS = '';
    while ($s = $qs->fetch_assoc()) {
        $sel = ((int)($_GET['servizio_id'] ?? 0) === (int)$s['servizio_id']) ? 'selected' : '';
        $optS .= "<option value='{$s['servizio_id']}' $sel>{$s['nome']}</option>";
    }

    // 5) RENDER
    // Nel tuo template HTML sostituisci <[messaggio_form]>, <[lista_servizi]>, <[lista_richieste]>
    $bodyHtml = str_replace(
      ['<[messaggio_form]>', '<[lista_servizi]>', '<[lista_richieste]>'],
      [$flash, $optS, $tbl],
      file_get_contents(__DIR__.'/../dtml/webarch/richieste.html')
    );
}
?>