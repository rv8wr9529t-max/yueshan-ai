<?php
/**
 * 越山对话ai - 核心配置加载
 * 配置文件（.env / models.json）通过静态变量 + filemtime 缓存，避免每请求重复 I/O。
 */

/**
 * 加载 .env 环境变量（带 filemtime 失效缓存）
 */
function loadEnv($path) {
    static $loaded = [];
    static $mtime_cache = [];

    if (!file_exists($path)) {
        return;
    }
    $mtime = filemtime($path);
    // 已加载且文件未变化则跳过，避免每请求重复 I/O 与 putenv
    if (isset($loaded[$path]) && $mtime_cache[$path] === $mtime) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        // 修复：无 '=' 的行会令 list() 报 notice，跳过此类无效行
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        $_ENV[$name] = $value;
        putenv($name . "=" . $value);
    }

    $loaded[$path] = true;
    $mtime_cache[$path] = $mtime;
}

/**
 * 加载 models.json（带 filemtime 失效缓存）
 * @return array 模型列表
 */
function load_models($path) {
    static $cache = [];
    static $mtime_cache = [];

    if (!file_exists($path)) {
        return [];
    }
    $mtime = filemtime($path);
    if (isset($cache[$path]) && $mtime_cache[$path] === $mtime) {
        return $cache[$path];
    }

    $data = json_decode(file_get_contents($path), true) ?: [];
    $cache[$path] = $data;
    $mtime_cache[$path] = $mtime;
    return $data;
}

$models_file = __DIR__ . '/../models.json';

if (!file_exists($models_file)) {
    die("错误：找不到 models.json 配置文件。");
}

loadEnv(__DIR__ . '/../.env');

$models = load_models($models_file);
