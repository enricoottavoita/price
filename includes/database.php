<?php
// File: includes/database.php

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;
    private static $instance = null;
    
    public function __construct() {
        $this->host = DB_HOST;
        $this->db_name = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        $this->conn = null;
    }
    
    // Implementazione Singleton
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Connessione al database
    public function getConnection() {
        if ($this->conn === null) {
            try {
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                    ]
                );
            } catch (PDOException $e) {
                error_log("Errore di connessione al database: " . $e->getMessage());
                throw new Exception("Errore di connessione al database");
            }
        }
        return $this->conn;
    }
    
    // Metodo per eseguire query in modo sicuro
    public function executeQuery($sql, $params = []) {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Errore nell'esecuzione della query: " . $e->getMessage());
            throw new Exception("Errore nell'esecuzione della query");
        }
    }
    
    // Metodo per ottenere un singolo record
    public function fetchOne($sql, $params = []) {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetch();
    }
    
    // Metodo per ottenere tutti i record
    public function fetchAll($sql, $params = []) {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
    
    // Metodo per ottenere il conteggio dei record
    public function count($sql, $params = []) {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetchColumn();
    }
    
    // Metodo per inserire un record e restituire l'ID
    public function insert($sql, $params = []) {
        $this->executeQuery($sql, $params);
        return $this->getConnection()->lastInsertId();
    }
}
?>