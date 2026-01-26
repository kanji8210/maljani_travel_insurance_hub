<?php
/**
 * Maljani Logger - Structured Error Logging
 *
 * Provides centralized logging functionality for the plugin
 * 
 * @since 1.0.1
 * @package Maljani
 */

class Maljani_Logger {
    
    /**
     * Singleton instance
     *
     * @var Maljani_Logger
     */
    private static $instance = null;
    
    /**
     * Log file path
     *
     * @var string
     */
    private $log_file;
    
    /**
     * Get singleton instance
     *
     * @return Maljani_Logger
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/maljani-logs';
        
        // Create log directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            
            // Add .htaccess to protect logs
            $htaccess = $log_dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Deny from all\n");
            }
        }
        
        $this->log_file = $log_dir . '/maljani-' . date('Y-m-d') . '.log';
    }
    
    /**
     * Log a message
     *
     * @param string $level Log level (error, warning, info, debug)
     * @param string $message Log message
     * @param array $context Additional context data
     * @return bool Whether logging was successful
     */
    public function log($level, $message, $context = []) {
        // Only log if WP_DEBUG is enabled
        if (!WP_DEBUG) {
            return false;
        }
        
        $timestamp = current_time('mysql');
        $user_id = get_current_user_id();
        $user_info = $user_id ? " [User: $user_id]" : '';
        
        $context_string = !empty($context) ? ' Context: ' . json_encode($context) : '';
        
        $log_entry = sprintf(
            "[%s] %s: %s%s%s\n",
            $timestamp,
            strtoupper($level),
            $message,
            $user_info,
            $context_string
        );
        
        // Write to file
        $result = error_log($log_entry, 3, $this->log_file);
        
        // Also write to WordPress debug.log if WP_DEBUG_LOG is enabled
        if (WP_DEBUG_LOG) {
            error_log("Maljani - $log_entry");
        }
        
        return $result !== false;
    }
    
    /**
     * Log an error
     *
     * @param string $message Error message
     * @param array $context Additional context
     * @return bool
     */
    public function error($message, $context = []) {
        return $this->log('error', $message, $context);
    }
    
    /**
     * Log a warning
     *
     * @param string $message Warning message
     * @param array $context Additional context
     * @return bool
     */
    public function warning($message, $context = []) {
        return $this->log('warning', $message, $context);
    }
    
    /**
     * Log an info message
     *
     * @param string $message Info message
     * @param array $context Additional context
     * @return bool
     */
    public function info($message, $context = []) {
        return $this->log('info', $message, $context);
    }
    
    /**
     * Log a debug message
     *
     * @param string $message Debug message
     * @param array $context Additional context
     * @return bool
     */
    public function debug($message, $context = []) {
        return $this->log('debug', $message, $context);
    }
    
    /**
     * Get recent logs
     *
     * @param int $lines Number of lines to retrieve
     * @return array Array of log lines
     */
    public function get_recent_logs($lines = 50) {
        if (!file_exists($this->log_file)) {
            return [];
        }
        
        $file = new SplFileObject($this->log_file, 'r');
        $file->seek(PHP_INT_MAX);
        $last_line = $file->key();
        $start_line = max(0, $last_line - $lines);
        
        $logs = [];
        $file->seek($start_line);
        
        while (!$file->eof()) {
            $line = $file->current();
            if (!empty(trim($line))) {
                $logs[] = $line;
            }
            $file->next();
        }
        
        return $logs;
    }
    
    /**
     * Clear old log files (older than 30 days)
     *
     * @return int Number of files deleted
     */
    public function cleanup_old_logs() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/maljani-logs';
        
        if (!is_dir($log_dir)) {
            return 0;
        }
        
        $deleted = 0;
        $files = glob($log_dir . '/maljani-*.log');
        $cutoff_time = strtotime('-30 days');
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
}

// Convenience function
if (!function_exists('maljani_log')) {
    /**
     * Quick logging function
     *
     * @param string $level Log level
     * @param string $message Message to log
     * @param array $context Context data
     */
    function maljani_log($level, $message, $context = []) {
        Maljani_Logger::get_instance()->log($level, $message, $context);
    }
}
