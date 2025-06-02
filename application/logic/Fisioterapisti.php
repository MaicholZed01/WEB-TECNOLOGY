<?php
// application/logic/Fisioterapisti.php
require_once 'Db.php';
class Fisioterapisti {
    public static function listAll() {
        $c = LogicDb::conn();
        $res = $c->query("SELECT fisioterapista_id AS id,nome,cognome FROM fisioterapisti");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
    public static function login($email, $pass) {
        $c = LogicDb::conn();
        $hash = sha1($pass);
        $res = $c->query("
          SELECT fisioterapista_id FROM fisioterapisti
          WHERE email='$email' AND password_hash='$hash'
        ");
        return $res && $res->num_rows ? $res->fetch_assoc()['fisioterapista_id'] : false;
    }
    public static function updateProfile($id, $data) {
        $c = LogicDb::conn();
        $q = "
          UPDATE fisioterapisti SET
            nome='{$data['nome']}',
            cognome='{$data['cognome']}',
            telefono='{$data['telefono']}',
            bio='{$data['bio']}',
            tariffa_oraria={$data['tariffa_oraria']},
            anni_esperienza={$data['anni_esperienza']}
          WHERE fisioterapista_id=$id";
        return $c->query($q);
    }
}
