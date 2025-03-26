<?php
// File: includes/logger.php

/**
 * Classe per gestire i log dell'applicazione
 */
class Logger {
    // Livelli di log
    const DEBUG = 100;
    const INFO = 200;
    const NOTICE = 250;
    const WARNING = 300;
    const ERROR = 400;
    const CRITICAL = 500;
    const ALERT = 550;
    const EMERGENCY = 600;
    
    // Nomi dei livelli
    protected static $levels = [
        self::DEBUG => 'DEBUG',
        self::INFO => 'INFO',
        self::NOTICE => 'NOTICE',
        self::WARNING => 'WARNING',
        self::ERROR => 'ERROR',
        self::CRITICAL => 'CRITICAL',
        self::ALERT => 'ALERT',
        self::EMERGENCY => 'EMERGENCY',
    ];
    
    // Directory di log
    protected $log_dir;
    
    // Nome del file di log
    protected $log_file;
    
    /**
     * Costruttore
     *
     * @param string $log_dir Directory di log
     * @param string $log_file Nome del file di log (default: app.log)
     */
    public function __construct($log_dir = null, $log_file = 'app.log') {
        // Se la directory non è specificata, usa la directory predefinita
        if ($log_dir === null) {
            $log_dir = __DIR__ . '/../logs';
        }
        
        $this->log_dir = rtrim($log_dir, '/');
        $this->log_file = $log_file;
        
        // Crea la directory di log se non esiste
        if (!file_exists($this->log_dir)) {
            mkdir($this->log_dir, 0755, true);
        }
    }
    
    /**
     * Scrive un messaggio di log
     *
     * @param string $message Messaggio da loggare
     * @param int $level Livello di log
     * @param array $context Dati di contesto
     * @return bool True se il log è stato scritto, false altrimenti
     */
    public function log($message, $level = self::INFO, $context = []) {
        // Formatta il messaggio
        $log_message = $this->formatMessage($message, $level, $context);
        
        // Scrivi il messaggio nel file di log
        return file_put_contents(
            $this->log_dir . '/' . $this->log_file,
            $log_message . PHP_EOL,
            FILE_APPEND | LOCK_EX
        ) !== false;
    }
    
    /**
     * Formatta il messaggio di log
     *
     * @param string $message Messaggio da formattare
     * @param int $level Livello di log
     * @param array $context Dati di contesto
     * @return string Messaggio formattato
     */
    protected function formatMessage($message, $level, $context = []) {
        // Ottieni il nome del livello
        $level_name = isset(self::$levels[$level]) ? self::$levels[$level] : 'UNKNOWN';
        
        // Formatta il timestamp
        $timestamp = date('Y-m-d H:i:s');
        
        // Formatta il messaggio
        $formatted = "[{$timestamp}] [{$level_name}] {$message}";
        
        // Aggiungi il contesto se presente
        if (!empty($context)) {
            $formatted .= ' ' . json_encode($context);
        }
        
        return $formatted;
    }
    
    /**
     * Scrive un messaggio di debug
     *
     * @param string $message Messaggio da loggare
     * @param array $context Dati di contesto
     * @return bool True se il log è stato scritto, false altrimenti
     */
    public function debug($message, $context = []) {
        return $this->log($message, self::DEBUG, $context);
    }
    
    /**
     * Scrive un messaggio informativo
     *
     * @param string $message Messaggio da loggare
     * @param array $context Dati di contesto
     * @return bool True se il log è stato scritto, false altrimenti
     */
    public function info($message, $context = []) {
        return $this->log($message, self::INFO, $context);
    }
    
    /**
     * Scrive un messaggio di avviso
     *
     * @param string $message Messaggio da loggare
     * @param array $context Dati di contesto
     * @return bool True se il log è stato scritto, false altrimenti
     */
    public function warning($message, $context = []) {
        return $this->log($message, self::WARNING, $context);
    }
    
    /**
     * Scrive un messaggio di errore
     *
     * @param string $message Messaggio da loggare
     * @param array $context Dati di contesto
     * @return bool True se il log è stato scritto, false altrimenti
     */
    public function error($message, $context = []) {
        return $this->log($message, self::ERROR, $context);
    }
    
    /**
     * Scrive un messaggio critico
     *
     * @param string $message Messaggio da loggare
     * @param array $context Dati di contesto
     * @return bool True se il log è stato scritto, false altrimenti
     */
    public function critical($message, $context = []) {
        return $this->log($message, self::CRITICAL, $context);
    }
}
?>