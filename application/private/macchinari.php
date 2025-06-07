<?php
// macchinari.php
// Controller per macchinari.html

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

// 2. Inizializzo variabili per il template
$messaggio_form           = '';
$label_submit             = 'Aggiungi';
$old_macchinario_id       = '';
$old_nome_macchinario     = '';
$old_modello              = '';
$old_marca                = '';
$old_descrizione          = '';
$old_data_acquisto        = '';
$old_quantita             = '';
$old_stato                = '';
$lista_stati_macchinario  = '';
$lista_macchinari         = '';

// 3. Funzione di escape
function esc($mysqli, $val) {
    return $mysqli->real_escape_string(trim($val));
}

// 4. Costruisco dropdown “Stato” basato su valori ENUM della colonna stato
//    (Si presume che gli stati siano, ad esempio: 'Attivo','In Riparazione','Fuori Servizio')
function buildStatiOptions($mysqli, $selected = '') {
    $opts = "";
    // Leggo i possibili valori ENUM direttamente da INFORMATION_SCHEMA
    $sql_enum = "
      SELECT COLUMN_TYPE 
      FROM INFORMATION_SCHEMA.COLUMNS 
      WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'macchinari' 
        AND COLUMN_NAME = 'stato'
    ";
    if ($res = $mysqli->query($sql_enum)) {
        if ($row = $res->fetch_assoc()) {
            // Il tipo ENUM appare come: enum('Val1','Val2','Val3',...)
            $enumDef = $row['COLUMN_TYPE'];
            preg_match_all("/'([^']+)'/", $enumDef, $matches);
            foreach ($matches[1] as $val) {
                $v = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
                $sel = ($selected === $val) ? ' selected' : '';
                $opts .= "<option value=\"{$v}\"{$sel}>{$v}</option>\n";
            }
        }
        $res->free();
    }
    return $opts;
}

// 5. Gestione eliminazione (GET action=delete&id=...)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = intval($_GET['id']);
    if ($del_id > 0) {
        $sql_del = "DELETE FROM macchinari WHERE macchinario_id = {$del_id}";
        if ($mysqli->query($sql_del)) {
            $messaggio_form = '<div class="alert alert-success">Macchinario eliminato correttamente.</div>';
        } else {
            $messaggio_form = '<div class="alert alert-danger">Errore eliminazione: '
                              . htmlspecialchars($mysqli->error, ENT_QUOTES, 'UTF-8') . '</div>';
        }
    }
}

// 6. Se richiesta modifica (GET action=edit&id=...)
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    if ($edit_id > 0) {
        $sql_sel = "
          SELECT 
            macchinario_id,
            nome_macchinario,
            modello,
            marca,
            descrizione,
            data_acquisto,
            quantita,
            stato
          FROM macchinari
          WHERE macchinario_id = {$edit_id}
          LIMIT 1
        ";
        if ($res = $mysqli->query($sql_sel)) {
            if ($res->num_rows === 1) {
                $row = $res->fetch_assoc();
                $old_macchinario_id   = (int)$row['macchinario_id'];
                $old_nome_macchinario = htmlspecialchars($row['nome_macchinario'], ENT_QUOTES, 'UTF-8');
                $old_modello          = htmlspecialchars($row['modello'], ENT_QUOTES, 'UTF-8');
                $old_marca            = htmlspecialchars($row['marca'], ENT_QUOTES, 'UTF-8');
                $old_descrizione      = htmlspecialchars($row['descrizione'], ENT_QUOTES, 'UTF-8');
                $old_data_acquisto    = $row['data_acquisto'] ? htmlspecialchars($row['data_acquisto'], ENT_QUOTES, 'UTF-8') : '';
                $old_quantita         = (int)$row['quantita'];
                $old_stato            = $row['stato'];
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
    $macchinario_id     = intval($_POST['macchinario_id'] ?? 0);
    $nome_macchinario   = esc($mysqli, $_POST['nome_macchinario'] ?? '');
    $modello            = esc($mysqli, $_POST['modello'] ?? '');
    $marca              = esc($mysqli, $_POST['marca'] ?? '');
    $descrizione        = esc($mysqli, $_POST['descrizione'] ?? '');
    $data_acquisto      = $_POST['data_acquisto'] ?? '';
    $quantita           = intval($_POST['quantita'] ?? 0);
    $stato              = esc($mysqli, $_POST['stato'] ?? '');

    // Validazione di base
    if ($nome_macchinario === '' || $stato === '') {
        $messaggio_form = '<div class="alert alert-danger">I campi Nome Macchinario e Stato sono obbligatori.</div>';
        // Mantengo i valori nel form
        $old_macchinario_id   = $macchinario_id;
        $old_nome_macchinario = htmlspecialchars($nome_macchinario, ENT_QUOTES, 'UTF-8');
        $old_modello          = htmlspecialchars($modello, ENT_QUOTES, 'UTF-8');
        $old_marca            = htmlspecialchars($marca, ENT_QUOTES, 'UTF-8');
        $old_descrizione      = htmlspecialchars($descrizione, ENT_QUOTES, 'UTF-8');
        $old_data_acquisto    = htmlspecialchars($data_acquisto, ENT_QUOTES, 'UTF-8');
        $old_quantita         = $quantita;
        $old_stato            = $stato;
        $label_submit         = ($macchinario_id > 0 ? 'Modifica' : 'Aggiungi');
    } else {
        if ($macchinario_id > 0) {
            // UPDATE macchinario esistente
            $sql_upd = "
              UPDATE macchinari
              SET 
                nome_macchinario = '{$nome_macchinario}',
                modello          = '{$modello}',
                marca            = '{$marca}',
                descrizione      = '{$descrizione}',
                data_acquisto    = " . ($data_acquisto !== '' ? "'{$data_acquisto}'" : "NULL") . ",
                quantita         = {$quantita},
                stato            = '{$stato}'
              WHERE macchinario_id = {$macchinario_id}
            ";
            if ($mysqli->query($sql_upd)) {
                $messaggio_form = '<div class="alert alert-success">Macchinario aggiornato correttamente.</div>';
                // Reset campi
                $old_macchinario_id   = '';
                $old_nome_macchinario = '';
                $old_modello          = '';
                $old_marca            = '';
                $old_descrizione      = '';
                $old_data_acquisto    = '';
                $old_quantita         = '';
                $old_stato            = '';
                $label_submit         = 'Aggiungi';
            } else {
                $messaggio_form = '<div class="alert alert-danger">Errore aggiornamento: '
                                  . htmlspecialchars($mysqli->error, ENT_QUOTES, 'UTF-8') . '</div>';
            }
        } else {
            // INSERT nuovo macchinario
            $sql_ins = "
              INSERT INTO macchinari 
                (nome_macchinario, modello, marca, descrizione, data_acquisto, quantita, stato, creato_il)
              VALUES 
                ('{$nome_macchinario}',
                 '{$modello}',
                 '{$marca}',
                 '{$descrizione}',
                 " . ($data_acquisto !== '' ? "'{$data_acquisto}'" : "NULL") . ",
                 {$quantita},
                 '{$stato}',
                 CURRENT_TIMESTAMP)
            ";
            if ($mysqli->query($sql_ins)) {
                $messaggio_form = '<div class="alert alert-success">Macchinario aggiunto correttamente.</div>';
                // Reset campi
                $old_macchinario_id   = '';
                $old_nome_macchinario = '';
                $old_modello          = '';
                $old_marca            = '';
                $old_descrizione      = '';
                $old_data_acquisto    = '';
                $old_quantita         = '';
                $old_stato            = '';
            } else {
                $messaggio_form = '<div class="alert alert-danger">Errore inserimento: '
                                  . htmlspecialchars($mysqli->error, ENT_QUOTES, 'UTF-8') . '</div>';
            }
        }
    }
}

// 8. Costruisco dropdown “Stato” con selezione corrente
$lista_stati_macchinario = buildStatiOptions($mysqli, $old_stato);

// 9. Recupero elenco macchinari per la tabella
$sql_list = "
  SELECT 
    macchinario_id,
    nome_macchinario,
    modello,
    marca,
    descrizione,
    data_acquisto,
    quantita,
    stato,
    DATE_FORMAT(creato_il, '%Y-%m-%d %H:%i:%s') AS creato_il_formatted
  FROM macchinari
  ORDER BY creato_il DESC
";
if ($res = $mysqli->query($sql_list)) {
    while ($row = $res->fetch_assoc()) {
        $mid       = (int)$row['macchinario_id'];
        $nome      = htmlspecialchars($row['nome_macchinario'], ENT_QUOTES, 'UTF-8');
        $mod       = htmlspecialchars($row['modello'], ENT_QUOTES, 'UTF-8');
        $mar       = htmlspecialchars($row['marca'], ENT_QUOTES, 'UTF-8');
        $descr     = htmlspecialchars($row['descrizione'], ENT_QUOTES, 'UTF-8');
        $dataAcq   = $row['data_acquisto'] ? htmlspecialchars(substr($row['data_acquisto'], 0, 10), ENT_QUOTES, 'UTF-8') : '';
        $qty       = (int)$row['quantita'];
        $st        = htmlspecialchars($row['stato'], ENT_QUOTES, 'UTF-8');
        $creatoIl  = htmlspecialchars($row['creato_il_formatted'], ENT_QUOTES, 'UTF-8');

        $link_delete = "index.php?page=macchinari&action=delete&id={$mid}";

        $lista_macchinari .= "
          <tr>
            <td>{$nome}</td>
            <td>{$mod}</td>
            <td>{$mar}</td>
            <td>{$descr}</td>
            <td>{$dataAcq}</td>
            <td>{$qty}</td>
            <td>{$st}</td>
            <td>{$creatoIl}</td>
            <td>
              <a href=\"index.php?page=macchinari&action=edit&id={$mid}\"
                 class=\"btn btn-outline-modern btn-xs me-1\" title=\"Modifica\">
                <i class=\"fas fa-edit me-1\"></i>Modifica
              </a>
              <a href=\"{$link_delete}\"
                 class=\"btn btn-outline-modern btn-xs text-danger\" 
                 onclick=\"return confirm('Eliminare questo macchinario?');\">
                <i class=\"fas fa-trash-alt me-1\"></i>Elimina
              </a>
            </td>
          </tr>
        ";
    }
    $res->free();
}

// 10. Caricamento del template macchinari.html
$template_path = __DIR__ . '/macchinari.html';
$template = file_get_contents($template_path);
if ($template === false) {
    die("Impossibile caricare il template macchinari.html");
}

// 11. Sostituzione dei placeholder
$output = str_replace(
    [
      '<[messaggio_form]>',
      '<[label_submit]>',
      '<[old_nome_macchinario]>',
      '<[old_modello]>',
      '<[old_marca]>',
      '<[old_descrizione]>',
      '<[old_data_acquisto]>',
      '<[old_quantita]>',
      '<[lista_stati_macchinario]>',
      '<[old_macchinario_id]>',
      '<[lista_macchinari]>'
    ],
    [
      $messaggio_form,
      $label_submit,
      htmlspecialchars($old_nome_macchinario, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($old_modello, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($old_marca, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($old_descrizione, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($old_data_acquisto, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($old_quantita, ENT_QUOTES, 'UTF-8'),
      $lista_stati_macchinario,
      htmlspecialchars($old_macchinario_id, ENT_QUOTES, 'UTF-8'),
      $lista_macchinari
    ],
    $template
);

// 12. Output finale
echo $output;

// 13. Chiusura connessione
$mysqli->close();
?>
