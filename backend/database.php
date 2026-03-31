<?php
/**
 * 数据库连接类
 */

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        global $config;
        
        $dbPath = $config['database']['path'];
        
        // 确保数据库文件存在
        if (!file_exists($dbPath)) {
            $this->initDatabase($dbPath);
        }
        
        try {
            $this->pdo = new PDO('sqlite:' . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->sendResponse(500, '数据库连接失败');
        }
    }
    
    private function initDatabase($dbPath) {
        // 创建空数据库文件
        touch($dbPath);
        
        // 导入 SQL 脚本
        $sqlFile = __DIR__ . '/database.sql';
        if (file_exists($sqlFile)) {
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $sql = file_get_contents($sqlFile);
            $pdo->exec($sql);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function sendResponse($code, $message, $data = null) {
        http_response_code($code >= 100 && $code < 600 ? $code : 500);
        echo json_encode([
            'code' => $code,
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public function success($data = null, $message = 'success') {
        $this->sendResponse(200, $message, $data);
    }
    
    public function created($data = null, $message = 'created') {
        $this->sendResponse(201, $message, $data);
    }
    
    public function error($code, $message, $data = null) {
        $this->sendResponse($code, $message, $data);
    }
}

$db = Database::getInstance();
$pdo = $db->getConnection();
