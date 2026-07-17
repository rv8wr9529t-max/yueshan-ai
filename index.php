<?php
/**
 * 越山对话ai - 智能对话系统 (由mountain开发)
 * 主入口文件
 */

require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/api_handler.php';

$history = [];
$error_msg = "";

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF 校验失败，请刷新页面重试。");
    }
    if (!check_rate_limit()) {
        die("请求过于频繁，请稍后再试。");
    }
    $user_input = trim($_POST['user_input'] ?? '');
    $history_json = $_POST['history_json'] ?? '[]';
    $history = json_decode($history_json, true) ?: [];
    
    // 限制历史记录长度，仅保留最近 10 条对话，防止上下文过长导致的安全与费用风险
    if (count($history) > 10) {
        $history = array_slice($history, -10);
    }
    $model_index = intval($_POST['model_index'] ?? 0);
    $selected_model = $models[$model_index] ?? null;

    if ($user_input !== '' && $selected_model) {
        $history[] = ['role' => 'user', 'content' => $user_input];
        $result = call_ai_api($selected_model, $history);
        
        if (isset($result['error'])) {
            $error_msg = $result['error'];
        } else {
            $history[] = ['role' => 'assistant', 'content' => $result['content']];
        }
    }
}

if (isset($_GET['clear'])) {
    $history = [];
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>越山对话ai</title>
    <link rel="icon" href="https://mountainai.nekoweb.org/IMG_1282.jpeg" type="image/jpeg">
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="brand">
            <img src="https://mountainai.nekoweb.org/IMG_1282.jpeg" alt="Logo">
            <span>越山对话ai</span>
        </div>
        <div class="header-actions">
            <a href="?clear=1" class="btn-clear">新对话</a>
        </div>
    </header>

    <main id="chatBox">
        <div class="chat-container">
            <?php if (empty($history)): ?>
                <div class="welcome">
                    <h1>您好，</h1>
                    <p>今天我能帮您做些什么？</p>
                </div>
            <?php else: ?>
                <?php foreach ($history as $msg): ?>
                    <div class="message <?php echo $msg['role'] === 'user' ? 'user' : 'ai'; ?>">
                        <div class="avatar">
                            <?php if ($msg['role'] === 'user'): ?>
                                <img src="https://ui-avatars.com/api/?name=User&background=f0f4f9&color=1a73e8" alt="U">
                            <?php else: ?>
                                <img src="https://mountainai.nekoweb.org/IMG_1282.jpeg" alt="AI">
                            <?php endif; ?>
                        </div>
                        <div class="content"><?php echo nl2br(htmlspecialchars($msg['content'])); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if ($error_msg): ?>
                <div class="error-msg" style="text-align: center; color: #d93025; margin-top: 10px;"><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="input-wrapper">
            <form method="POST" action="index.php" id="chatForm">
                <div class="model-selector">
                    <select name="model_index">
                        <?php foreach ($models as $index => $m): ?>
                            <option value="<?php echo $index; ?>" <?php echo (isset($_POST['model_index']) && $_POST['model_index'] == $index) ? 'selected' : ''; ?>>
                                ✨ <?php echo htmlspecialchars($m['display_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-box">
                    <textarea name="user_input" id="userInput" placeholder="在这里输入内容..." required rows="1"></textarea>
                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                    <input type="hidden" name="history_json" value='<?php echo json_encode($history, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                    <button type="submit" class="send-btn" id="sendBtn">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path></svg>
                    </button>
                </div>
            </form>
        </div>
    </footer>

    <script src="assets/js/script.js"></script>
</body>
</html>
