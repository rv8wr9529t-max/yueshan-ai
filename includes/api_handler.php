<?php
/**
 * 越山对话ai - API 处理逻辑
 */

function call_ai_api($selected_model, $history) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $selected_model['api_url']);
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
