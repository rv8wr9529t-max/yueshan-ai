<?php
/**
 * 越山对话ai - 统一身份验证
 */

require_once __DIR__ . '/security.php';

// 加固 Session 安全配置
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
session_start();

// 统一输出安全响应头（必须在任何输出之前调用）
set_security_headers();

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
 * 默认只信任 REMOTE_ADDR（由服务器协议层提供，客户端无法伪造）。
 * X-Forwarded-For 可被任意伪造，仅在显式配置可信代理时才参考其最右侧值。
 */
function get_client_ip() {
    $trusted_proxy = getenv('TRUSTED_PROXY_IP');
    $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if ($trusted_proxy && hash_equals(trim($trusted_proxy), $remote_addr)) {
        // 仅在请求来自可信代理时，才采用 XFF 最右侧（最靠近可信代理的一跳）
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff !== '') {
            $parts = array_map('trim', explode(',', $xff));
            $parts = array_filter($parts, fn($p) => filter_var($p, FILTER_VALIDATE_IP) !== false);
            if (!empty($parts)) {
                return end($parts);
            }
        }
    }
    return $remote_addr;
}

/**
 * 基础频率限制 (基于 IP，防止暴力请求)
 * 每 2 秒允许一次请求。由于沙盒环境限制，暂使用文件系统模拟简单的 IP 计数器。
 */
function check_rate_limit($action = 'default') {
    $ip = get_client_ip();
    $limit_dir = __DIR__ . '/../data/limits';
    if (!is_dir($limit_dir)) {
        @mkdir($limit_dir, 0750, true);
    }
    // 目录不可用则放行（fail-open，避免限流故障导致服务不可用）
    if (!is_dir($limit_dir) || !is_writable($limit_dir)) {
        return true;
    }
    $limit_file = $limit_dir . '/' . md5($ip . $action);
    $now = time();
    
    if (file_exists($limit_file)) {
        $last_time = (int)file_get_contents($limit_file);
        if ($now - $last_time < 2) {
            return false;
        }
    }
    
    file_put_contents($limit_file, $now, LOCK_EX);

    // 概率清理过期计数文件，防止长期运行文件无限堆积（约每 100 次请求清理一次）
    if (mt_rand(1, 100) === 1) {
        $expire = $now - 3600;
        foreach ((array)glob($limit_dir . '/*') as $old) {
            if (is_file($old) && filemtime($old) < $expire) {
                @unlink($old);
            }
        }
    }
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

/**
 * 获取后台管理密码（独立二次认证）
 * 优先使用 ADMIN_PANEL_PASSWORD；未设置则回退到 ADMIN_PASSWORD（仍需二次确认）。
 */
function get_panel_password() {
    $panel = getenv('ADMIN_PANEL_PASSWORD');
    if ($panel) {
        return $panel;
    }
    return get_admin_password();
}

/**
 * 判断当前会话是否已通过后台二次认证
 */
function is_admin() {
    return !empty($_SESSION['is_admin']);
}

/**
 * 渲染后台二次认证登录页（在已登录前台的基础上）
 */
function render_admin_login($error = '') {
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>后台二次验证 - 越山对话ai</title>
        <style>
            body { font-family: -apple-system, sans-serif; background: #f0f2f5; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .login-box { background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 320px; text-align: center; }
            h2 { margin-bottom: 20px; font-size: 1.2rem; color: #333; }
            input { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; font-size: 16px; }
            button { width: 100%; padding: 12px; background: #007bff; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 16px; }
            .error { color: #dc3545; font-size: 0.9rem; margin-bottom: 10px; }
            p { font-size: 0.8rem; color: #999; margin-top: 20px; }
            .nav { margin: 15px 0; }
            .nav a { color: #007bff; text-decoration: none; font-size: 0.85rem; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>后台管理二次验证</h2>
            <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                <input type="password" name="panel_pass" placeholder="请输入管理密码" required autofocus>
                <button type="submit">进入后台</button>
            </form>
            <div class="nav"><a href="index.php">← 返回对话页</a> &nbsp; <a href="?logout=1">退出登录</a></div>
            <p>由 mountain 开发</p>
        </div>
    </body>
    </html>
    <?php
}

// 处理后台二次认证提交
function handle_admin_login() {
    if (!isset($_SESSION['logged_in'])) {
        return null; // 尚未登录前台，交由前台登录流程处理
    }
    if (is_admin()) {
        return null; // 已是 admin，无需再处理
    }
    if (isset($_POST['panel_pass'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            return "CSRF 校验失败，请刷新页面重试。";
        }
        if (!check_rate_limit('panel_login')) {
            return "请求过于频繁，请稍后再试。";
        }
        $panel_pass = get_panel_password();
        if ($panel_pass !== false && hash_equals($panel_pass, $_POST['panel_pass'])) {
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            return true; // 认证成功，由调用方跳转
        }
        return "管理密码错误！";
    }
    return null;
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
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $login_error = "CSRF 校验失败，请刷新页面重试。";
        } elseif (!check_rate_limit('login')) {
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
                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
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
