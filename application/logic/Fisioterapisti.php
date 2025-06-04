<?php
// application/logic/Fisioterapisti.php

require_once __DIR__ . '/../include/dbms.inc.php';

class Fisioterapisti {
    public static function listAll() {
        $c = Db::getConnection();
        $res = $c->query("SELECT fisioterapista_id AS id, nome, cognome FROM fisioterapisti");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

        /**
     * Effettua il login del fisioterapista.
     * @param string $email
     * @param string $password  // in chiaro
     * @return int|false  Id del fisioterapista se login OK, false se fallito.
     */
      public static function login(string $email, string $password) {
        $conn = Db::getConnection();

        // Escapiamo l’email (non useriamo prepared statements)
        $emailEsc = $conn->real_escape_string($email);
        $hash     = sha1($password);

        $sql = "
            SELECT fisioterapista_id
            FROM fisioterapisti
            WHERE email = '$emailEsc'
              AND password_hash = '$hash'
            LIMIT 1
        ";
        $res = $conn->query($sql);
        if (!$res) {
            error_log("Fisioterapisti::login query error: " . $conn->error);
            return false;
        }
        if ($res->num_rows === 0) {
            return false;
        }
        $row = $res->fetch_assoc();
        return (int)$row['fisioterapista_id'];
    }

    /**
     * Ritorna qualche dato in più (opzionale, per debug).
     */
    public static function getById(int $id): ?array {
        $conn = Db::getConnection();
        $sql = "
            SELECT fisioterapista_id, email, nome, cognome
            FROM fisioterapisti
            WHERE fisioterapista_id = $id
            LIMIT 1
        ";
        $res = $conn->query($sql);
        if (!$res || $res->num_rows === 0) {
            return null;
        }
        return $res->fetch_assoc();
    }


    public static function updateProfile($id, $data) {
        $c = Db::getConnection();
        $q = "
          UPDATE fisioterapisti SET
            nome           = '{$data['nome']}',
            cognome        = '{$data['cognome']}',
            telefono       = '{$data['telefono']}',
            bio            = '{$data['bio']}',
            tariffa_oraria = {$data['tariffa_oraria']},
            anni_esperienza= {$data['anni_esperienza']}
          WHERE fisioterapista_id = $id
        ";
        return $c->query($q);
    }
}
?>