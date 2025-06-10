<?php
// application/private/profilo.php
require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

/**
 * Gestisce visualizzazione e aggiornamento del profilo fisioterapista
 * @param bool &$showForm se true mostra il form
 * @param string &$bodyHtml contenuto HTML del form
 */
function handleProfile(&$showForm, &$bodyHtml) {
    $showForm = false;
    $bodyHtml = '';
    
    // Solo se page=profilo
    if (($_GET['page'] ?? '') !== 'profilo') {
        return;
    }

    $db = Db::getConnection();
        // Avvia la sessione se non è già partita
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Se non siamo loggati, redirigo al login
    if (empty($_SESSION['fisio'])) {
        header('Location: index.php?page=login');
        exit;
    }

    // Prendo l’ID del fisioterapista dalla sessione
    $fisioId = (int) $_SESSION['fisio'];




    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'update_info') {
        // Sanitizza input
        $nome = $db->real_escape_string($_POST['nome']);
        $cognome = $db->real_escape_string($_POST['cognome']);
        $email = $db->real_escape_string($_POST['email']);
        $telefono = $db->real_escape_string($_POST['telefono']);
        $bio = $db->real_escape_string($_POST['bio']);
        $anni_esperienza = (int)$db->real_escape_string($_POST['anni_esperienza']);
        $tariffa = $db->real_escape_string($_POST['tariffa_oraria']);
        
      $fotoPath = '';
if (!empty($_FILES['url_foto_profilo']['tmp_name'])) {
    $tmp  = $_FILES['url_foto_profilo']['tmp_name'];
    $name = basename($_FILES['url_foto_profilo']['name']);
    // Costruisci un nome univoco
    $filename = "foto_profilo_{$fisioId}_" . preg_replace('/[^a-zA-Z0-9._-]/','_', $name);
    // Cartella filesystem: application/upload/
    $uploadDir = dirname(__DIR__) . '/upload';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $dest = $uploadDir . '/' . $filename;
    if (move_uploaded_file($tmp, $dest)) {
        // Path da salvare in DB e usare come src nell'HTML:
        $fotoPath = '/tec-web/application/upload/' . $filename;
    } else {
        // Non riesce a salvare file
        $message = "<div class='alert alert-warning'>Impossibile caricare l'immagine.</div>";
    }
}
        
        // Build UPDATE
        $sql = "UPDATE fisioterapisti SET ";
        $sql .= "nome='$nome', cognome='$cognome', email='$email', telefono='$telefono', bio='$bio', anni_esperienza='$anni_esperienza' ,tariffa_oraria='$tariffa'";
        if ($fotoPath) {
    $sql .= ", url_foto_profilo = '" . $db->real_escape_string($fotoPath) . "'";
}
        $sql .= " WHERE fisioterapista_id=$fisioId";
        
        if ($db->query($sql)) {
            $message = "<div class='alert alert-success'>Profilo aggiornato con successo.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Errore aggiornamento: " . $db->error . "</div>";
        }
    }

    // Recupero dati correnti
    $res = $db->query("SELECT nome,cognome,email,telefono,bio,tariffa_oraria,anni_esperienza,url_foto_profilo FROM fisioterapisti WHERE fisioterapista_id=$fisioId");
    $row = $res->fetch_assoc();

    // Costruisco il form
    $tpl = new Template('dtml/webarch/profilo'); // usa profiling template
    $tpl->setContent('messaggio_form', $message ?? '');
    $tpl->setContent('old_nome', htmlspecialchars($row['nome']));
    $tpl->setContent('old_cognome', htmlspecialchars($row['cognome']));
    $tpl->setContent('old_email', htmlspecialchars($row['email']));
    $tpl->setContent('old_telefono', htmlspecialchars($row['telefono']));
    $tpl->setContent('old_bio', htmlspecialchars($row['bio']));
    $tpl->setContent('old_tariffa_oraria', htmlspecialchars($row['tariffa_oraria']));
    $tpl->setContent('old_anni_esperienza', htmlspecialchars($row['anni_esperienza']));
    $tpl->setContent('foto_profilo', htmlspecialchars($row['url_foto_profilo']));
    $tpl->setContent('old_fisioterapista_id', $fisioId);
    
    $bodyHtml = $tpl->get();
    $showForm = true;
}
