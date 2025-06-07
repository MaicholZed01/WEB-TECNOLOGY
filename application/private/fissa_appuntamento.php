<?php
// fissa_appuntamento.php
// Controller per fissa_appuntamento.html

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

// 2. Inizializzo variabili per il template
$richiesta_id             = '';
$richiesta_nome           = '';
$richiesta_cognome        = '';
$richiesta_email          = '';
$richiesta_telefono       = '';
$richiesta_data_nascita   = '';
$richiesta_sesso          = '';
$richiesta_servizio       = '';
$richiesta_fisioterapista = '';
$richiesta_data_preferita = '';
$richiesta_fascia         = '';
$old_data                 = '';
$old_orario               = '';

$lista_sale      = '';
$messaggio_form  = '';

// Funzione di escape
function esc($mysqli, $val) {
    return $mysqli->real_escape_string(trim($val));
}

// Costruisco dropdown "sale"
function buildOptions($mysqli, $table, $idCol, $nameCol, $selected = '') {
    $opt = "";
    $sql = "SELECT `$idCol`, `$nameCol` FROM `$table` ORDER BY `$nameCol` ASC";
    if ($res = $mysqli->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $id   = $row[$idCol];
            $nome = htmlspecialchars($row[$nameCol], ENT_QUOTES, 'UTF-8');
            $sel  = ($selected !== '' && $selected == $id) ? ' selected' : '';
            $opt .= "<option value=\"{$id}\"{$sel}>{$nome}</option>\n";
        }
        $res->free();
    }
    return $opt;
}
$lista_sale = buildOptions($mysqli, 'sale', 'sala_id', 'nome_sala', '');

// 3. Se in GET con richiesta_id, carico i dati della richiesta
if ($_SERVER['REQUEST_METHOD'] === 'GET' 
    && isset($_GET['richiesta_id']) 
    && intval($_GET['richiesta_id']) > 0) 
{
    $rid = intval($_GET['richiesta_id']);
    $sql = "
      SELECT 
        r.richiesta_id,
        r.nome, r.cognome, r.email, r.telefono,
        r.data_nascita, r.sesso,
        s.nome      AS servizio,
        f.nome      AS fisioterapista,
        r.data_preferita, r.fascia_id
      FROM richieste AS r
      LEFT JOIN servizi AS s ON r.servizio_id = s.servizio_id
      LEFT JOIN fisioterapisti AS f ON r.fisioterapista_id = f.fisioterapista_id
      WHERE r.richiesta_id = {$rid}
      LIMIT 1
    ";
    if ($res = $mysqli->query($sql)) {
        if ($res->num_rows === 1) {
            $row = $res->fetch_assoc();
            $richiesta_id             = (int)$row['richiesta_id'];
            $richiesta_nome           = htmlspecialchars($row['nome'], ENT_QUOTES, 'UTF-8');
            $richiesta_cognome        = htmlspecialchars($row['cognome'], ENT_QUOTES, 'UTF-8');
            $richiesta_email          = htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8');
            $richiesta_telefono       = htmlspecialchars($row['telefono'], ENT_QUOTES, 'UTF-8');
            $richiesta_data_nascita   = $row['data_nascita'] ?? '';
            $richiesta_sesso          = $row['sesso'] ?? '';
            $richiesta_servizio       = htmlspecialchars($row['servizio'], ENT_QUOTES, 'UTF-8');
            $richiesta_fisioterapista = htmlspecialchars($row['fisioterapista'], ENT_QUOTES, 'UTF-8');
            $richiesta_data_preferita = $row['data_preferita'] ?? '';
            $richiesta_fascia         = (int)$row['fascia_id'];
        }
        $res->free();
    }
}

// 4. Se POST action=save, creo l’appuntamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' 
    && isset($_GET['action']) 
    && $_GET['action'] === 'save') 
{
    $richiesta_id_post   = intval($_POST['richiesta_id'] ?? 0);
    $app_data            = esc($mysqli, $_POST['data'] ?? '');
    $app_orario          = esc($mysqli, $_POST['orario'] ?? '');
    $sala_id             = intval($_POST['sala_id'] ?? 0);

    if ($richiesta_id_post <= 0 || $app_data === '' || $app_orario === '' || $sala_id === 0) {
        $messaggio_form = '<div class="alert alert-danger">Dati mancanti per creare l’appuntamento.</div>';
    } else {
        // Combino data + orario
        $prenotato_il = $app_data . ' ' . $app_orario . ':00';
        $stato        = 'Prenotato';

        // Inserisco in appuntamenti copiando i dati dalla richiesta
        $sql_ins = "
          INSERT INTO appuntamenti 
            (richiesta_id, fisioterapista_id, servizio_id, fascia_id, stato, prenotato_il, sala_id)
          SELECT 
            r.richiesta_id,
            COALESCE(r.fisioterapista_id, 0) AS fisioterapista_id,
            r.servizio_id,
            r.fascia_id,
            '{$stato}',
            '{$prenotato_il}',
            {$sala_id}
          FROM richieste AS r
          WHERE r.richiesta_id = {$richiesta_id_post}
          LIMIT 1
        ";
        if ($mysqli->query($sql_ins)) {
            $messaggio_form = '<div class="alert alert-success">Appuntamento fissato correttamente!</div>';
            // Reset campi
            $richiesta_id             = '';
            $richiesta_nome           = '';
            $richiesta_cognome        = '';
            $richiesta_email          = '';
            $richiesta_telefono       = '';
            $richiesta_data_nascita   = '';
            $richiesta_sesso          = '';
            $richiesta_servizio       = '';
            $richiesta_fisioterapista = '';
            $richiesta_data_preferita = '';
            $richiesta_fascia         = '';
            $old_data                 = '';
            $old_orario               = '';
            // Ricostruisco dropdown sale senza selezione
            $lista_sale = buildOptions($mysqli, 'sale', 'sala_id', 'nome_sala', '');
        } else {
            $messaggio_form = '<div class="alert alert-danger">Errore inserimento appuntamento: ' 
                              . $mysqli->error . '</div>';
        }
    }
}

// 5. Caricamento del template e sostituzione dei placeholder
$template = file_get_contents(__DIR__ . '/fissa_appuntamento.html');
if ($template === false) {
    die("Impossibile caricare il template fissa_appuntamento.html");
}
$output = str_replace(
    [
      '<[messaggio_form]>',
      '<[richiesta_id]>',
      '<[richiesta_nome]>',
      '<[richiesta_cognome]>',
      '<[richiesta_email]>',
      '<[richiesta_telefono]>',
      '<[richiesta_data_nascita]>',
      '<[richiesta_sesso]>',
      '<[richiesta_servizio]>',
      '<[richiesta_fisioterapista]>',
      '<[richiesta_data_preferita]>',
      '<[richiesta_fascia]>',
      '<[old_data]>',
      '<[old_orario]>',
      '<[lista_sale]>',
    ],
    [
      $messaggio_form,
      htmlspecialchars($richiesta_id, ENT_QUOTES, 'UTF-8'),
      $richiesta_nome,
      $richiesta_cognome,
      $richiesta_email,
      $richiesta_telefono,
      $richiesta_data_nascita,
      $richiesta_sesso,
      $richiesta_servizio,
      $richiesta_fisioterapista,
      $richiesta_data_preferita,
      $richiesta_fascia,
      $old_data,
      $old_orario,
      $lista_sale,
    ],
    $template
);
echo $output;

$mysqli->close();
?>
