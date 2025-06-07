<?php
// application/logic/Db.php
require_once __DIR__ . '/../include/dbms.inc.php';
class LogicDb {
    public static function conn() {
        return Db::getConnection();
    }
}
?>