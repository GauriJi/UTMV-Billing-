<?php
require_once 'config.php';

class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    private $conn;
    private $error;

    public function __construct() {
        $this->connect();
    }

    private function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->dbname,
                $this->user,
                $this->pass,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            $this->error = $e->getMessage();
            die("Connection failed: " . $this->error);
        }
    }

    public function getConnection() {
        return $this->conn;
    }

    // Generic query execution
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    // Get single record
    public function single($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    // Get multiple records
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    // Get last insert ID
    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }

    // Begin transaction
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }

    // Commit transaction
    public function commit() {
        return $this->conn->commit();
    }

    // Rollback transaction
    public function rollback() {
        return $this->conn->rollBack();
    }
}
?>
