<?php
// application/logic/FasceDisponibilita.php
require_once 'Db.php';
class FasceDisponibilita {
    public static function byFisio($fid) {
        $c = LogicDb::conn();
        $res = $c->query("
          SELECT fascia_id, inizio, fine
          FROM fasce_disponibilita
          WHERE fisioterapista_id=$fid AND inizio>NOW()
          ORDER BY inizio
        ");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
    public static function add($fid, $data) {
        $c = LogicDb::conn();
        $q = "
          INSERT INTO fasce_disponibilita
            (fisioterapista_id,sala_id,inizio,fine)
          VALUES (
            $fid,{$data['sala_id']},
            '{$data['inizio']}','{$data['fine']}'
          )";
        return $c->query($q);
    }
}
