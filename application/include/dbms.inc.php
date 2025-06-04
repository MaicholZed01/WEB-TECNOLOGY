<?php    
    class Db {
    private static $instance = null;
    private $conn;

    private function __construct() {
        // Sostituisci con le tue credenziali Altervista
        $this->conn = new mysqli('localhost', 'lazzarini21', '', 'my_lazzarini21');
        if ($this->conn->connect_error) {
            die('Connection error: ' . $this->conn->connect_error);
        }
        $this->conn->set_charset('utf8mb4');
    }

    public static function getConnection() {
        if (self::$instance === null) {
            self::$instance = new Db();
        }
        return self::$instance->conn;
    }
}

?>