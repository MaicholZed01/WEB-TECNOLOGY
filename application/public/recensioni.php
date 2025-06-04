<?php
// application/public/recensioni.php

require "../include/dbms.inc.php";
require "../include/template2.inc.php";
require "../logic/Appuntamenti.php";
require "../logic/Richieste.php";

$main = new Template("application/dtml/2098_health/frame");
$body = new Template("application/dtml/2098_health/recensioni");

// 1) Recupera elenco di appuntamenti già completati (per selezionare a quale lasciare recensione)
$listaApp = Appuntamenti::listCompleted();  
// listCompleted() esegue qualcosa come:
// SELECT a.appuntamento_id, req.nome, req.cognome, a.aggiornato_il
//  FROM appuntamenti AS a
//  JOIN richieste AS req ON a.richiesta_id = req.richiesta_id
//  WHERE a.stato = 'Completato'
$htmlOptions = "<option value=\"\">-- Seleziona Appuntamento --</option>";
foreach ($listaApp as $app) {
    $idApp = intval($app['appuntamento_id']);
    $nome  = htmlspecialchars($app['nome']);
    $cogn  = htmlspecialchars($app['cognome']);
    $dataA = date("d/m/Y", strtotime($app['aggiornato_il']));
    $htmlOptions .= "<option value=\"$idApp\">$dataA – $nome $cogn</option>";
}
$body->setContent("lista_appuntamento_option", $htmlOptions);

// 2) Se POST => salva la recensione
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $app_id     = intval($_POST['appuntamento_id'] ?? 0);
    $valutazione = intval($_POST['valutazione'] ?? 0);
    $commento   = trim($_POST['commento'] ?? "");
    $errore      = "";

    if ($app_id <= 0) {
        $errore = "Devi selezionare un appuntamento valido.";
    } elseif ($valutazione < 1 || $valutazione > 5) {
        $errore = "La valutazione deve essere un numero tra 1 e 5.";
    }

    if ($errore === "") {
        $dati = [
            'appuntamento_id' => $app_id,
            'valutazione'     => $valutazione,
            'commento'        => $commento
        ];
        $ok = Appuntamenti::addReview($dati);
        if ($ok) {
            $body->setContent("messaggio_form", "<div class=\"alert alert-success\">Recensione salvata con successo.</div>");
        } else {
            $body->setContent("messaggio_form", "<div class=\"alert alert-danger\">Errore durante il salvataggio della recensione.</div>");
        }
    } else {
        $body->setContent("messaggio_form", "<div class=\"alert alert-danger\">$errore</div>");
    }

    // Ripristina selezioni  
    $body->setContent("old_appuntamento_id", $app_id);
    $body->setContent("sel_val_1",   $valutazione===1 ? "selected" : "");
    $body->setContent("sel_val_2",   $valutazione===2 ? "selected" : "");
    $body->setContent("sel_val_3",   $valutazione===3 ? "selected" : "");
    $body->setContent("sel_val_4",   $valutazione===4 ? "selected" : "");
    $body->setContent("sel_val_5",   $valutazione===5 ? "selected" : "");
    $body->setContent("old_commento", htmlspecialchars($commento));
    $body->setContent("old_nome_cliente",    ""); // non usato in questo contesto
    $body->setContent("old_cognome_cliente", ""); // non usato in questo contesto
}
else {
    // Primo accesso, campi vuoti
    $body->setContent("messaggio_form", "");
    $body->setContent("old_appuntamento_id", "");
    $body->setContent("sel_val_1",   "");
    $body->setContent("sel_val_2",   "");
    $body->setContent("sel_val_3",   "");
    $body->setContent("sel_val_4",   "");
    $body->setContent("sel_val_5",   "");
    $body->setContent("old_commento",       "");
    $body->setContent("old_nome_cliente",    "");
    $body->setContent("old_cognome_cliente", "");
}

// Render finale
$main->setContent("body", $body->get());
$main->close();
?>