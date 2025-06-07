<?php
// application/logic/Servizi.php

require_once __DIR__ . '/../include/dbms.inc.php';

class Servizi {
    public static function listAll() {
        $c = Db::getConnection();
        $res = $c->query("SELECT servizio_id, nome FROM servizi");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public static function toggle($id) {
        $c = Db::getConnection();
        $q = "
          UPDATE servizi_fisioterapista
          SET attivo = IF(attivo=1, 0, 1)
          WHERE id_servizio_fisio = $id
        ";
        return $c->query($q);
    }
}
?>