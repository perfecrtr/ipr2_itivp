<?php
require_once 'Database.php';

class Auth {
    private $conn;
    private $table_name = "api_keys";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function authenticate($api_key) {
        if (empty($api_key)) {
            return false;
        }

        $query = "SELECT id, user_id, is_active 
                  FROM " . $this->table_name . " 
                  WHERE api_key = :api_key AND is_active = 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":api_key", $api_key);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return true;
        }
        return false;
    }
}
?>