<?php
/**
 * 越山对话ai - 模型管理后台
 */

require_once 'includes/auth.php';
require_once 'includes/config.php';

$message = "";

// 处理添加或修改
if (isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF 校验失败，请重试。");
    }
    if ($_POST['action'] === 'save') {
        $index = $_POST['edit_index'] ?? '';
        $new_key = $_POST['api_key'];
        
        // 如果输入的是脱敏后的星号且是编辑模式，则保留原密钥
        if ($index !== '' && $new_key === '********') {
            $new_key = $models[$index]['api_key'];
        }

        $new_model = [
            'display_name' => $_POST['display_name'],
            'model_id' => $_POST['model_id'],
            'api_url' => $_POST['api_url'],
            'api_key' => $new_key
        ];
        
        if ($index !== '') {
            $models[$index] = $new_model;
            $message = "模型已更新！";
        } else {
            $models[] = $new_model;
            $message = "新模型已添加！";
        }
        file_put_contents($models_file, json_encode(array_values($models), JSON_PRETTY_PRINT));
        
        // 刷新模型列表
        $models = json_decode(file_get_contents($models_file), true);
    } elseif ($_POST['action'] === 'delete') {
        $index = intval($_POST['index']);
        array_splice($models, $index, 1);
        file_put_contents($models_file, json_encode(array_values($models), JSON_PRETTY_PRINT));
        $message = "模型已删除！";
    }
}

// 获取编辑数据
$edit_data = null;
$edit_index = '';
if (isset($_GET['edit'])) {
    $edit_index = intval($_GET['edit']);
    $edit_data = $models[$edit_index] ?? null;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>越山对话ai - 后台管理</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: #f0f2f5; padding: 20px; color: #333; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { font-size: 1.5rem; margin-bottom: 20px; color: #007bff; border-bottom: 2px solid #f0f2f5; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 0.9rem; }
        input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; outline: none; box-sizing: border-box; }
        button { padding: 10px 20px; background: #007bff; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button.delete { background: #dc3545; padding: 5px 10px; font-size: 0.8rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 30px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 0.85rem; }
        th { background: #f8f9fa; }
        .msg { padding: 10px; background: #d4edda; color: #155724; border-radius: 5px; margin-bottom: 20px; }
        .nav { margin-bottom: 20px; font-size: 0.9rem; display: flex; justify-content: space-between; }
        .nav a { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="index.php">← 返回对话页</a>
            <a href="?logout=1" style="color: #dc3545;">退出登录</a>
        </div>
        <h1>越山对话ai 模型管理后台</h1>
        
        <?php if ($message): ?><div class="msg"><?php echo $message; ?></div><?php endif; ?>

        <form method="POST" action="kjzz025247.php">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <input type="hidden" name="edit_index" value="<?php echo $edit_index; ?>">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>显示名称 (例如: GPT-4o)</label>
                    <input type="text" name="display_name" value="<?php echo $edit_data['display_name'] ?? ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>模型 ID (例如: gpt-4o)</label>
                    <input type="text" name="model_id" value="<?php echo $edit_data['model_id'] ?? ''; ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>API 地址 (URL)</label>
                <input type="text" name="api_url" value="<?php echo $edit_data['api_url'] ?? ''; ?>" required>
            </div>
            <div class="form-group">
                <label>API 密钥 (Key) <?php echo $edit_data ? '<span style="color: #999; font-weight: normal;">(已隐藏，输入新密钥以修改)</span>' : ''; ?></label>
                <input type="text" name="api_key" value="<?php echo $edit_data ? '********' : ''; ?>" placeholder="请输入 API 密钥" required>
            </div>
            <button type="submit"><?php echo $edit_data ? '保存修改' : '添加模型'; ?></button>
            <?php if ($edit_data): ?> <a href="kjzz025247.php" style="font-size: 0.9rem; margin-left: 10px; color: #666;">取消编辑</a><?php endif; ?>
        </form>

        <table>
            <thead>
                <tr>
                    <th>名称</th>
                    <th>模型 ID</th>
                    <th>API 地址</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($models as $index => $m): ?>
                <tr>
                    <td><?php echo htmlspecialchars($m['display_name']); ?></td>
                    <td><?php echo htmlspecialchars($m['model_id']); ?></td>
                    <td><?php echo htmlspecialchars(substr($m['api_url'], 0, 30)) . '...'; ?></td>
                    <td>
                        <a href="?edit=<?php echo $index; ?>" style="color: #007bff; text-decoration: none; font-size: 0.8rem; margin-right: 10px;">编辑</a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('确定要删除吗？');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                            <input type="hidden" name="index" value="<?php echo $index; ?>">
                            <button type="submit" class="delete">删除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
