<?php
// application/logic/Messaggi.php

require_once __DIR__ . '/../include/dbms.inc.php';

class Messaggi {
    public static function send($fid, $data) {
        $c = Db::getConnection();
        $q = "
          INSERT INTO messaggi
            (richiesta_id, fisioteraprista_id, appuntamento_id, contenuto)
          VALUES (
            {$data['richiesta_id']}, $fid, {$data['appuntamento_id']},
            '{$data['contenuto']}'
          )
        ";
        return $c->query($q);
    }

    public static function listNotifications($fid) {
        $c = Db::getConnection();
        $res = $c->query("
          SELECT * FROM notifiche
          WHERE fisioterapista_id = $fid
          ORDER BY creata_il DESC
        ");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public static function markRead($nid) {
        $c = Db::getConnection();
        return $c->query("UPDATE notifiche SET letta = 1 WHERE notifica_id = $nid");
    }
}
?>