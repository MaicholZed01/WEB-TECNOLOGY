<?php
// application/private/registrazione.php
// Controller per la pagina "registrazione" (creazione/modifica fisioterapista)

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

function handleRegistrazione(bool &$show, string &$bodyHtml): void {
    $show     = false;
    $bodyHtml = '';

    // 1) Solo quando page=registrazione
    if (($_GET['page'] ?? '') !== 'registrazione') return;
    $show = true;

    // 2) Sessione + flash messages
    if (session_status() === PHP_SESSION_NONE) session_start();
    $flash    = $_SESSION['reg_flash'] ?? '';
    unset($_SESSION['reg_flash']);
    $sqlError = $_SESSION['reg_error'] ?? '';
    unset($_SESSION['reg_error']);

    // 3) Connessione DB
    $db = Db::getConnection();
    $db->set_charset('utf8');

    // 4) Variabili old per populate form
    $old = [
        'fisioterapista_id' => 0,
        'nome'              => '',
        'cognome'           => '',
        'telefono'          => '',
        'email'             => '',
        'password'          => '',
        'confirm_password'  => '',
        'bio'               => '',
        'anni_esperienza'   => '',
        'tariffa_oraria'    => ''
    ];
    $message = '';

    // 5) Se POST action=save => salva dati
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'save') {
        // Legge campi POST
        foreach ($old as $k => $_) {
            if (isset($_POST[$k])) {
                $old[$k] = trim($_POST[$k]);
            }
        }

        // Validazioni
        if ($old['nome']==='' || $old['cognome']==='' || $old['email']==='' || $old['password']==='' ) {
            $message = '<div class="alert alert-danger">Compila tutti i campi obbligatori (Nome, Cognome, Email, Password).</div>';
        } elseif (!preg_match('/^[^@]+@(?:fisiocenter\.it|gmail\.com)$/i',$old['email'])) {
            $message = '<div class="alert alert-danger">Email non valida: deve terminare con @fisiocenter.it o @gmail.com</div>';
        } elseif ($old['password'] !== $old['confirm_password']) {
            $message = '<div class="alert alert-danger">Le password non corrispondono.</div>';
        } else {
            // Escape + hash password
            $n   = $db->real_escape_string($old['nome']);
            $c   = $db->real_escape_string($old['cognome']);
            $tel = $db->real_escape_string($old['telefono']);
            $em  = $db->real_escape_string($old['email']);
            $pw  = sha1($old['password']);
            $bio = $db->real_escape_string($old['bio']);
            // anni esperienza: usa NULL se vuoto
            $ae  = $old['anni_esperienza'] === '' ? 'NULL' : (int)$old['anni_esperienza'];
            // tariffa_oraria: usa NULL se vuoto
            $tr  = $old['tariffa_oraria'] === ''
                   ? 'NULL'
                   : "'".$db->real_escape_string($old['tariffa_oraria'])."'";

           
            // INSERT
            $sql = "INSERT INTO fisioterapisti
                        (nome, cognome, telefono, email, password_hash, bio, anni_esperienza, tariffa_oraria, creato_il)
                    VALUES
                        ('$n','$c','$tel','$em','$pw','$bio',$ae,$tr,NOW())";
            
            if ($db->query($sql)) {
                $_SESSION['reg_flash'] = '<div class="alert alert-success">Registrazione avvenuta con successo.</div>';
                // PRG -> login
                sleep(2);
                header('Location: index.php?page=login');
                exit;
            } else {
                $message = '<div class="alert alert-danger">Errore SQL: '.htmlspecialchars($db->error, ENT_QUOTES).'</div>';
            }
        }
    }

    // 6) Carica template e popola
    $tpl = new Template('dtml/webarch/registrazione');
    $tpl->setContent('error_register', $message . $flash . ($sqlError ? '<div class="alert alert-danger">'.$sqlError.'</div>' : ''));
    // old fields
    $tpl->setContent('old_fisioterapista_id', $old['fisioterapista_id']);
    foreach (['nome','cognome','telefono','email','bio','anni_esperienza','tariffa_oraria'] as $f) {
        $tpl->setContent('old_'.$f, htmlspecialchars($old[$f], ENT_QUOTES));
    }
    $bodyHtml = $tpl->get();
}
?>
