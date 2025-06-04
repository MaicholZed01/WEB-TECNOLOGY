<?php
// application/private/login.php

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../logic/Fisioterapisti.php';

/**
 * Gestisce il login senza prepared statements.
 * - Se arriva POST, verifica email+password e reindirizza o restituisce alert.
 * @return string|null  HTML di alert o null se non c’è nulla da visualizzare.
 */
function handleLogin(): ?string {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return null;
    }

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        return '<div class="alert alert-danger">Inserisci email e password.</div>';
    }

    // Provo a loggare
    $fisioId = Fisioterapisti::login($email, $password);
    if ($fisioId === false) {
        return '<div class="alert alert-danger">Credenziali non valide.</div>';
    }

    // Login OK: salvo in sessione e redirect
    $_SESSION['fisio'] = $fisioId;
    header('Location: index.php?page=dashboard');
    exit;
}
?>