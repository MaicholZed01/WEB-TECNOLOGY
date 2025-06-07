<?php
// richieste.php
// Controller per richieste.html

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

// 2. Inizializzo variabili e messaggio di esito
$messaggio_form   = '';
$lista_servizi    = '';
$lista_richieste  = '';

$data_inizio   = '';
$data_fine     = '';
$nome_filter   = '';
$cognome_filter = '';
$servizio_filter = 0;

// Funzione di escape
function esc($mysqli, $val) {
    return $mysqli->real_escape_string(trim($val));
}

// 3. Popolo dropdown servizi per il filtro
function buildOptions($mysqli, $selectedValue = '') {
    $opts = "";
    $sql = "SELECT servizio_id, nome FROM servizi ORDER BY nome ASC";
    if ($res = $mysqli->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $id   = (int)$row['servizio_id'];
            $nome = htmlspecialchars($row['nome'], ENT_QUOTES, 'UTF-8');
            $sel  = ($selectedValue != '' && $selectedValue == $id) ? ' selected' : '';
            $opts .= "<option value=\"{$id}\"{$sel}>{$nome}</option>\n";
        }
        $res->free();
    }
    return $opts;
}

// 4. Gestione eliminazione richiesta
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = intval($_GET['id']);
    if ($del_id > 0) {
        $sql_del = "DELETE FROM richieste WHERE richiesta_id = {$del_id}";
        if ($mysqli->query($sql_del)) {
            $messaggio_form = '<div class="alert alert-success">Richiesta eliminata correttamente.</div>';
        } else {
            $messaggio_form = '<div class="alert alert-danger">Errore eliminazione richiesta: '
                              . htmlspecialchars($mysqli->error, ENT_QUOTES, 'UTF-8') . '</div>';
        }
    }
}

// 5. Recupero filtri da GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['data_inizio'])) {
        $data_inizio = esc($mysqli, $_GET['data_inizio']);
    }
    if (isset($_GET['data_fine'])) {
        $data_fine = esc($mysqli, $_GET['data_fine']);
    }
    if (isset($_GET['nome'])) {
        $nome_filter = esc($mysqli, $_GET['nome']);
    }
    if (isset($_GET['cognome'])) {
        $cognome_filter = esc($mysqli, $_GET['cognome']);
    }
    if (isset($_GET['servizio_id'])) {
        $servizio_filter = intval($_GET['servizio_id']);
    }
}

// Ricostruisco dropdown servizi con selezione corrente
$lista_servizi = buildOptions($mysqli, $servizio_filter);

// 6. Costruisco query per l’elenco delle richieste con filtri
$where_clauses = [];
if ($data_inizio !== '') {
    $where_clauses[] = "DATE(r.creato_il) >= '{$data_inizio}'";
}
if ($data_fine !== '') {
    $where_clauses[] = "DATE(r.creato_il) <= '{$data_fine}'";
}
if ($nome_filter !== '') {
    $where_clauses[] = "r.nome LIKE '%{$nome_filter}%'";
}
if ($cognome_filter !== '') {
    $where_clauses[] = "r.cognome LIKE '%{$cognome_filter}%'";
}
if ($servizio_filter > 0) {
    $where_clauses[] = "r.servizio_id = {$servizio_filter}";
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Query di selezione con join sui servizi e fasce
$sql_richieste = "
  SELECT
    r.richiesta_id,
    r.creato_il,
    r.nome,
    r.cognome,
    r.email,
    r.telefono,
    r.sesso,
    r.data_nascita,
    s.nome AS servizio_nome,
    r.data_preferita,
    fd.inizio AS fascia_inizio,
    fd.fine   AS fascia_fine
  FROM richieste AS r
  LEFT JOIN servizi AS s
    ON r.servizio_id = s.servizio_id
  LEFT JOIN fasce_disponibilita AS fd
    ON r.fascia_id = fd.fascia_id
  {$where_sql}
  ORDER BY r.creato_il DESC
";

$res = $mysqli->query($sql_richieste);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $rid        = (int)$row['richiesta_id'];
        $creato_il  = date('d/m/Y H:i', strtotime($row['creato_il']));
        $nome       = htmlspecialchars($row['nome'], ENT_QUOTES, 'UTF-8');
        $cognome    = htmlspecialchars($row['cognome'], ENT_QUOTES, 'UTF-8');
        $email      = htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8');
        $telefono   = htmlspecialchars($row['telefono'], ENT_QUOTES, 'UTF-8');
        $sesso      = htmlspecialchars($row['sesso'], ENT_QUOTES, 'UTF-8');
        $data_nasc  = $row['data_nascita'] ? htmlspecialchars($row['data_nascita'], ENT_QUOTES, 'UTF-8') : '';
        $servizio   = htmlspecialchars($row['servizio_nome'], ENT_QUOTES, 'UTF-8');
        $data_pref  = $row['data_preferita'] ? htmlspecialchars($row['data_preferita'], ENT_QUOTES, 'UTF-8') : '';
        $fascia     = '';
        if (!empty($row['fascia_inizio']) && !empty($row['fascia_fine'])) {
            $fascia = substr($row['fascia_inizio'], 0, 5) . ' – ' . substr($row['fascia_fine'], 0, 5);
        }

        // Costruisco link Azioni: Aggiungi (fissa_appuntamento) e Elimina (richieste&action=delete)
        $link_aggiungi = "index.php?page=fissa_appuntamento&richiesta_id={$rid}";
        $link_elimina  = "index.php?page=richieste&action=delete&id={$rid}";

        $lista_richieste .= "
          <tr>
            <td>{$creato_il}</td>
            <td>{$nome}</td>
            <td>{$cognome}</td>
            <td>{$email}</td>
            <td>{$telefono}</td>
            <td>{$sesso}</td>
            <td>{$data_nasc}</td>
            <td>{$servizio}</td>
            <td>{$data_pref}</td>
            <td>{$fascia}</td>
            <td>
              <a href=\"{$link_aggiungi}\"
                 class=\"btn btn-outline-modern btn-xs text-success\">
                <i class=\"fas fa-plus me-1\"></i>Aggiungi
              </a>
              <a href=\"{$link_elimina}\"
                 class=\"btn btn-outline-modern btn-xs text-danger\"
                 onclick=\"return confirm('Eliminare questa richiesta?');\">
                <i class=\"fas fa-trash-alt me-1\"></i>Elimina
              </a>
            </td>
          </tr>
        ";
    }
    $res->free();
}

// 7. Caricamento del template richieste.html
$template_path = __DIR__ . '/richieste.html';
$template = file_get_contents($template_path);
if ($template === false) {
    die("Impossibile caricare il template richieste.html");
}

// 8. Sostituzione dei placeholder
$output = str_replace(
    [
      '<[messaggio_form]>',
      '<[lista_servizi]>',
      '<[lista_richieste]>'
    ],
    [
      $messaggio_form,
      $lista_servizi,
      $lista_richieste
    ],
    $template
);

// 9. Output finale (HTML renderizzato)
echo $output;

// 10. Chiusura connessione
$mysqli->close();
?>
