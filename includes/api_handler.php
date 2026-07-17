<?php
/**
 * 越山对话ai - API 处理逻辑
 */

require_once __DIR__ . '/security.php';

function call_ai_api($selected_model, $history) {
    $api_url = $selected_model['api_url'] ?? '';

    // SSRF 纵深防御：请求前解析并校验目标 IP
    $safe_ip = resolve_safe_ip($api_url);
    if ($safe_ip === false) {
        return ['error' => "目标 API 地址无效或被禁止访问。"];
    }

    // 用校验过的 IP 固定 curl 解析：省去 curl 内部重复 DNS 查询，并彻底阻断 DNS rebinding
    $host = parse_url($api_url, PHP_URL_HOST);
    $port = parse_url($api_url, PHP_URL_PORT) ?: (parse_url($api_url, PHP_URL_SCHEME) === 'https' ? 443 : 80);
    $resolve_line = "$host:$port:$safe_ip";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RESOLVE, [$resolve_line]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    
    $messages = array_merge([['role' => 'system', 'content' => 'You are a helpful assistant.']], $history);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => $selected_model['model_id'],
        'messages' => $messages,
        'stream' => false
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . trim($selected_model['api_key'])
    ]);
    // 生产环境必须开启 SSL 校验以防止中间人攻击
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    // 禁止跟随重定向，防止通过 302 跳转绕过 SSRF 校验访问内网/云元数据端点
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        return ['error' => "网络连接失败: " . $err];
    }
    
    $res_data = json_decode($response, true);
    if (isset($res_data['choices'][0]['message']['content'])) {
        return ['content' => $res_data['choices'][0]['message']['content']];
    } else {
        // 对错误消息进行脱敏处理，防止泄露 API Key 或后端内部信息
        $raw_error = $res_data['error']['message'] ?? "未知错误";
        // 简单屏蔽可能包含的敏感信息（如 key 的末尾）
        $safe_error = preg_replace('/sk-[a-zA-Z0-9]{10,}/', 'sk-***', $raw_error);
        return ['error' => "接口请求失败，请检查配置或稍后再试。 (详情: " . htmlspecialchars(mb_substr($safe_error, 0, 100)) . ")"];
    }
}
