<?php
/**
 * 越山对话ai - 统一身份验证
 */

// 加固 Session 安全配置
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
session_start();

/**
 * 生成并获取 CSRF Token
 */
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 校验 CSRF Token
 */
function verify_csrf_token($token) {
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * 获取用户真实 IP
 */
function get_client_ip() {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * 基础频率限制 (基于 IP，防止暴力请求)
 * 每 2 秒允许一次请求。由于沙盒环境限制，暂使用文件系统模拟简单的 IP 计数器。
 */
function check_rate_limit($action = 'default') {
    $ip = get_client_ip();
    $limit_dir = __DIR__ . '/../data/limits';
    if (!is_dir($limit_dir)) {
        @mkdir($limit_dir, 0777, true);
    }
    $limit_file = $limit_dir . '/' . md5($ip . $action);
    $now = time();
    
    if (file_exists($limit_file)) {
        $last_time = (int)file_get_contents($limit_file);
        if ($now - $last_time < 2) {
            return false;
        }
    }
    
    file_put_contents($limit_file, $now);
    return true;
}

/**
 * 获取管理员密码 (延迟加载以确保环境变量已注入)
 */
function get_admin_password() {
    $pass = getenv('ADMIN_PASSWORD');
    if (!$pass) {
        // 如果环境变量未设置，出于安全考虑，禁止登录
        return false;
    }
    return $pass;
}

// 处理注销
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// 检查登录状态
if (!isset($_SESSION['logged_in'])) {
    if (isset($_POST['login_pass'])) {
        if (!check_rate_limit('login')) {
            $login_error = "请求过于频繁，请稍后再试。";
        } else {
        $admin_pass = get_admin_password();
        if ($admin_pass !== false && hash_equals($admin_pass, $_POST['login_pass'])) {
                session_regenerate_id(true); // 修复 Session Fixation
                $_SESSION['logged_in'] = true;
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $login_error = $admin_pass === false ? "系统配置错误：未设置管理员密码。" : "密码错误！";
            }
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>身份验证 - 越山对话ai</title>
        <style>
            body { font-family: -apple-system, sans-serif; background: #f0f2f5; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .login-box { background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 320px; text-align: center; }
            h2 { margin-bottom: 20px; font-size: 1.2rem; color: #333; }
            input { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; font-size: 16px; }
            button { width: 100%; padding: 12px; background: #1a73e8; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 16px; }
            .error { color: #dc3545; font-size: 0.9rem; margin-bottom: 10px; }
            p { font-size: 0.8rem; color: #999; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <img src="https://mountainai.nekoweb.org/IMG_1282.jpeg" style="width: 60px; height: 60px; border-radius: 50%; margin-bottom: 15px;">
            <h2>越山对话ai</h2>
            <?php if (isset($login_error)): ?><div class="error"><?php echo $login_error; ?></div><?php endif; ?>
            <form method="POST">
                <input type="password" name="login_pass" placeholder="请输入访问密码" required autofocus>
                <button type="submit">进入系统</button>
            </form>
            <p>由 mountain 开发</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
