<?php
// application/public/recensioni.php

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

/**
 * handleRecensioni(&$showRec, &$bodyHtmlRec)
 *   - Se $_GET['page']=='recensioni', imposta $showRec=true.
 *   - Gestisce l’inserimento POST (action=save) e usa Post-Redirect-Get per il flash message.
 *   - Verifica che esista un appuntamento con quell'id e che nome/cognome corrispondano alla richiesta.
 *   - Al primo POST (sia in caso di errore sia di successo) setta $_SESSION['rec_flash'] e ridirige a GET.
 */
function handleRecensioni(bool &$showRec, string &$bodyHtmlRec): void {
    $showRec     = false;
    $bodyHtmlRec = '';

    if (!isset($_GET['page']) || $_GET['page'] !== 'recensioni') {
        return;
    }
    $showRec = true;

    // 1) Flash message
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $flash = $_SESSION['rec_flash'] ?? '';
    unset($_SESSION['rec_flash']);

    // 2) Se è un POST per salvare la recensione:
    if (isset($_GET['action']) && $_GET['action'] === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $conn = Db::getConnection();

        $nome_cli    = trim($_POST['nome_cliente'] ?? '');
        $cogn_cli    = trim($_POST['cognome_cliente'] ?? '');
        $app_id      = (int)($_POST['appuntamento_id'] ?? 0);
        $valutazione = (int)($_POST['valutazione'] ?? 0);
        $commento    = trim($_POST['commento'] ?? '');

        // 3) Validazione campi obbligatori
        if ($nome_cli === '' || $cogn_cli === '' || $app_id <= 0 || !in_array($valutazione, [1,2,3,4,5], true)) {
            $_SESSION['rec_flash'] = '<div class="alert alert-danger">'
                                  . 'Compila tutti i campi obbligatori (Nome, Cognome, Appuntamento ID, Valutazione).'
                                  . '</div>';
            header('Location: index.php?page=recensioni');
            exit;
        }

        // 4) Verifico corrispondenza con appuntamento → richiesta
        $nome_escape = $conn->real_escape_string($nome_cli);
        $cogn_escape = $conn->real_escape_string($cogn_cli);

        $checkSql = "
            SELECT r.richiesta_id
            FROM appuntamenti AS a
            JOIN richieste   AS r ON a.richiesta_id = r.richiesta_id
            WHERE a.appuntamento_id = $app_id
              AND r.nome = '$nome_escape'
              AND r.cognome = '$cogn_escape'
            LIMIT 1
        ";
        $resCheck = $conn->query($checkSql);
        if ($resCheck === false) {
            $_SESSION['rec_flash'] = '<div class="alert alert-danger">'
                                  . 'Errore SQL durante la verifica: '
                                  . htmlspecialchars($conn->error, ENT_QUOTES)
                                  . '</div>';
            header('Location: index.php?page=recensioni');
            exit;
        }
        if ($resCheck->num_rows === 0) {
            $_SESSION['rec_flash'] = '<div class="alert alert-danger">'
                                  . 'Appuntamento non trovato o nome/cognome non corrispondono.'
                                  . '</div>';
            header('Location: index.php?page=recensioni');
            exit;
        }

        // 5) Tutto corretto → eseguo INSERT in recensioni
        $commento_escape = $conn->real_escape_string($commento);
        $insertSql = "
            INSERT INTO recensioni
              (appuntamento_id, valutazione, commento, creato_il)
            VALUES (
              $app_id,
              $valutazione,
              '" . ($commento_escape ? $commento_escape : '') . "',
              NOW()
            )
        ";
        if ($conn->query($insertSql)) {
            $_SESSION['rec_flash'] = '<div class="alert alert-success">'
                                  . 'Recensione salvata con successo!'
                                  . '</div>';
            header('Location: index.php?page=recensioni');
            exit;
        } else {
            $_SESSION['rec_flash'] = '<div class="alert alert-danger">'
                                  . 'Errore durante il salvataggio: '
                                  . htmlspecialchars($conn->error, ENT_QUOTES)
                                  . '</div>';
            header('Location: index.php?page=recensioni');
            exit;
        }
    }

    // 6) Caso GET → carico il template spazzando vecchi dati POST
    $tpl = new Template('dtml/2098_health/recensioni');
    $tpl->setContent('messaggio_form', $flash);
    // Non settiamo mai “old_…” perché vogliamo che il form sia sempre pulito al reload
    $tpl->setContent('old_nome_cliente', '');
    $tpl->setContent('old_cognome_cliente', '');
    $tpl->setContent('old_appuntamento_id', '');
    $tpl->setContent('sel_val_1', '');
    $tpl->setContent('sel_val_2', '');
    $tpl->setContent('sel_val_3', '');
    $tpl->setContent('sel_val_4', '');
    $tpl->setContent('sel_val_5', '');
    $tpl->setContent('old_commento', '');

    $bodyHtmlRec = $tpl->get();
}
?>