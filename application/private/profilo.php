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
    // Per test usiamo id fisso=1, oppure da sessione: $_SESSION['fisio_id']
    $fisioId = $_SESSION['fisio_id'] ?? 1;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'update_info') {
        // Sanitizza input
        $nome = $db->real_escape_string($_POST['nome']);
        $cognome = $db->real_escape_string($_POST['cognome']);
        $email = $db->real_escape_string($_POST['email']);
        $telefono = $db->real_escape_string($_POST['telefono']);
        $bio = $db->real_escape_string($_POST['bio']);
        $tariffa = $db->real_escape_string($_POST['tariffa_oraria']);
        
        // Gestione upload foto (facoltativo)
        $fotoPath = '';
        if (!empty($_FILES['url_foto_profilo']['tmp_name'])) {
            $tmp = $_FILES['url_foto_profilo']['tmp_name'];
            $name = basename($_FILES['url_foto_profilo']['name']);
            $dest = __DIR__ . '/../upload/foto_profilo_' . $fisioId . '_' . $name;
            move_uploaded_file($tmp, $dest);
            $fotoPath = $db->real_escape_string('/tec-web/application/' . str_replace($_SERVER['DOCUMENT_ROOT'], '', $dest));
        }
        
        // Build UPDATE
        $sql = "UPDATE fisioterapisti SET ";
        $sql .= "nome='$nome', cognome='$cognome', email='$email', telefono='$telefono', bio='$bio', tariffa_oraria='$tariffa'";
        if ($fotoPath) {
            $sql .= ", url_foto_profilo='$fotoPath'";
        }
        $sql .= " WHERE fisioterapista_id=$fisioId";
        
        if ($db->query($sql)) {
            $message = "<div class='alert alert-success'>Profilo aggiornato con successo.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Errore aggiornamento: " . $db->error . "</div>";
        }
    }

    // Recupero dati correnti
    $res = $db->query("SELECT nome,cognome,email,telefono,bio,tariffa_oraria,url_foto_profilo FROM fisioterapisti WHERE fisioterapista_id=$fisioId");
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
    $tpl->setContent('foto_profilo', htmlspecialchars($row['url_foto_profilo']));
    $tpl->setContent('old_fisioterapista_id', $fisioId);
    
    $bodyHtml = $tpl->get();
    $showForm = true;
}
