<?php
// fatturazioni.php
// Controller per fatturazioni.html

session_start();

// 1. Connessione al database
$host     = 'localhost';
$username = 'TUO_USERNAME';
$password = 'TUA_PASSWORD';
$database = 'my_lazzarini21';

$mysqli = new mysqli($host, $username, $password, $database);
if ($mysqli->connect_errno) {
    die("Errore di connessione al database: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8");

// 2. Inizializzo variabili per template
$messaggio_form         = '';
$label_submit           = 'Aggiungi';
$old_pagamento_id       = '';
$old_appuntamento_id    = '';
$old_importo            = '';
$lista_appuntamenti     = '';
$lista_servizi_filter   = '';
$lista_servizi_dropdown = '';
$lista_fatturazioni     = '';

$data_inizio   = '';
$data_fine     = '';
$cliente_filt  = '';
$servizio_filt = '';

// Funzione di escape
function esc($mysqli, $val) {
    return $mysqli->real_escape_string(trim($val));
}

// 3. Costruisco dropdown Servizi per filtro e per form
function buildOptions($mysqli, $table, $idCol, $nameCol, $selectedValue = '') {
    $opts = "";
    $sql = "SELECT `$idCol`, `$nameCol` FROM `$table` ORDER BY `$nameCol` ASC";
    if ($res = $mysqli->query($sql)) {
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

// Popolo dropdown servizi per filtro
$lista_servizi_filter = "<option value=\"\">-- Seleziona Servizio --</option>\n" 
                      . buildOptions($mysqli, 'servizi', 'servizio_id', 'nome', '');

// 4. Costruisco dropdown Appuntamenti per form Aggiungi/Modifica
function buildAppuntamentiDropdown($mysqli, $selected = '') {
    $opts = "";
    // Unisco appuntamenti, richieste (nome/cognome), servizi, per mostrare opzione: "ID - Cliente (Data Ora) - Servizio"
    $sql = "
      SELECT 
        a.appuntamento_id,
        r.nome AS cliente_nome,
        r.cognome AS cliente_cognome,
        DATE_FORMAT(a.prenotato_il,'%Y-%m-%d %H:%i') AS dt,
        s.nome AS servizio_nome
      FROM appuntamenti AS a
      LEFT JOIN richieste AS r ON a.richiesta_id = r.richiesta_id
      LEFT JOIN servizi  AS s ON a.servizio_id = s.servizio_id
      ORDER BY a.prenotato_il DESC
    ";
    if ($res = $mysqli->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $id    = (int)$row['appuntamento_id'];
            $cliente = htmlspecialchars($row['cliente_nome'] . ' ' . $row['cliente_cognome'], ENT_QUOTES, 'UTF-8');
            $dt    = htmlspecialchars($row['dt'], ENT_QUOTES, 'UTF-8');
            $serv  = htmlspecialchars($row['servizio_nome'], ENT_QUOTES, 'UTF-8');
            $label = "{$id} - {$cliente} ({$dt}) - {$serv}";
            $sel   = ($selected !== '' && $selected == $id) ? ' selected' : '';
            $opts .= "<option value=\"{$id}\"{$sel}>{$label}</option>\n";
        }
        $res->free();
    }
    return $opts;
}

// 5. Gestione azioni: delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = intval($_GET['id']);
    if ($del_id > 0) {
        $sql_del = "DELETE FROM pagamenti WHERE pagamento_id = {$del_id}";
        if ($mysqli->query($sql_del)) {
            $messaggio_form = '<div class="alert alert-success">Fatturazione eliminata correttamente.</div>';
        } else {
            $messaggio_form = '<div class="alert alert-danger">Errore eliminazione: '
                              . htmlspecialchars($mysqli->error, ENT_QUOTES, 'UTF-8') . '</div>';
        }
    }
}

// 6. Se si sta modificando un pagamento (GET action=edit&id=...)
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    if ($edit_id > 0) {
        $sql_sel = "
          SELECT 
            p.pagamento_id,
            p.appuntamento_id,
            p.importo
          FROM pagamenti AS p
          WHERE p.pagamento_id = {$edit_id}
          LIMIT 1
        ";
        if ($res = $mysqli->query($sql_sel)) {
            if ($res->num_rows === 1) {
                $row = $res->fetch_assoc();
                $old_pagamento_id     = (int)$row['pagamento_id'];
                $old_appuntamento_id  = (int)$row['appuntamento_id'];
                $old_importo          = htmlspecialchars($row['importo'], ENT_QUOTES, 'UTF-8');
                $label_submit         = 'Modifica';
            }
            $res->free();
        }
    }
}

// 7. Gestione salvataggio (POST action=save)
if ($_SERVER['REQUEST_METHOD'] === 'POST' 
    && isset($_GET['action']) 
    && $_GET['action'] === 'save') 
{
    $pagamento_id      = intval($_POST['pagamento_id'] ?? 0);
    $appuntamento_id   = intval($_POST['appuntamento_id'] ?? 0);
    $importo           = esc($mysqli, $_POST['importo'] ?? '');

    // Validazione: appuntamento e importo obbligatori
    if ($appuntamento_id === 0 || $importo === '') {
        $messaggio_form = '<div class="alert alert-danger">Selezionare un appuntamento e specificare l\'importo.</div>';
        // Mantengo i valori nel form
        $old_pagamento_id    = $pagamento_id;
        $old_appuntamento_id = $appuntamento_id;
        $old_importo         = htmlspecialchars($importo, ENT_QUOTES, 'UTF-8');
        $label_submit        = ($pagamento_id > 0 ? 'Modifica' : 'Aggiungi');
    } else {
        if ($pagamento_id > 0) {
            // UPDATE
            $sql_upd = "
              UPDATE pagamenti
              SET appuntamento_id = {$appuntamento_id},
                  importo = '{$importo}'
              WHERE pagamento_id = {$pagamento_id}
            ";
            if ($mysqli->query($sql_upd)) {
                $messaggio_form = '<div class="alert alert-success">Fatturazione aggiornata correttamente.</div>';
                // Reset campi
                $old_pagamento_id    = '';
                $old_appuntamento_id = '';
                $old_importo         = '';
                $label_submit        = 'Aggiungi';
            } else {
                $messaggio_form = '<div class="alert alert-danger">Errore aggiornamento: '
                                  . htmlspecialchars($mysqli->error, ENT_QUOTES, 'UTF-8') . '</div>';
            }
        } else {
            // INSERT
            $sql_ins = "
              INSERT INTO pagamenti (appuntamento_id, importo, pagato_il)
              VALUES ({$appuntamento_id}, '{$importo}', CURRENT_TIMESTAMP)
            ";
            if ($mysqli->query($sql_ins)) {
                $messaggio_form = '<div class="alert alert-success">Fatturazione aggiunta correttamente.</div>';
                // Reset campi
                $old_pagamento_id    = '';
                $old_appuntamento_id = '';
                $old_importo         = '';
            } else {
                $messaggio_form = '<div class="alert alert-danger">Errore inserimento: '
                                  . htmlspecialchars($mysqli->error, ENT_QUOTES, 'UTF-8') . '</div>';
            }
        }
    }
}

// 8. Recupero filtri da GET (per elenco Fatturazioni)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['data_inizio'])) {
        $data_inizio = esc($mysqli, $_GET['data_inizio']);
    }
    if (!empty($_GET['data_fine'])) {
        $data_fine = esc($mysqli, $_GET['data_fine']);
    }
    if (!empty($_GET['cliente'])) {
        $cliente_filt = esc($mysqli, $_GET['cliente']);
    }
    if (!empty($_GET['servizio_id'])) {
        $servizio_filt = intval($_GET['servizio_id']);
    }
}

// Ricostruisco dropdown Servizi con selezione attuale
$lista_servizi_filter = "<option value=\"\">-- Seleziona Servizio --</option>\n" 
                      . buildOptions($mysqli, 'servizi', 'servizio_id', 'nome', $servizio_filt);

// 9. Preparo dropdown Appuntamenti nella form (aggiungi/modifica)
$lista_appuntamenti = "<option value=\"\">-- Seleziona Appuntamento --</option>\n"
                    . buildAppuntamentiDropdown($mysqli, $old_appuntamento_id);

// 10. Costruisco clausole WHERE per filtro elenco
$where_clauses = [];

if ($data_inizio !== '') {
    $where_clauses[] = "DATE(p.pagato_il) >= '{$data_inizio}'";
}
if ($data_fine !== '') {
    $where_clauses[] = "DATE(p.pagato_il) <= '{$data_fine}'";
}
if ($cliente_filt !== '') {
    // Cerco in nome e cognome del cliente (da tabella richieste)
    $cf = esc($mysqli, $cliente_filt);
    $where_clauses[] = "(r.nome LIKE '%{$cf}%' OR r.cognome LIKE '%{$cf}%')";
}
if ($servizio_filt > 0) {
    $where_clauses[] = "a.servizio_id = {$servizio_filt}";
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// 11. Query per elenco Fatturazioni
$sql_list = "
  SELECT 
    p.pagamento_id,
    r.nome AS cliente_nome,
    r.cognome AS cliente_cognome,
    DATE_FORMAT(a.prenotato_il, '%Y-%m-%d %H:%i') AS dt_app,
    s.nome AS servizio_nome,
    FORMAT(p.importo, 2) AS importo
  FROM pagamenti AS p
  LEFT JOIN appuntamenti AS a 
    ON p.appuntamento_id = a.appuntamento_id
  LEFT JOIN richieste AS r 
    ON a.richiesta_id = r.richiesta_id
  LEFT JOIN servizi AS s 
    ON a.servizio_id = s.servizio_id
  {$where_sql}
  ORDER BY p.pagato_il DESC
";
if ($res = $mysqli->query($sql_list)) {
    while ($row = $res->fetch_assoc()) {
        $pid    = (int)$row['pagamento_id'];
        $cliente = htmlspecialchars($row['cliente_nome'] . ' ' . $row['cliente_cognome'], ENT_QUOTES, 'UTF-8');
        $dt_app  = htmlspecialchars($row['dt_app'], ENT_QUOTES, 'UTF-8');
        $serv    = htmlspecialchars($row['servizio_nome'], ENT_QUOTES, 'UTF-8');
        $imp     = htmlspecialchars($row['importo'], ENT_QUOTES, 'UTF-8');

        $link_delete = "index.php?page=fatturazioni&action=delete&id={$pid}";

        $lista_fatturazioni .= "
          <tr>
            <td>{$pid}</td>
            <td>{$cliente}</td>
            <td>{$dt_app}</td>
            <td>{$serv}</td>
            <td>{$imp}</td>
            <td>
              <a href=\"{$link_delete}\"
                 class=\"btn btn-outline-modern btn-xs text-danger\"
                 onclick=\"return confirm('Eliminare questa fatturazione?');\">
                <i class=\"fas fa-trash-alt me-1\"></i>Elimina
              </a>
            </td>
          </tr>
        ";
    }
    $res->free();
}

// 12. Caricamento del template fatturazioni.html
$template_path = __DIR__ . '/fatturazioni.html';
$template = file_get_contents($template_path);
if ($template === false) {
    die("Impossibile caricare il template fatturazioni.html");
}

// 13. Sostituzione dei placeholder
$output = str_replace(
    [
      '<[messaggio_form]>',
      '<[label_submit]>',
      '<[lista_appuntamenti]>',
      '<[old_importo]>',
      '<[old_pagamento_id]>',
      '<[lista_servizi]>',
      '<[lista_fatturazioni]>'
    ],
    [
      $messaggio_form,
      $label_submit,
      $lista_appuntamenti,
      $old_importo,
      htmlspecialchars($old_pagamento_id, ENT_QUOTES, 'UTF-8'),
      $lista_servizi_filter,
      $lista_fatturazioni
    ],
    $template
);

// 14. Output finale (HTML renderizzato)
echo $output;

// 15. Chiusura connessione
$mysqli->close();
?>
