<?php
// application/logic/FasceDisponibilita.php

require_once __DIR__ . '/../include/dbms.inc.php';

class FasceDisponibilita {
	public static function getById(int $id): ?array {
    $db = Db::getConnection();
    $res = $db->query("SELECT * FROM fasce_disponibilita WHERE fascia_id=$id");
    return $res ? $res->fetch_assoc() : null;
}



    public static function byFisio($fid) {
        $c = Db::getConnection();
        $res = $c->query("
          SELECT fascia_id, inizio, fine
          FROM fasce_disponibilita
          WHERE fisioterapista_id = $fid
            AND inizio > NOW()
          ORDER BY inizio
        ");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public static function add($fid, $data) {
        $c = Db::getConnection();
        $q = "
          INSERT INTO fasce_disponibilita
            (fisioterapista_id, sala_id, inizio, fine)
          VALUES (
            $fid,
            {$data['sala_id']},
            '{$data['inizio']}',
            '{$data['fine']}'
          )
        ";
        return $c->query($q);
    }
}
?>