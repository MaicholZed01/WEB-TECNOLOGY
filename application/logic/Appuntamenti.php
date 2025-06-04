<?php
// application/logic/Appuntamenti.php

require_once __DIR__ . '/../include/dbms.inc.php';

class Appuntamenti {
    public static function create($rid, $data) {
        $c = Db::getConnection();
        $q = "
          INSERT INTO appuntamenti
            (richiesta_id, fisioterapista_id, servizio_id, fascia_id, stato)
          VALUES (
            $rid,
            {$data['fisioterapista_id']},
            {$data['servizio_id']},
            {$data['fascia_id']},
            'Prenotato'
          )
        ";
        return $c->query($q);
    }

    public static function addReview($data) {
        $c = Db::getConnection();
        $q = "
          INSERT INTO recensioni
            (appuntamento_id, valutazione, commento)
          VALUES (
            {$data['appuntamento_id']},
            {$data['valutazione']},
            '{$data['commento']}'
          )
        ";
        return $c->query($q);
    }

    public static function changeStatus($id, $stato) {
        $c = Db::getConnection();
        $q = "
          UPDATE appuntamenti
          SET stato = '$stato'
          WHERE appuntamento_id = $id
        ";
        return $c->query($q);
    }
}
?>