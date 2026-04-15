<?php
/**
 * 数据库连接类
 */

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        global $config;

        $dbConfig = $config['database'];

        try {
            // 先连接到 MySQL 服务器（不指定数据库）
            $dsn = sprintf(
                'mysql:host=%s;port=%d;charset=%s',
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['charset']
            );
            
            $tempPdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
            $tempPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 检查数据库是否存在，不存在则创建
            $dbName = $dbConfig['dbname'];
            $result = $tempPdo->query("SHOW DATABASES LIKE '$dbName'");
            
            if ($result->rowCount() === 0) {
                // 数据库不存在，创建它
                $tempPdo->exec("CREATE DATABASE `$dbName` DEFAULT CHARACTER SET {$dbConfig['charset']} COLLATE {$dbConfig['charset']}_unicode_ci");
                $this->initDatabase($tempPdo, $dbName);
            }

            // 连接到目标数据库
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $dbConfig['host'],
                $dbConfig['port'],
                $dbName,
                $dbConfig['charset']
            );
            
            $this->pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
        } catch (PDOException $e) {
            $this->sendResponse(500, '数据库连接失败: ' . $e->getMessage());
        }
    }

    private function initDatabase($pdo, $dbName) {
        // 导入 SQL 脚本
        $sqlFile = __DIR__ . '/database.sql';
        if (file_exists($sqlFile)) {
            // 切换到新数据库
            $pdo->exec("USE `$dbName`");
            
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
