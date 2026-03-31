<?php
/**
 * 匿名墙后端 - 入口文件
 * 
 * 所有请求通过此文件路由处理
 */

// 加载配置
$config = require __DIR__ . '/config.php';

// 设置时区
date_default_timezone_set($config['app']['timezone']);

// 错误处理
if ($config['app']['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// CORS 头
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// 全局异常处理，避免 PHP Fatal 直接输出 HTML 导致前端 JSON 解析失败
set_exception_handler(function ($e) use ($config) {
    $message = $config['app']['debug']
        ? ('服务器异常: ' . $e->getMessage())
        : '服务器内部错误';
    http_response_code(500);
    echo json_encode([
        'code' => 500,
        'message' => $message,
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 解析请求
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// 移除基础路径和查询参数
$basePath = '/api';
$path = parse_url($requestUri, PHP_URL_PATH);
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}
if ($path === false || $path === '') {
    $path = '/';
}

// 加载数据库
require __DIR__ . '/database.php';

// 加载路由
require __DIR__ . '/routes.php';

// 执行路由
dispatch($path, $requestMethod);
