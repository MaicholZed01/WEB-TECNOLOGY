<?php
// avvisi2.php
// Controller per avvisi2.html

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
$messaggio_form     = '';
$label_submit       = 'Aggiungi';     // di default
$old_annuncio_id    = '';
$old_titolo         = '';
$old_contenuto      = '';
$lista_annunci      = '';

// Funzione di escape
function esc($mysqli, $val) {
    return $mysqli->real_escape_string(trim($val));
}

// 3. Gestione eliminazione avviso
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = intval($_GET['id']);
    if ($del_id > 0) {
        $sql_del = "DELETE FROM annunci WHERE annuncio_id = {$del_id}";
        if ($mysqli->query($sql_del)) {
            $messaggio_form = '<div class="alert alert-success">Avviso eliminato correttamente.</div>';
        } else {
            $messaggio_form = '<div class="alert alert-danger">Errore eliminazione avviso: '
                              . htmlspecialchars($mysqli->error, ENT_QUOTES, 'UTF-8') . '</div>';
        }
    }
}

// 4. Se viene richiesta la modifica (GET action=edit&id=...)
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    if ($edit_id > 0) {
        $sql_sel = "
          SELECT 
            a.annuncio_id,
            a.titolo,
            a.contenuto,
            f.nome AS fisioterapista_nome
          FROM annunci AS a
          LEFT JOIN fisioterapisti AS f 
            ON a.fisioterapista_id = f.fisioterapista_id
          WHERE a.annuncio_id = {$edit_id}
          LIMIT 1
        ";
        if ($res = $mysqli->query($sql_sel)) {
            if ($res->num_rows === 1) {
                $row = $res->fetch_assoc();
                $old_annuncio_id  = (int)$row['annuncio_id'];
                $old_titolo       = htmlspecialchars($row['titolo'], ENT_QUOTES, 'UTF-8');
                $old_contenuto    = htmlspecialchars($row['contenuto'], ENT_QUOTES, 'UTF-8');
                $label_submit     = 'Modifica';
            }
            $res->free();
        }
    }
}

// 5. Gestione salvataggio (inserimento o aggiornamento)
if ($_SERVER['REQUEST_METHOD'] === 'POST' 
    && isset($_GET['action']) 
    && $_GET['action'] === 'save') 
{
    // Prelevo dati dal form
    $annuncio_id = intval($_POST['annuncio_id'] ?? 0);
    $titolo      = esc($mysqli, $_POST['titolo'] ?? '');
    $contenuto   = esc($mysqli, $_POST['contenuto'] ?? '');

    // Validazione minima
    if ($titolo === '') {
        $messaggio_form = '<div class="alert alert-danger">Il titolo è obbligatorio.</div>';
        // Se venivo da edit, mantengo label "Modifica"
        if ($annuncio_id > 0) {
            $label_submit = 'Modifica';
            $old_annuncio_id = $annuncio_id;
            $old_titolo      = htmlspecialchars($titolo, ENT_QUOTES, 'UTF-8');
            $old_contenuto   = htmlspecialchars($contenuto, ENT_QUOTES, 'UTF-8');
        }
    } else {
        if ($annuncio_id > 0) {
            // UPDATE avviso esistente
            $sql_upd = "
              UPDATE annunci
              SET titolo = '{$titolo}', contenuto = '{$contenuto}'
              WHERE annuncio_id = {$annuncio_id}
            ";
            if ($mysqli->query($sql_upd)) {
                $messaggio_form = '<div class="alert alert-success">Avviso aggiornato correttamente.</div>';
            } else {
                $messaggio_form = '<div class="alert alert-danger">Errore aggiornamento: '
                                  . htmlspecialchars($mysqli->error, ENT_QUOTES, 'UTF-8') . '</div>';
                $label_submit     = 'Modifica';
                $old_annuncio_id  = $annuncio_id;
                $old_titolo       = htmlspecialchars($titolo, ENT_QUOTES, 'UTF-8');
                $old_contenuto    = htmlspecialchars($contenuto, ENT_QUOTES, 'UTF-8');
            }
        } else {
            // INSERT nuovo avviso
            // Consideriamo che l’ID del fisioterapista loggato è in sessione
            $fisioterapista_id = intval($_SESSION['fisioterapista_id'] ?? 0);

            $sql_ins = "
              INSERT INTO annunci (fisioterapista_id, titolo, contenuto, pubblicato_il)
              VALUES ({$fisioterapista_id}, '{$titolo}', '{$contenuto}', CURRENT_TIMESTAMP)
            ";
            if ($mysqli->query($sql_ins)) {
                $messaggio_form = '<div class="alert alert-success">Avviso aggiunto correttamente.</div>';
                // Ripristino form vuoto
                $titolo    = '';
                $contenuto = '';
            } else {
                $messaggio_form = '<div class="alert alert-danger">Errore inserimento: '
                                  . htmlspecialchars($mysqli->error, ENT_QUOTES, 'UTF-8') . '</div>';
            }
        }
    }
}

// 6. Recupero elenco avvisi per la tabella
$sql_list = "
  SELECT 
    a.annuncio_id,
    f.nome AS fisioterapista_nome,
    a.titolo,
    a.contenuto,
    a.pubblicato_il
  FROM annunci AS a
  LEFT JOIN fisioterapisti AS f
    ON a.fisioterapista_id = f.fisioterapista_id
  ORDER BY a.pubblicato_il DESC
";
if ($res = $mysqli->query($sql_list)) {
    while ($row = $res->fetch_assoc()) {
        $aid    = (int)$row['annuncio_id'];
        $fisio  = htmlspecialchars($row['fisioterapista_nome'], ENT_QUOTES, 'UTF-8');
        $tit    = htmlspecialchars($row['titolo'], ENT_QUOTES, 'UTF-8');
        $cont   = htmlspecialchars($row['contenuto'], ENT_QUOTES, 'UTF-8');
        $pubb   = date('d/m/Y H:i', strtotime($row['pubblicato_il']));

        $link_delete = "index.php?page=avvisi2&action=delete&id={$aid}";

        $lista_annunci .= "
          <tr>
            <td>{$aid}</td>
            <td>{$fisio}</td>
            <td>{$tit}</td>
            <td>{$cont}</td>
            <td>{$pubb}</td>
            <td>
              <a href=\"{$link_delete}\"
                 class=\"btn btn-outline-modern btn-xs text-danger\"
                 onclick=\"return confirm('Eliminare questo avviso?');\">
                <i class=\"fas fa-trash-alt me-1\"></i>Elimina
              </a>
            </td>
          </tr>
        ";
    }
    $res->free();
}

// 7. Caricamento template avvisi2.html
$template_path = __DIR__ . '/avvisi2.html';
$template = file_get_contents($template_path);
if ($template === false) {
    die("Impossibile caricare il template avvisi2.html");
}

// 8. Sostituzione dei placeholder
$output = str_replace(
    [
      '<[messaggio_form]>',
      '<[label_submit]>',
      '<[old_titolo]>',
      '<[old_contenuto]>',
      '<[old_annuncio_id]>',
      '<[lista_annunci]>'
    ],
    [
      $messaggio_form,
      $label_submit,
      htmlspecialchars($old_titolo, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($old_contenuto, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($old_annuncio_id, ENT_QUOTES, 'UTF-8'),
      $lista_annunci
    ],
    $template
);

// 9. Output HTML finale
echo $output;

// 10. Chiusura connessione
$mysqli->close();
?>
