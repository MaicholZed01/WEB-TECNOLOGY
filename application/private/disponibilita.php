<?php
// disponibilita.php
// Controller per disponibilita.html

session_start();

// 1. Connessione al database
$conn = Db::getConnection();
if ($conn->connect_error) {
    die("Errore di connessione al database: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// 2. Inizializzo variabili per il template
$messaggio_form     = '';
$label_submit       = 'Aggiungi';
$old_fascia_id      = '';
$old_sala_id        = '';
$old_giorno         = '';
$old_inizio         = '';
$old_fine           = '';
$lista_sale         = '';
$sel_lun            = '';
$sel_mar            = '';
$sel_mer            = '';
$sel_gio            = '';
$sel_ven            = '';
$sel_sab            = '';
$lista_disponibilita = '';

// Funzione di escape
function esc($conn, $val) {
    return $conn->real_escape_string(trim($val));
}

// 3. Costruisco dropdown sale
function buildSaleOptions($conn, $selectedId = '') {
    $opts = "";
    $sql = "SELECT sala_id, nome_sala FROM sale ORDER BY nome_sala ASC";
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $id   = (int)$row['sala_id'];
            $nome = htmlspecialchars($row['nome_sala'], ENT_QUOTES, 'UTF-8');
            $sel  = ($selectedId !== '' && $selectedId == $id) ? ' selected' : '';
            $opts .= "<option value=\"{$id}\"{$sel}>{$nome}</option>\n";
        }
        $res->free();
    }
    return $opts;
}

// 4. Gestione eliminazione disponibilità
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = intval($_GET['id']);
    if ($del_id > 0) {
        $sql_del = "DELETE FROM fasce_disponibilita WHERE fascia_id = {$del_id}";
        if ($conn->query($sql_del)) {
            $messaggio_form = '<div class="alert alert-success">Disponibilità eliminata correttamente.</div>';
        } else {
            $messaggio_form = '<div class="alert alert-danger">Errore eliminazione: '
                              . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . '</div>';
        }
    }
}

// 5. Se richiesta modifica (GET action=edit&id=...)
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    if ($edit_id > 0) {
        $sql_sel = "
          SELECT 
            fascia_id,
            sala_id,
            giorno_settimana,
            inizio,
            fine
          FROM fasce_disponibilita
          WHERE fascia_id = {$edit_id}
          LIMIT 1
        ";
        if ($res = $conn->query($sql_sel)) {
            if ($res->num_rows === 1) {
                $row = $res->fetch_assoc();
                $old_fascia_id  = (int)$row['fascia_id'];
                $old_sala_id    = (int)$row['sala_id'];
                $old_giorno     = $row['giorno_settimana'];
                $old_inizio     = substr($row['inizio'], 0, 5);
                $old_fine       = substr($row['fine'], 0, 5);
                $label_submit   = 'Modifica';

                // Preparo i "selected" per il dropdown giorno
                $giorni = ['Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
                foreach ($giorni as $g) {
                    $varName = 'sel_' . mb_strtolower(substr($g, 0, 3), 'UTF-8');
                    if ($g === $old_giorno) {
                        $$varName = 'selected';
                    } else {
                        $$varName = '';
                    }
                }
            }
            $res->free();
        }
    }
}

// 6. Gestione salvataggio (inserimento o aggiornamento)
if ($_SERVER['REQUEST_METHOD'] === 'POST' 
    && isset($_GET['action']) 
    && $_GET['action'] === 'save') 
{
    $fascia_id   = intval($_POST['fascia_id'] ?? 0);
    $sala_id     = intval($_POST['sala_id'] ?? 0);
    $giorno      = esc($conn, $_POST['giorno'] ?? '');
    $inizio      = esc($conn, $_POST['inizio'] ?? '');
    $fine        = esc($conn, $_POST['fine'] ?? '');

    // Validazione minima
    if ($sala_id === 0 || $giorno === '' || $inizio === '' || $fine === '') {
        $messaggio_form = '<div class="alert alert-danger">Tutti i campi sono obbligatori.</div>';
        // Mantengo i valori nei campi del form
        $old_fascia_id = $fascia_id;
        $old_sala_id   = $sala_id;
        $old_giorno    = $giorno;
        $old_inizio    = $inizio;
        $old_fine      = $fine;
        $label_submit  = $fascia_id > 0 ? 'Modifica' : 'Aggiungi';

        // Preparo selezione giorno
        $giorni = ['Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
        foreach ($giorni as $g) {
            $varName = 'sel_' . mb_strtolower(substr($g, 0, 3), 'UTF-8');
            if ($g === $giorno) {
                $$varName = 'selected';
            } else {
                $$varName = '';
            }
        }
    } else {
        if ($fascia_id > 0) {
            // UPDATE fascia esistente
            $sql_upd = "
              UPDATE fasce_disponibilita
              SET 
                sala_id = {$sala_id},
                giorno_settimana = '{$giorno}',
                inizio = '{$inizio}:00',
                fine   = '{$fine}:00'
              WHERE fascia_id = {$fascia_id}
            ";
            if ($conn->query($sql_upd)) {
                $messaggio_form = '<div class="alert alert-success">Fascia aggiornata correttamente.</div>';
                // Svuoto i campi
                $old_fascia_id = '';
                $old_sala_id   = '';
                $old_giorno    = '';
                $old_inizio    = '';
                $old_fine      = '';
                $label_submit  = 'Aggiungi';
                $sel_lun = $sel_mar = $sel_mer = $sel_gio = $sel_ven = $sel_sab = '';
            } else {
                $messaggio_form = '<div class="alert alert-danger">Errore aggiornamento: '
                                  . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . '</div>';
            }
        } else {
            // INSERT nuova fascia
            $sql_ins = "
              INSERT INTO fasce_disponibilita (sala_id, giorno_settimana, inizio, fine)
              VALUES ({$sala_id}, '{$giorno}', '{$inizio}:00', '{$fine}:00')
            ";
            if ($conn->query($sql_ins)) {
                $messaggio_form = '<div class="alert alert-success">Fascia aggiunta correttamente.</div>';
                // Svuoto i campi
                $old_sala_id = '';
                $old_giorno  = '';
                $old_inizio  = '';
                $old_fine    = '';
                $label_submit = 'Aggiungi';
                $sel_lun = $sel_mar = $sel_mer = $sel_gio = $sel_ven = $sel_sab = '';
            } else {
                $messaggio_form = '<div class="alert alert-danger">Errore inserimento: '
                                  . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . '</div>';
            }
        }
    }
}

// 7. Costruisco dropdown sale con selezione corrente
$lista_sale = "<option value=\"\">-- Seleziona Sala --</option>\n" 
             . buildSaleOptions($conn, $old_sala_id);

// 8. Costruisco elenco disponibilità per la tabella
$sql_list = "
  SELECT 
    d.fascia_id,
    s.nome_sala,
    d.giorno_settimana,
    d.inizio,
    d.fine
  FROM fasce_disponibilita AS d
  LEFT JOIN sale AS s ON d.sala_id = s.sala_id
  ORDER BY 
    FIELD(d.giorno_settimana,'Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'),
    d.inizio
";
if ($res = $conn->query($sql_list)) {
    while ($row = $res->fetch_assoc()) {
        $fid   = (int)$row['fascia_id'];
        $sala  = htmlspecialchars($row['nome_sala'], ENT_QUOTES, 'UTF-8');
        $gior  = htmlspecialchars($row['giorno_settimana'], ENT_QUOTES, 'UTF-8');
        $ini   = substr($row['inizio'], 0, 5);
        $fin   = substr($row['fine'], 0, 5);

        $link_delete = "index.php?page=disponibilita&action=delete&id={$fid}";

        $lista_disponibilita .= "
          <tr>
            <td>{$sala}</td>
            <td>{$gior}</td>
            <td>{$ini}</td>
            <td>{$fin}</td>
            <td>
              <a href=\"index.php?page=disponibilita&action=edit&id={$fid}\"
                 class=\"btn btn-outline-modern btn-xs text-primary me-1\" title=\"Modifica\">
                <i class=\"fas fa-edit me-1\"></i>Modifica
              </a>
              <a href=\"{$link_delete}\"
                 class=\"btn btn-outline-modern btn-xs text-danger\"
                 onclick=\"return confirm('Sei sicuro di voler eliminare?');\">
                <i class=\"fas fa-trash-alt me-1\"></i>Elimina
              </a>
            </td>
          </tr>
        ";
    }
    $res->free();
}

// 9. Caricamento del template disponibilita.html
$template_path = __DIR__ . '/disponibilita.html';
$template = file_get_contents($template_path);
if ($template === false) {
    die("Impossibile caricare il template disponibilita.html");
}

// 10. Sostituzione dei placeholder
$output = str_replace(
    [
      '<[messaggio_form]>',
      '<[label_submit]>',
      '<[lista_sale]>',
      '<[old_fascia_id]>',
      '<[old_inizio]>',
      '<[old_fine]>',
      '<[sel_lun]>',
      '<[sel_mar]>',
      '<[sel_mer]>',
      '<[sel_gio]>',
      '<[sel_ven]>',
      '<[sel_sab]>',
      '<[lista_disponibilita]>'
    ],
    [
      $messaggio_form,
      $label_submit,
      $lista_sale,
      htmlspecialchars($old_fascia_id, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($old_inizio, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($old_fine, ENT_QUOTES, 'UTF-8'),
      $sel_lun,
      $sel_mar,
      $sel_mer,
      $sel_gio,
      $sel_ven,
      $sel_sab,
      $lista_disponibilita
    ],
    $template
);

// 11. Output finale (HTML renderizzato)
echo $output;

// 12. Chiusura connessione
$conn->close();
?>
