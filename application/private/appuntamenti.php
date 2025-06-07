<?php
// appuntamenti.php
// Controller per appuntamenti.html

// 1. Connessione al database
$conn = Db::getConnection();
if ($conn->connect_error) {
    die("Errore di connessione al database: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// 2. Inizializzo variabili per template
$messaggio_form          = '';
$lista_fisioterapisti    = '';
$lista_servizi           = '';
$lista_appuntamenti      = '';

$data_inizio     = '';
$data_fine       = '';
$stato_filter    = '';
$fisio_filter    = 0;
$servizio_filter = 0;

// Funzione di escape
function esc($conn, $val) {
    return $conn->real_escape_string(trim($val));
}

// 3. Costruisco dropdown Fisioterapisti e Servizi
function buildOptions($conn, $table, $idCol, $nameCol, $selectedValue = '') {
    $opts = "";
    $sql = "SELECT `$idCol`, `$nameCol` FROM `$table` ORDER BY `$nameCol` ASC";
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $id   = (int)$row[$idCol];
            $nome = htmlspecialchars($row[$nameCol], ENT_QUOTES, 'UTF-8');
            $sel  = ($selectedValue !== '' && $selectedValue == $id) ? ' selected' : '';
            $opts .= "<option value=\"{$id}\"{$sel}>{$nome}</option>\n";
        }
        $res->free();
    }
    return $opts;
}

$lista_fisioterapisti = "<option value=\"\">Tutti</option>\n" 
                       . buildOptions($conn, 'fisioterapisti', 'fisioterapista_id', 'nome', '');
$lista_servizi        = "<option value=\"\">Tutti</option>\n" 
                       . buildOptions($conn, 'servizi', 'servizio_id', 'nome', '');

// 4. Gestione azioni: delete e updateStato
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = intval($_GET['id']);
    if ($del_id > 0) {
        $sql_del = "DELETE FROM appuntamenti WHERE appuntamento_id = {$del_id}";
        if ($conn->query($sql_del)) {
            $messaggio_form = '<div class="alert alert-success">Appuntamento eliminato correttamente.</div>';
        } else {
            $messaggio_form = '<div class="alert alert-danger">Errore eliminazione: '
                              . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . '</div>';
        }
    }
}

// updateStato: POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' 
    && isset($_GET['action']) 
    && $_GET['action'] === 'updateStato') 
{
    $app_id = intval($_POST['appuntamento_id'] ?? 0);
    $nuovo_stato = esc($conn, $_POST['stato'] ?? '');
    if ($app_id > 0 && ($nuovo_stato === 'Prenotato' || $nuovo_stato === 'Completato')) {
        $sql_upd = "
          UPDATE appuntamenti
          SET stato = '{$nuovo_stato}'
          WHERE appuntamento_id = {$app_id}
        ";
        if ($conn->query($sql_upd)) {
            $messaggio_form = '<div class="alert alert-success">Stato aggiornato correttamente.</div>';
        } else {
            $messaggio_form = '<div class="alert alert-danger">Errore aggiornamento stato: '
                              . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . '</div>';
        }
    }
}

// 5. Recupero filtri da GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['data_inizio'])) {
        $data_inizio = esc($conn, $_GET['data_inizio']);
    }
    if (!empty($_GET['data_fine'])) {
        $data_fine = esc($conn, $_GET['data_fine']);
    }
    if (!empty($_GET['stato'])) {
        $stato_filter = esc($conn, $_GET['stato']);
    }
    if (!empty($_GET['fisioterapista_id'])) {
        $fisio_filter = intval($_GET['fisioterapista_id']);
    }
    if (!empty($_GET['servizio_id'])) {
        $servizio_filter = intval($_GET['servizio_id']);
    }
}

// Ricostruisco dropdown con selezione attuale
$lista_fisioterapisti = "<option value=\"\">Tutti</option>\n" 
                       . buildOptions($conn, 'fisioterapisti', 'fisioterapista_id', 'nome', $fisio_filter);
$lista_servizi        = "<option value=\"\">Tutti</option>\n" 
                       . buildOptions($conn, 'servizi', 'servizio_id', 'nome', $servizio_filter);

// 6. Costruisco clausole WHERE in base ai filtri
$where_clauses = [];

if ($data_inizio !== '') {
    $where_clauses[] = "DATE(a.prenotato_il) >= '{$data_inizio}'";
}
if ($data_fine !== '') {
    $where_clauses[] = "DATE(a.prenotato_il) <= '{$data_fine}'";
}
if ($stato_filter !== '') {
    $where_clauses[] = "a.stato = '{$stato_filter}'";
}
if ($fisio_filter > 0) {
    $where_clauses[] = "a.fisioterapista_id = {$fisio_filter}";
}
if ($servizio_filter > 0) {
    $where_clauses[] = "a.servizio_id = {$servizio_filter}";
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// 7. Query per elenco appuntamenti
$sql_app = "
  SELECT
    a.appuntamento_id,
    DATE(a.prenotato_il) AS data_app,
    TIME(a.prenotato_il) AS ora_app,
    r.nome AS cliente_nome,
    r.cognome AS cliente_cognome,
    s.nome AS servizio_nome,
    f.nome AS fisioterapista_nome,
    a.stato
  FROM appuntamenti AS a
  LEFT JOIN richieste AS r
    ON a.richiesta_id = r.richiesta_id
  LEFT JOIN servizi AS s
    ON a.servizio_id = s.servizio_id
  LEFT JOIN fisioterapisti AS f
    ON a.fisioterapista_id = f.fisioterapista_id
  {$where_sql}
  ORDER BY a.prenotato_il DESC
";
$res_app = $conn->query($sql_app);
if ($res_app) {
    while ($row = $res_app->fetch_assoc()) {
        $aid    = (int)$row['appuntamento_id'];
        $data   = htmlspecialchars($row['data_app'], ENT_QUOTES, 'UTF-8');
        $ora    = substr(htmlspecialchars($row['ora_app'], ENT_QUOTES, 'UTF-8'), 0, 5);
        $cliente = htmlspecialchars($row['cliente_nome'] . ' ' . $row['cliente_cognome'], ENT_QUOTES, 'UTF-8');
        $servizio = htmlspecialchars($row['servizio_nome'], ENT_QUOTES, 'UTF-8');
        $fisio    = htmlspecialchars($row['fisioterapista_nome'], ENT_QUOTES, 'UTF-8');
        $stato    = htmlspecialchars($row['stato'], ENT_QUOTES, 'UTF-8');

        // Link Azioni
        $link_modifica    = "index.php?page=aggiungi_appuntamento&appuntamento_id={$aid}";
        $link_elimina     = "index.php?page=appuntamenti&action=delete&id={$aid}";
        $form_stato       = "
          <form action=\"index.php?page=appuntamenti&action=updateStato\" method=\"post\" class=\"d-inline\">
            <input type=\"hidden\" name=\"appuntamento_id\" value=\"{$aid}\">
            <button type=\"submit\" name=\"stato\" value=\"Completato\"
                    class=\"btn btn-outline-modern btn-xs\"
                    title=\"Segna come completato\">
              <i class=\"fas fa-check fa-sm\"></i>
            </button>
          </form>
        ";

        $lista_appuntamenti .= "
          <tr>
            <td>{$data}</td>
            <td>{$ora}</td>
            <td>{$cliente}</td>
            <td>{$servizio}</td>
            <td>{$fisio}</td>
            <td>{$stato}</td>
            <td>
              <a href=\"{$link_modifica}\"
                 class=\"btn btn-outline-modern btn-xs me-1\" title=\"Modifica\">
                <i class=\"fas fa-edit fa-sm\"></i>
              </a>
              <a href=\"{$link_elimina}\"
                 class=\"btn btn-outline-modern btn-xs text-danger me-1\" title=\"Elimina\"
                 onclick=\"return confirm('Eliminare questo appuntamento?');\">
                <i class=\"fas fa-trash-alt fa-sm\"></i>
              </a>
              {$form_stato}
            </td>
          </tr>
        ";
    }
    $res_app->free();
}

// 8. Caricamento del template appuntamenti.html
$template_path = __DIR__ . '/appuntamenti.html';
$template = file_get_contents($template_path);
if ($template === false) {
    die("Impossibile caricare il template appuntamenti.html");
}

// 9. Sostituzione dei placeholder
$output = str_replace(
    [
      '<[messaggio_form]>',
      '<[lista_fisioterapisti]>',
      '<[lista_servizi]>',
      '<[lista_appuntamenti]>'
    ],
    [
      $messaggio_form,
      $lista_fisioterapisti,
      $lista_servizi,
      $lista_appuntamenti
    ],
    $template
);

// 10. Output finale (HTML renderizzato)
echo $output;

// 11. Chiusura connessione
$conn->close();
?>
