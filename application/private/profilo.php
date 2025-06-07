<?php
// profilo.php
// Controller per profilo.html

session_start();

// Controlla se l’utente è loggato
if (!isset($_SESSION['fisioterapista_id'])) {
    // Se non è loggato, reindirizza al login
    header('Location: index.php?page=login');
    exit;
}

$fisioterapista_id = intval($_SESSION['fisioterapista_id']);

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
$messaggio_form        = '';
$old_nome              = '';
$old_cognome           = '';
$old_email             = '';
$old_telefono          = '';
$old_bio               = '';
$old_tariffa_oraria    = '';
$foto_profilo_url      = '';
$old_fisioterapista_id = $fisioterapista_id;

// Funzione di escape
function esc($mysqli, $val) {
    return $mysqli->real_escape_string(trim($val));
}

// 1) Gestione POST per update_info
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_GET['action']) 
    && $_GET['action'] === 'update_info') 
{
    // Raccolgo i dati dal form
    $nome            = esc($mysqli, $_POST['nome'] ?? '');
    $cognome         = esc($mysqli, $_POST['cognome'] ?? '');
    $email           = esc($mysqli, $_POST['email'] ?? '');
    $telefono        = esc($mysqli, $_POST['telefono'] ?? '');
    $bio             = esc($mysqli, $_POST['bio'] ?? '');
    $tariffa_oraria  = esc($mysqli, $_POST['tariffa_oraria'] ?? '');

    // Validazione minimale: nome, cognome e email obbligatori
    if ($nome === '' || $cognome === '' || $email === '') {
        $messaggio_form = '<div class="alert alert-danger">Nome, Cognome e E-mail sono campi obbligatori.</div>';
    } else {
        // Gestione upload foto profilo (opzionale)
        $upload_ok = true;
        $nuovo_file_url = '';
        if (!empty($_FILES['url_foto_profilo']['name'])) {
            $file = $_FILES['url_foto_profilo'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $max_size = 2 * 1024 * 1024; // 2MB
            $permitted = ['jpg','jpeg','png'];
            
            // Controllo estensione e dimensione
            if (!in_array($ext, $permitted)) {
                $messaggio_form = '<div class="alert alert-danger">Il file deve essere JPG o PNG.</div>';
                $upload_ok = false;
            } elseif ($file['size'] > $max_size) {
                $messaggio_form = '<div class="alert alert-danger">Il file supera i 2MB di dimensione.</div>';
                $upload_ok = false;
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $messaggio_form = '<div class="alert alert-danger">Errore nell\'upload dell\'immagine.</div>';
                $upload_ok = false;
            } else {
                // Sposto il file in cartella “uploads/”
                $dest_dir = __DIR__ . '/uploads/';
                if (!is_dir($dest_dir)) {
                    mkdir($dest_dir, 0755, true);
                }
                $nome_unico = 'profilo_' . $fisioterapista_id . '_' . time() . '.' . $ext;
                $dest_path = $dest_dir . $nome_unico;
                if (move_uploaded_file($file['tmp_name'], $dest_path)) {
                    // URL relativo al file salvato
                    $nuovo_file_url = 'uploads/' . $nome_unico;
                } else {
                    $messaggio_form = '<div class="alert alert-danger">Impossibile salvare l\'immagine.</div>';
                    $upload_ok = false;
                }
            }
        }

        // Se upload ok (o non è stato caricato alcun file), faccio UPDATE
        if ($upload_ok) {
            // Se non è stata caricata nuova immagine, mantengo la vecchia
            if ($nuovo_file_url === '') {
                // Recupero attuale URL foto dal DB
                $res = $mysqli->query("
                    SELECT url_foto_profilo 
                    FROM fisioterapisti 
                    WHERE fisioterapista_id = {$fisioterapista_id} 
                    LIMIT 1
                ");
                if ($res && $res->num_rows === 1) {
                    $row = $res->fetch_assoc();
                    $nuovo_file_url = $row['url_foto_profilo'];
                } else {
                    $nuovo_file_url = '';
                }
                if ($res) $res->free();
            }

            // UPDATE fisioterapisti
            $sql_upd = "
              UPDATE fisioterapisti
              SET 
                nome            = '{$nome}',
                cognome         = '{$cognome}',
                email           = '{$email}',
                telefono        = '{$telefono}',
                bio             = '{$bio}',
                tariffa_oraria  = " . ($tariffa_oraria !== '' ? "'{$tariffa_oraria}'" : "NULL") . ",
                url_foto_profilo= " . ($nuovo_file_url !== '' ? "'{$nuovo_file_url}'" : "NULL") . "
              WHERE fisioterapista_id = {$fisioterapista_id}
            ";
            if ($mysqli->query($sql_upd)) {
                $messaggio_form = '<div class="alert alert-success">Profilo aggiornato con successo.</div>';
            } else {
                $messaggio_form = '<div class="alert alert-danger">Errore durante l\'aggiornamento: '
                                  . htmlspecialchars($mysqli->error, ENT_QUOTES, 'UTF-8') . '</div>';
            }
        }
    }
}

// 2. Recupero dati aggiornati dal database per popolare il form
$sql = "
  SELECT 
    nome, cognome, email, telefono, bio, tariffa_oraria, url_foto_profilo 
  FROM fisioterapisti 
  WHERE fisioterapista_id = {$fisioterapista_id}
  LIMIT 1
";
$res = $mysqli->query($sql);
if ($res && $res->num_rows === 1) {
    $row = $res->fetch_assoc();
    $old_nome           = htmlspecialchars($row['nome'], ENT_QUOTES, 'UTF-8');
    $old_cognome        = htmlspecialchars($row['cognome'], ENT_QUOTES, 'UTF-8');
    $old_email          = htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8');
    $old_telefono       = htmlspecialchars($row['telefono'], ENT_QUOTES, 'UTF-8');
    $old_bio            = htmlspecialchars($row['bio'], ENT_QUOTES, 'UTF-8');
    $old_tariffa_oraria = $row['tariffa_oraria'] !== null
                          ? htmlspecialchars($row['tariffa_oraria'], ENT_QUOTES, 'UTF-8')
                          : '';
    $foto_profilo_url   = $row['url_foto_profilo'] !== null
                          ? htmlspecialchars($row['url_foto_profilo'], ENT_QUOTES, 'UTF-8')
                          : 'assets/images/default-profile.png';
}
if ($res) {
    $res->free();
}

// 3. Caricamento del template profilo.html
$template_path = __DIR__ . '/profilo.html';
$template = file_get_contents($template_path);
if ($template === false) {
    die("Impossibile caricare il template profilo.html");
}

// 4. Sostituzione dei placeholder
$output = str_replace(
    [
      '<[messaggio_form]>',
      '<[old_nome]>',
      '<[old_cognome]>',
      '<[old_email]>',
      '<[old_telefono]>',
      '<[old_bio]>',
      '<[old_tariffa_oraria]>',
      '<[foto_profilo]>',
      '<[old_fisioterapista_id]>'
    ],
    [
      $messaggio_form,
      $old_nome,
      $old_cognome,
      $old_email,
      $old_telefono,
      $old_bio,
      $old_tariffa_oraria,
      $foto_profilo_url,
      htmlspecialchars($old_fisioterapista_id, ENT_QUOTES, 'UTF-8')
    ],
    $template
);

// 5. Output finale (HTML renderizzato)
echo $output;

// 6. Chiusura connessione
$mysqli->close();
?>
