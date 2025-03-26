<?php
require_once dirname(__DIR__) . '/config.php';

// Classe per gestire la connessione al database
class Database {
    private static $conn = null;
    
    // Ottieni connessione singleton
    public static function getConnection() {
        if (self::$conn === null) {
            try {
                self::$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                
                if (self::$conn->connect_error) {
                    throw new Exception("Connection failed: " . self::$conn->connect_error);
                }
                
                self::$conn->set_charset("utf8mb4");
            } catch (Exception $e) {
                error_log("Database connection error: " . $e->getMessage());
                throw $e;
            }
        }
        
        return self::$conn;
    }
    
    // Chiudi connessione
    public static function closeConnection() {
        if (self::$conn !== null) {
            self::$conn->close();
            self::$conn = null;
        }
    }
}

// Classe QueryBuilder per query parametrizzate
class QueryBuilder {
    private $conn;
    private $stmt;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function prepare($sql) {
        $this->stmt = $this->conn->prepare($sql);
        
        if ($this->stmt === false) {
            throw new Exception("Query preparation failed: " . $this->conn->error);
        }
        
        return $this;
    }
    
    public function bind($types, ...$params) {
        if ($this->stmt === null) {
            throw new Exception("No prepared statement");
        }
        
        $this->stmt->bind_param($types, ...$params);
        return $this;
    }
    
    public function execute() {
        if ($this->stmt === null) {
            throw new Exception("No prepared statement");
        }
        
        $result = $this->stmt->execute();
        
        if ($result === false) {
            throw new Exception("Query execution failed: " . $this->stmt->error);
        }
        
        return $this;
    }
    
    public function getResult() {
        if ($this->stmt === null) {
            throw new Exception("No prepared statement");
        }
        
        return $this->stmt->get_result();
    }
    
    public function close() {
        if ($this->stmt !== null) {
            $this->stmt->close();
            $this->stmt = null;
        }
    }
    
    // Metodo di convenienza per eseguire query SELECT
    public function select($sql, $types = null, $params = null) {
        $this->prepare($sql);
        
        if ($types !== null && $params !== null) {
            $this->bind($types, ...$params);
        }
        
        $this->execute();
        return $this->getResult();
    }
    
    // Metodo di convenienza per eseguire query di modifica
    public function modify($sql, $types, $params) {
        $this->prepare($sql);
        $this->bind($types, ...$params);
        $this->execute();
        
        return [
            'affected_rows' => $this->stmt->affected_rows,
            'insert_id' => $this->stmt->insert_id
        ];
    }
}
?>