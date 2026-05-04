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
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];

        try {
            $serverDsn = sprintf(
                'mysql:host=%s;port=%d;charset=%s',
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['charset']
            );

            $dbName = $dbConfig['dbname'];
            $serverPdo = new PDO($serverDsn, $dbConfig['username'], $dbConfig['password'], $options);

            if (!$this->databaseExists($serverPdo, $dbName)) {
                $quotedDbName = str_replace('`', '``', $dbName);
                $serverPdo->exec(
                    "CREATE DATABASE `{$quotedDbName}` " .
                    "DEFAULT CHARACTER SET {$dbConfig['charset']} " .
                    "COLLATE {$dbConfig['charset']}_unicode_ci"
                );
                $this->initDatabase($serverPdo, $dbName);
            }

            $databaseDsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $dbConfig['host'],
                $dbConfig['port'],
                $dbName,
                $dbConfig['charset']
            );

            $this->pdo = new PDO($databaseDsn, $dbConfig['username'], $dbConfig['password'], $options);
        } catch (PDOException $e) {
            $this->sendResponse(500, '数据库连接失败: ' . $e->getMessage());
        }
    }

    private function databaseExists($pdo, $dbName) {
        $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $stmt->execute([$dbName]);
        return (bool)$stmt->fetchColumn();
    }

    private function initDatabase($pdo, $dbName) {
        $sqlFile = __DIR__ . '/database.sql';
        if (!file_exists($sqlFile)) {
            return;
        }

        $pdo->exec("USE `" . str_replace('`', '``', $dbName) . "`");
        $sql = file_get_contents($sqlFile);

        foreach ($this->splitSqlStatements($sql) as $statement) {
            $pdo->exec($statement);
        }
    }

    private function splitSqlStatements($sql) {
        $statements = [];
        $buffer = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if (!$inSingleQuote && !$inDoubleQuote && $char === '-' && $next === '-') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            if ($char === "'" && !$inDoubleQuote) {
                $escaped = $i > 0 && $sql[$i - 1] === '\\';
                if (!$escaped) {
                    $inSingleQuote = !$inSingleQuote;
                }
            } elseif ($char === '"' && !$inSingleQuote) {
                $escaped = $i > 0 && $sql[$i - 1] === '\\';
                if (!$escaped) {
                    $inDoubleQuote = !$inDoubleQuote;
                }
            }

            if ($char === ';' && !$inSingleQuote && !$inDoubleQuote) {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
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
