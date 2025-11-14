<?php
require_once 'config/Database.php';
require_once 'config/Auth.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-API-Key");


$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));


$auth = new Auth();
$api_key = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';

if (!$auth->authenticate($api_key)) {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized. Invalid or missing API key."]);
    exit;
}


$database = new Database();
$db = $database->getConnection();


$resource = isset($segments[1]) ? $segments[1] : '';
$id = isset($segments[2]) ? intval($segments[2]) : null;


if ($resource === 'transactions') {
    switch ($method) {
        case 'GET':
            if ($id) {
                $query = "SELECT id, order_id, amount, payment_method, status, created_at, updated_at 
                          FROM transactions 
                          WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$id]);
                
                if ($stmt->rowCount() > 0) {
                    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo json_encode($transaction);
                } else {
                    http_response_code(404);
                    echo json_encode(["message" => "Transaction not found."]);
                }
            } else {
                $query = "SELECT id, order_id, amount, payment_method, status, created_at, updated_at 
                          FROM transactions 
                          ORDER BY created_at DESC";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                $transactions = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $transactions[] = $row;
                }
                echo json_encode($transactions);
            }
            break;

        case 'POST':
            if (!empty($input['order_id']) && !empty($input['amount']) && 
                !empty($input['payment_method']) && !empty($input['status'])) {
                
                $query = "INSERT INTO transactions 
                         (order_id, amount, payment_method, status) 
                          VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([
                    $input['order_id'],
                    $input['amount'],
                    $input['payment_method'],
                    $input['status']
                ])) {
                    http_response_code(201);
                    echo json_encode([
                        "message" => "Transaction created successfully.",
                        "id" => $db->lastInsertId()
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(["message" => "Unable to create transaction."]);
                }
            } else {
                http_response_code(400);
                echo json_encode(["message" => "Missing required fields."]);
            }
            break;

        case 'PUT':
            if ($id && !empty($input)) {
                $allowed_fields = ['order_id', 'amount', 'payment_method', 'status'];
                $updates = [];
                $params = [];
                
                foreach ($allowed_fields as $field) {
                    if (isset($input[$field])) {
                        $updates[] = "$field = ?";
                        $params[] = $input[$field];
                    }
                }
                
                if (!empty($updates)) {
                    $params[] = $id;
                    $query = "UPDATE transactions SET " . implode(', ', $updates) . " WHERE id = ?";
                    $stmt = $db->prepare($query);
                    
                    if ($stmt->execute($params)) {
                        echo json_encode(["message" => "Transaction updated successfully."]);
                    } else {
                        http_response_code(500);
                        echo json_encode(["message" => "Unable to update transaction."]);
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(["message" => "No valid fields to update."]);
                }
            } else {
                http_response_code(400);
                echo json_encode(["message" => "Missing data or ID."]);
            }
            break;

        case 'DELETE':
            if ($id) {
                $query = "DELETE FROM transactions WHERE id = ?";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$id])) {
                    echo json_encode(["message" => "Transaction deleted successfully."]);
                } else {
                    http_response_code(500);
                    echo json_encode(["message" => "Unable to delete transaction."]);
                }
            } else {
                http_response_code(400);
                echo json_encode(["message" => "Missing transaction ID."]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(["message" => "Method not allowed."]);
            break;
    }
} else {
    http_response_code(404);
    echo json_encode(["message" => "Resource not found."]);
}
?>