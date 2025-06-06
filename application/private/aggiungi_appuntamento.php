<?php
// aggiungi_appuntamento.php
// Controller per aggiungi_appuntamento.html
// Flusso: crea prima la richiesta, poi l’appuntamento

$host     = 'localhost';
$username = 'TUO_USERNAME';
$password = 'TUA_PASSWORD';
$database = 'my_lazzarini21';

$mysqli = new mysqli($host, $username, $password, $database);
if ($mysqli->connect_errno) {
    die("Errore di connessione al database: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8");

// Inizializzo variabili per il template
$old_nome           = '';
$old_cognome        = '';
$old_email          = '';
$old_telefono       = '';
$old_data_nascita   = '';
$old_sesso          = '';
$old_servizio       = '';
$old_fascia         = '';
$old_data           = '';
$old_orario         = '';
$sala_selezionata   = '';

$sel_sesso_Maschio  = '';
$sel_sesso_Femmina  = '';
$sel_sesso_Altro    = '';

$lista_servizi        = '';
$lista_fasi_disponibili = '';
$lista_sale          = '';
$messaggio_form      = '';

// Funzione di escape
function esc($mysqli, $val) {
    return $mysqli->real_escape_string(trim($val));
}

// Costruisco dropdown
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
// Popolo dropdown iniziali senza selezione
$lista_servizi         = buildOptions($mysqli, 'servizi', 'servizio_id', 'nome', '');
$lista_fasi_disponibili = buildOptions($mysqli, 'fasce_disponibilita', 'fascia_id', 'inizio', '');
$lista_sale            = buildOptions($mysqli, 'sale', 'sala_id', 'nome_sala', '');

// 1) Se POST action=save, creo richiesta e appuntamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' 
    && isset($_GET['action']) 
    && $_GET['action'] === 'save') 
{
    $nome            = esc($mysqli, $_POST['nome'] ?? '');
    $cognome         = esc($mysqli, $_POST['cognome'] ?? '');
    $email           = esc($mysqli, $_POST['email'] ?? '');
    $telefono        = esc($mysqli, $_POST['telefono'] ?? '');
    $data_nascita    = esc($mysqli, $_POST['data_nascita'] ?? '');
    $sesso           = esc($mysqli, $_POST['sesso'] ?? '');
    $servizio_id     = intval($_POST['servizio_id'] ?? 0);
    $fascia_id       = intval($_POST['fascia_id'] ?? 0);

    $app_data        = esc($mysqli, $_POST['data'] ?? '');
    $app_orario      = esc($mysqli, $_POST['orario'] ?? '');
    $sala_id         = intval($_POST['sala_id'] ?? 0);

    // Validazioni
    if ($nome === '' || $cognome === '' || $email === '' 
        || $servizio_id === 0 || $fascia_id === 0 
        || $app_data === '' || $app_orario === '' || $sala_id === 0) 
    {
        $messaggio_form = '<div class="alert alert-danger">'
                       . 'Compila tutti i campi obbligatori.</div>';
        // Mantengo i valori per ricaricarli nel form
        $old_nome         = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $old_cognome      = htmlspecialchars($cognome, ENT_QUOTES, 'UTF-8');
        $old_email        = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $old_telefono     = htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8');
        $old_data_nascita = $data_nascita;
        $old_sesso        = $sesso;
        $old_servizio     = $servizio_id;
        $old_fascia       = $fascia_id;
        $old_data         = $app_data;
        $old_orario       = $app_orario;
        $sala_selezionata = $sala_id;
    } else {
        // 1.1) Inserimento in richieste
        $sql_ins_req = "
          INSERT INTO `richieste`
            (`nome`, `cognome`, `email`, `telefono`,
             `data_nascita`, `sesso`, `servizio_id`, `fascia_id`, `creato_il`)
          VALUES
            ('{$nome}', '{$cognome}', '{$email}', '{$telefono}',
             " . ($data_nascita !== '' ? "'{$data_nascita}'" : "NULL") . ",
             " . ($sesso !== '' ? "'{$sesso}'" : "NULL") . ",
             {$servizio_id}, {$fascia_id}, CURRENT_TIMESTAMP)
        ";
        if (!$mysqli->query($sql_ins_req)) {
            $messaggio_form = '<div class="alert alert-danger">'
                           . 'Errore inserimento richiesta: ' 
                           . $mysqli->error . '</div>';
        } else {
            $new_richiesta_id = $mysqli->insert_id;

            // 1.2) Inserimento in appuntamenti con il nuovo richiesta_id
            $prenotato_il = $app_data . ' ' . $app_orario . ':00';
            $stato        = 'Prenotato';

            $sql_ins_app = "
              INSERT INTO `appuntamenti`
                (`richiesta_id`, `fisioterapista_id`, `servizio_id`, `fascia_id`, `stato`, `prenotato_il`, `sala_id`)
              VALUES
                ({$new_richiesta_id}, 0, {$servizio_id}, {$fascia_id}, '{$stato}', '{$prenotato_il}', {$sala_id})
            ";
            if (!$mysqli->query($sql_ins_app)) {
                $messaggio_form = '<div class="alert alert-danger">'
                               . 'Errore inserimento appuntamento: ' 
                               . $mysqli->error . '</div>';
            } else {
                $messaggio_form = '<div class="alert alert-success">'
                               . 'Appuntamento creato correttamente!</div>';
                // Reset form
                $old_nome         = '';
                $old_cognome      = '';
                $old_email        = '';
                $old_telefono     = '';
                $old_data_nascita = '';
                $old_sesso        = '';
                $old_servizio     = '';
                $old_fascia       = '';
                $old_data         = '';
                $old_orario       = '';
                $sala_selezionata = '';
            }
        }
    }

    // Ricostruisco i dropdown con eventuali selezioni rimaste
    $lista_servizi          = buildOptions($mysqli, 'servizi', 'servizio_id', 'nome', $old_servizio);
    $lista_fasi_disponibili = buildOptions($mysqli, 'fasce_disponibilita', 'fascia_id', 'inizio', $old_fascia);
    $lista_sale             = buildOptions($mysqli, 'sale', 'sala_id', 'nome_sala', $sala_selezionata);

    // Imposto selezione sesso
    $sel_sesso_Maschio  = ($old_sesso === 'Maschio') ? 'selected' : '';
    $sel_sesso_Femmina  = ($old_sesso === 'Femmina') ? 'selected' : '';
    $sel_sesso_Altro    = ($old_sesso === 'Altro') ? 'selected' : '';
}

// 2. Caricamento template e sostituzione placeholder
$template = file_get_contents(__DIR__ . '/aggiungi_appuntamento.html');
if ($template === false) {
    die("Impossibile caricare il template aggiungi_appuntamento.html");
}

$output = str_replace(
    [
      '<[messaggio_form]>',
      '<[old_nome]>',
      '<[old_cognome]>',
      '<[old_email]>',
      '<[old_telefono]>',
      '<[old_data_nascita]>',
      '<[sel_sesso_Maschio]>',
      '<[sel_sesso_Femmina]>',
      '<[sel_sesso_Altro]>',
      '<[lista_servizi]>',
      '<[old_servizio]>',
      '<[lista_fasce]>',
      '<[old_fascia]>',
      '<[old_data]>',
      '<[old_orario]>',
      '<[lista_sale]>',
    ],
    [
      $messaggio_form,
      htmlspecialchars($old_nome, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($old_cognome, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($old_email, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($old_telefono, ENT_QUOTES, 'UTF-8'),
      $old_data_nascita,
      $sel_sesso_Maschio,
      $sel_sesso_Femmina,
      $sel_sesso_Altro,
      $lista_servizi,
      $old_servizio,
      $lista_fasi_disponibili,
      $old_fascia,
      $old_data,
      $old_orario,
      $lista_sale,
    ],
    $template
);

echo $output;
$mysqli->close();
?>
