<?php
// application/logic/Richieste.php

require_once __DIR__ . '/../include/dbms.inc.php';

class Richieste {
public static function create(array $data) {
        $c = Db::getConnection();
        // Prepara i valori (attenzione: qui non usiamo prepared statements perché hai chiesto di evitarli)
        $nome          = $c->real_escape_string($data['nome']);
        $cognome       = $c->real_escape_string($data['cognome']);
        $email         = $c->real_escape_string($data['email']         ?? '');
        $telefono      = $c->real_escape_string($data['telefono']      ?? '');
        $data_nascita  = isset($data['data_nascita']) 
                         ? "'".$c->real_escape_string($data['data_nascita'])."'" 
                         : "NULL";
        $sesso         = isset($data['sesso']) 
                         ? "'".$c->real_escape_string($data['sesso'])."'" 
                         : "NULL";
        $servizio_id   = (int)$data['servizio_id'];
        $data_pref     = "'".$c->real_escape_string($data['data_preferita'])."'";
        $fascia_id     = (int)$data['fascia_id'];
        $note          = $c->real_escape_string($data['note'] ?? '');

        $sql = "
          INSERT INTO richieste
            (nome, cognome, email, telefono, data_nascita, sesso,
             servizio_id, data_preferita, fascia_id, note)
          VALUES (
            '$nome', '$cognome', '$email', '$telefono',
            $data_nascita, $sesso,
            $servizio_id, $data_pref, $fascia_id, '$note'
          )
        ";

        if (! $c->query($sql)) {
            // Stampo a schermo per debug:
            echo "<pre>Query fallita: $sql\nMySQL error: " . $c->error . "</pre>";
            return false;
        }

        return $c->insert_id;
    }
}
?>