<?php
// application/public/contatti.php
require __DIR__ . '/../include/dbms.inc.php';
require __DIR__ . '/../include/template2.inc.php';

$main = new Template("application/dtml/2098_health/frame");
$body = new Template("application/dtml/2098_health/contatti");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitizza e valida input
    $nome    = trim($_POST['nome']    ?? "");
    $email   = trim($_POST['email']   ?? "");
    $mess    = trim($_POST['messaggio'] ?? "");
    $errore  = "";

    if ($nome === "" || $email === "" || $mess === "") {
        $errore = "Tutti i campi sono obbligatori.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errore = "Indirizzo email non valido.";
    }

    if ($errore === "") {
        // Qui puoi inviare l’email. Esempio base:
        $to      = "info@tuo-dominio.it";
        $subject = "Contatto dal form - $nome";
        $bodyMail = "Nome: $nome\nEmail: $email\n\nMessaggio:\n$mess\n";
        $headers = "From: $email\r\nReply-To: $email\r\n";

        if (mail($to, $subject, $bodyMail, $headers)) {
            $body->setContent("messaggio_form", "<div class=\"alert alert-success\">Messaggio inviato con successo.</div>");
        } else {
            $body->setContent("messaggio_form", "<div class=\"alert alert-danger\">Si è verificato un errore durante l’invio.</div>");
        }
    } else {
        $body->setContent("messaggio_form", "<div class=\"alert alert-danger\">$errore</div>");
    }

    // Riempi campi con i valori inseriti in caso di errore
    $body->setContent("old_nome", htmlspecialchars($nome));
    $body->setContent("old_email", htmlspecialchars($email));
    $body->setContent("old_messaggio", nl2br(htmlspecialchars($mess)));
}
else {
    // Primo accesso, campi vuoti
    $body->setContent("messaggio_form", "");
    $body->setContent("old_nome", "");
    $body->setContent("old_email", "");
    $body->setContent("old_messaggio", "");
}

$main->setContent("body", $body->get());
$main->close();
?>