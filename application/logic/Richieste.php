<?php
// application/logic/Richieste.php
require_once 'Db.php';
class Richieste {
    public static function create($data) {
        $c = LogicDb::conn();
        $q = "
          INSERT INTO richieste
            (nome,cognome,email,telefono,data_nascita,sesso,indirizzo_id)
          VALUES (
            '{$data['nome']}',
            '{$data['cognome']}',
            '{$data['email']}',
            '{$data['telefono']}',
            '{$data['data_nascita']}',
            '{$data['sesso']}',
            NULL
          )";
        if (!$c->query($q)) return false;
        return $c->insert_id;
    }
}
