<?php
// servizi.php
// Controller per servizi.html

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
$messaggio_form       = '';
$label_submit         = 'Aggiungi';
$old_servizio_id      = '';
$old_nome             = '';
$old_descrizione      = '';
$old_durata_minuti    = '';
$old_prezzo_base      = '';
$lista_servizi        = '';

// Funzione di escape
function esc($mysqli, $val) {
    return $mysqli->real_escape_string(trim($val));
}

// 3. Gestione eliminazione (GET action=delete&id=...)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = intval($_GET['id']);
    if ($del_id > 0) {
        $sql_del = "DELETE FROM servizi WHERE servizio_id = {$del_id}";
        if ($mysqli->query($sql_del)) {
            $messaggio_form = '<div class="alert alert-success">Servizio eliminato correttamente.</div>';
        } else {
            $messaggio_form = '<div class="alert alert-danger">Errore eliminazione: '
                              . htmlspecialchars($mysqli->error, ENT_QUOTES, 'UTF-8') . '</div>';
        }
    }
}

// 4. Gestione modifica (GET action=edit&id=...)
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    if ($edit_id > 0) {
        $sql_sel = "
          SELECT 
            servizio_id,
            nome,
            descrizione,
            durata_minuti,
            prezzo_base
          FROM servizi
          WHERE servizio_id = {$edit_id}
          LIMIT 1
        ";
        if ($res = $mysqli->query($sql_sel)) {
            if ($res->num_rows === 1) {
                $row = $res->fetch_assoc();
                $old_servizio_id   = (int)$row['servizio_id'];
                $old_nome          = htmlspecialchars($row['nome'], ENT_QUOTES, 'UTF-8');
                $old_descrizione   = htmlspecialchars($row['descrizione'], ENT_QUOTES, 'UTF-8');
                $old_durata_minuti = (int)$row['durata_minuti'];
                $old_prezzo_base   = htmlspecialchars($row['prezzo_base'], ENT_QUOTES, 'UTF-8');
                $label_submit      = 'Modifica';
            }
            $res->free();
        }
    }
}

// 5. Gestione salvataggio (POST action=save)
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_GET['action'])
    && $_GET['action'] === 'save') 
{
    $servizio_id      = intval($_POST['servizio_id'] ?? 0);
    $nome             = esc($mysqli, $_POST['nome'] ?? '');
    $descrizione      = esc($mysqli, $_POST['descrizione'] ?? '');
    $durata_minuti    = intval($_POST['durata_minuti'] ?? 0);
    $prezzo_base      = esc($mysqli, $_POST['prezzo_base'] ?? '');

    // Validazione di base
    if ($nome === '' || $durata_minuti <= 0 || $prezzo_base === '') {
        $messaggio_form = '<div class="alert alert-danger">
            I campi Nome, Durata e Prezzo Base sono obbligatori, e Durata deve essere maggiore di zero.
          </div>';
        // Mantengo i valori nel form
        $old_servizio_id   = $servizio_id;
        $old_nome          = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $old_descrizione   = htmlspecialchars($descrizione, ENT_QUOTES, 'UTF-8');
        $old_durata_minuti = $durata_minuti;
        $old_prezzo_base   = htmlspecialchars($prezzo_base, ENT_QUOTES, 'UTF-8');
        $label_submit      = ($servizio_id > 0 ? 'Modifica' : 'Aggiungi');
    } else {
        if ($servizio_id > 0) {
            // UPDATE servizio esistente
            $sql_upd = "
              UPDATE servizi
              SET 
                nome          = '{$nome}',
                descrizione   = '{$descrizione}',
                durata_minuti = {$durata_minuti},
                prezzo_base   = '{$prezzo_base}'
              WHERE servizio_id = {$servizio_id}
            ";
            if ($mysqli->query($sql_upd)) {
                $messaggio_form = '<div class="alert alert-success">
                    Servizio aggiornato correttamente.
                  </div>';
                // Reset campi
                $old_servizio_id   = '';
                $old_nome          = '';
                $old_descrizione   = '';
                $old_durata_minuti = '';
                $old_prezzo_base   = '';
                $label_submit      = 'Aggiungi';
            } else {
                $messaggio_form = '<div class="alert alert-danger">
                    Errore aggiornamento: '
                    . htmlspecialchars($mysqli->error, ENT_QUOTES, 'UTF-8') . '
                  </div>';
            }
        } else {
            // INSERT nuovo servizio
            $sql_ins = "
              INSERT INTO servizi (nome, descrizione, durata_minuti, prezzo_base, creato_il)
              VALUES (
                '{$nome}',
                '{$descrizione}',
                {$durata_minuti},
                '{$prezzo_base}',
                CURRENT_TIMESTAMP
              )
            ";
            if ($mysqli->query($sql_ins)) {
                $messaggio_form = '<div class="alert alert-success">
                    Servizio aggiunto correttamente.
                  </div>';
                // Reset campi
                $old_servizio_id   = '';
                $old_nome          = '';
                $old_descrizione   = '';
                $old_durata_minuti = '';
                $old_prezzo_base   = '';
            } else {
                $messaggio_form = '<div class="alert alert-danger">
                    Errore inserimento: '
                    . htmlspecialchars($mysqli->error, ENT_QUOTES, 'UTF-8') . '
                  </div>';
            }
        }
    }
}

// 6. Costruisco elenco servizi per la tabella
$sql_list = "
  SELECT 
    servizio_id,
    nome,
    descrizione,
    durata_minuti,
    prezzo_base,
    DATE_FORMAT(creato_il, '%Y-%m-%d %H:%i:%s') AS creato_il_formatted
  FROM servizi
  ORDER BY creato_il DESC
";
if ($res = $mysqli->query($sql_list)) {
    while ($row = $res->fetch_assoc()) {
        $sid        = (int)$row['servizio_id'];
        $nome_s     = htmlspecialchars($row['nome'], ENT_QUOTES, 'UTF-8');
        $descr_s    = htmlspecialchars($row['descrizione'], ENT_QUOTES, 'UTF-8');
        $durata     = (int)$row['durata_minuti'];
        $prezzo     = htmlspecialchars($row['prezzo_base'], ENT_QUOTES, 'UTF-8');
        $creato_il  = htmlspecialchars($row['creato_il_formatted'], ENT_QUOTES, 'UTF-8');

        $link_edit   = "index.php?page=servizi&action=edit&id={$sid}";
        $link_delete = "index.php?page=servizi&action=delete&id={$sid}";

        $lista_servizi .= "
          <tr>
            <td>{$nome_s}</td>
            <td>{$descr_s}</td>
            <td>{$durata}</td>
            <td>{$prezzo}</td>
            <td>{$creato_il}</td>
            <td>
              <a href=\"{$link_edit}\"
                 class=\"btn btn-primary-modern btn-xs me-1\">
                <i class=\"fas fa-edit me-1\"></i>Modifica
              </a>
              <a href=\"{$link_delete}\"
                 class=\"btn btn-outline-modern btn-xs text-danger\"
                 onclick=\"return confirm('Eliminare questo servizio?');\">
                <i class=\"fas fa-trash-alt me-1\"></i>Elimina
              </a>
            </td>
          </tr>
        ";
    }
    $res->free();
}

// 7. Caricamento del template servizi.html
$template_path = __DIR__ . '/servizi.html';
$template = file_get_contents($template_path);
if ($template === false) {
    die("Impossibile caricare il template servizi.html");
}

// 8. Sostituzione dei placeholder
$output = str_replace(
    [
      '<[messaggio_form]>',
      '<[label_submit]>',
      '<[old_nome]>',
      '<[old_descrizione]>',
      '<[old_durata_minuti]>',
      '<[old_prezzo_base]>',
      '<[old_servizio_id]>',
      '<[lista_servizi]>'
    ],
    [
      $messaggio_form,
      $label_submit,
      htmlspecialchars($old_nome, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($old_descrizione, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($old_durata_minuti, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($old_prezzo_base, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($old_servizio_id, ENT_QUOTES, 'UTF-8'),
      $lista_servizi
    ],
    $template
);

// 9. Output finale (HTML renderizzato)
echo $output;

// 10. Chiusura connessione
$mysqli->close();
?>
