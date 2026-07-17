<?php
/**
 * 越山对话ai - 安全工具函数
 */

/**
 * 设置全局安全响应头
 * 防止点击劫持、MIME 嗅探、XSS 等浏览器侧风险。
 */
function set_security_headers() {
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: no-referrer-when-downgrade');
    header("Content-Security-Policy: default-src 'self'; "
        . "img-src 'self' https: data:; "
        . "style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; "
        . "font-src https://fonts.gstatic.com; "
        . "script-src 'self'; "
        . "connect-src 'self'; "
        . "frame-ancestors 'self'");
}

/**
 * SSRF 防御：解析 URL 为真实 IP 并校验是否指向公网可信地址
 * 采用白名单思路——解析 host 为真实 IP 后，拒绝所有保留/私有/链路本地网段。
 *
 * @param string $url 待校验的 URL
 * @return string|false 安全返回解析后的真实 IP，危险返回 false
 */
function resolve_safe_ip($url) {
    static $cache = [];
    if (isset($cache[$url])) {
        return $cache[$url];
    }

    $parsed = parse_url($url);
    if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
        return $cache[$url] = false;
    }

    // 仅允许 http/https 协议，禁止 file://、gopher://、dict:// 等
    if (!in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
        return $cache[$url] = false;
    }

    $host = $parsed['host'];
    $port = $parsed['port'] ?? null;

    // 拒绝异常端口（云元数据服务常借助非标准端口绕过 host 校验）
    if ($port !== null && ($port < 1 || $port > 65535)) {
        return $cache[$url] = false;
    }

    // 解析 host 为真实 IP，统一处理域名、十进制/八进制 IP、IPv6 字面量等绕过手法
    $ip = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);
    if ($ip === false) {
        // 域名：解析为 IPv4。解析失败（返回原字符串）视为危险。
        $resolved = gethostbyname($host);
        if ($resolved === $host) {
            return $cache[$url] = false;
        }
        $ip = $resolved;
    }

    // 拒绝保留/私有网段：含 10./172.16-31./192.168./127./0./169.254./224./::1 等
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return $cache[$url] = false;
    }

    // 显式拦截云元数据端点，防止旧版 PHP 的 NO_RES_RANGE 遗漏 169.254.0.0/16
    if (strpos($ip, '169.254.') === 0) {
        return $cache[$url] = false;
    }
    // 拒绝 0.0.0.0/8（部分 PHP 版本未将其归入保留范围）
    if (strpos($ip, '0.') === 0) {
        return $cache[$url] = false;
    }

    return $cache[$url] = $ip;
}

/**
 * SSRF 防御：校验 URL 是否指向公网可信地址
 * @param string $url 待校验的 URL
 * @return bool 安全返回 true，危险返回 false
 */
function is_safe_url($url) {
    return resolve_safe_ip($url) !== false;
}
