<?php
// application/public/contatti.php

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

/**
 * handleContatti(&$showContact, &$bodyHtmlContact)
 *   - Se $_GET['page']=='process_contatto' → processo POST e redirect
 *   - Se $_GET['page']=='contatti' → carico il template con eventuale flash
 */
function handleContatti(bool &$showContact, string &$bodyHtmlContact): void {
    $showContact      = false;
    $bodyHtmlContact  = '';

    // 1) Se page=process_contatto, gestisco subito il POST
    if (isset($_GET['page']) && $_GET['page'] === 'process_contatto') {
        // Deve essere sempre POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // 1a) Leggo e pulisco i campi
            $mittente = trim($_POST['mittente']   ?? '');
            $email    = trim($_POST['email']      ?? '');
            $contenuto= trim($_POST['contenuto']  ?? '');

            // Avvio sessione per i flash
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // 1b) Validazione base: tutti i campi obbligatori
            if ($mittente === '' || $email === '' || $contenuto === '') {
                $_SESSION['cont_flash'] = '<div class="alert alert-danger">'
                                        . 'Compila tutti i campi obbligatori.</div>';
                header('Location: index.php?page=contatti');
                exit;
            }
            // 1c) Validazione campo email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['cont_flash'] = '<div class="alert alert-danger">'
                                        . 'E-mail non valida.</div>';
                header('Location: index.php?page=contatti');
                exit;
            }

            // 1d) Inserimento in DB
            $conn = Db::getConnection();
            $mittente_esc  = $conn->real_escape_string($mittente);
            $email_esc     = $conn->real_escape_string($email);
            $contenuto_esc = $conn->real_escape_string($contenuto);

            $sql = "
                INSERT INTO messaggi
                  (mittente, email, contenuto, inviato_il)
                VALUES (
                  '$mittente_esc',
                  '$email_esc',
                  '$contenuto_esc',
                  NOW()
                )
            ";
            if ($conn->query($sql)) {
                $_SESSION['cont_flash'] = '<div class="alert alert-success">'
                                        . 'Messaggio inviato con successo!</div>';
                header('Location: index.php?page=contatti');
                exit;
            } else {
                $_SESSION['cont_flash'] = '<div class="alert alert-danger">'
                                        . 'Errore durante il salvataggio: '
                                        . htmlspecialchars($conn->error, ENT_QUOTES)
                                        . '</div>';
                header('Location: index.php?page=contatti');
                exit;
            }
        } else {
            // Se per qualche motivo si raggiunge process_contatto in GET,
            // semplicemente reindirizzo a contatti
            header('Location: index.php?page=contatti');
            exit;
        }
    }

    // 2) Se page=contatti, mostro il form con eventuale messaggio flash
    if (isset($_GET['page']) && $_GET['page'] === 'contatti') {
        $showContact = true;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $flash = $_SESSION['cont_flash'] ?? '';
        unset($_SESSION['cont_flash']);

        // Carico il template e popolo <[messaggio_form]> col flash (eventuale)
        $tpl = new Template('dtml/2098_health/contatti');
        $tpl->setContent('messaggio_form', $flash);

        $bodyHtmlContact = $tpl->get();
    }
}
?>