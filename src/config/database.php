<?php
/**
 * Database Configuration
 * Online Voting System - Wollo University
 * Integrated Project (IP, ADB and SAD)
 */

class Database {
    private $host = 'localhost';
    private $port = '3306'; // MySQL default port
    private $db_name = 'online_voting_system';
    private $username = 'root';
    private $password = ''; // Default XAMPP MySQL password is empty
    private $conn;
    

    /**
     * Get database connection
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            // First try to connect to the specific database
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                )
            );
        } catch(PDOException $exception) {
            // If database doesn't exist, try to connect without database name
            try {
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";port=" . $this->port . ";charset=utf8",
                    $this->username,
                    $this->password,
                    array(
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    )
                );
                
                // Create database if it doesn't exist
                $this->conn->exec("CREATE DATABASE IF NOT EXISTS " . $this->db_name . " CHARACTER SET utf8 COLLATE utf8_general_ci");
                $this->conn->exec("USE " . $this->db_name);
                
            } catch(PDOException $e) {
                die("Database connection failed: " . $e->getMessage() . 
                    "<br><br><strong>Please ensure:</strong><br>" .
                    "1. XAMPP MySQL service is running<br>" .
                    "2. MySQL credentials are correct<br>" .
                    "3. Database permissions are set properly<br><br>" .
                    "<a href='setup.php'>Click here to run database setup</a>");
            }
        }
        
        return $this->conn;
    }
    
    /**
     * Test database connection
     */
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            if ($conn) {
                return array('success' => true, 'message' => 'Database connection successful');
            } else {
                return array('success' => false, 'message' => 'Failed to establish connection');
            }
        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
}

/**
 * Database connection helper function
 */
function getDBConnection() {
    $database = new Database();
    return $database->getConnection();
}

/**
 * Test database connection helper
 */
function testDBConnection() {
    $database = new Database();
    return $database->testConnection();
}
?> 