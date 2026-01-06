<?php
session_start();

// 引入配置文件
define('CHAT_SYSTEM', true);
require_once 'config.php';

// 生成用户身份
function generateUserIdentity() {
    $colors = [];
    for ($i = 0; $i < 50; $i++) {
        $colors[] = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
    }
    
    // 中文字符集
    $chineseChars = [
        '快', '乐', '神', '秘', '活', '泼', '安', '静', '聪', '明',
        '勇', '敢', '机', '智', '阳', '光', '优', '雅', '幽', '默',
        '沉', '稳', '热', '情', '熊', '猫', '狮', '子', '兔', '子',
        '猫', '咪', '狗', '狗', '老', '虎', '海', '豚', '考', '拉',
        '大', '象', '猴', '子', '企', '鹅', '小', '鸟', '花', '草',
        '树', '木', '山', '水', '日', '月', '星', '辰', '风', '云'
    ];
    
    // 英文字符集（大小写）
    $englishChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    
    // 数字字符集
    $numberChars = '0123456789';
    
    // 随机生成用户名长度（2-10个字符）
    $nameLength = mt_rand(2, 10);
    
    // 生成用户名
    $name = '';
    for ($i = 0; $i < $nameLength; $i++) {
        // 随机选择字符类型
        $charType = mt_rand(1, 3);
        
        switch ($charType) {
            case 1:
                // 中文字符
                $name .= $chineseChars[mt_rand(0, count($chineseChars) - 1)];
                break;
            case 2:
                // 英文字符
                $name .= $englishChars[mt_rand(0, strlen($englishChars) - 1)];
                break;
            case 3:
                // 数字字符
                $name .= $numberChars[mt_rand(0, strlen($numberChars) - 1)];
                break;
        }
    }
    
    $sessionId = session_id();
    $userId = md5($sessionId . microtime(true) . mt_rand(10000, 99999));
    
    return [
        'color' => $colors[mt_rand(0, count($colors) - 1)] ?? '#333333',
        'name' => $name,
        'id' => 'user_' . substr($userId, 0, 16)
    ];
}

// 补充缺失函数：检查用户是否被拉黑
function isUserBlacklisted($userId) {
    if (empty($userId)) return false;
    $config = getChatConfig();
    $blacklist = $config['user_blacklist'] ?? [];
    return is_array($blacklist) && in_array($userId, $blacklist);
}

// 补充缺失函数：检查用户消息发送频率
function checkMessageLimit($userId) {
    $result = [
        'is_over_limit' => false,
        'remaining_seconds' => 0
    ];
    if (empty($userId)) return $result;

    $config = getChatConfig();
    $messageLimit = $config['message_limit'] ?? ['per_minute' => 10, 'enable' => false];
    if (!$messageLimit['enable']) return $result;

    $logFile = 'user_message_logs.json';
    $logs = file_exists($logFile) ? @json_decode(file_get_contents($logFile), true) : [];
    $logs = is_array($logs) ? $logs : [];

    $now = time();
    $userLogs = $logs[$userId] ?? [];
    // 过滤1分钟前的日志
    $userLogs = array_filter($userLogs, function($logTime) use ($now) {
        return $now - $logTime < 60;
    });
    $userLogs = array_values($userLogs); // 重置数组索引

    $maxCount = $messageLimit['per_minute'] ?? 10;
    if (count($userLogs) >= $maxCount) {
        $oldestTime = min($userLogs);
        $remainingSeconds = 60 - ($now - $oldestTime);
        $result['is_over_limit'] = true;
        $result['remaining_seconds'] = max(0, $remainingSeconds);
    }

    return $result;
}

// 补充缺失函数：记录用户消息发送时间
function recordUserMessageTime($userId) {
    if (empty($userId)) return;

    $logFile = 'user_message_logs.json';
    $logs = file_exists($logFile) ? @json_decode(file_get_contents($logFile), true) : [];
    $logs = is_array($logs) ? $logs : [];

    $logs[$userId] = $logs[$userId] ?? [];
    $logs[$userId][] = time();

    // 只保留最近1分钟的日志，减少文件大小
    $now = time();
    $logs[$userId] = array_filter($logs[$userId], function($logTime) use ($now) {
        return $now - $logTime < 60;
    });
    $logs[$userId] = array_values($logs[$userId]);

    @file_put_contents($logFile, json_encode($logs, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// 优化函数：安全解析JSON文件，防止解析失败
function safeJsonDecode($filePath) {
    if (!file_exists($filePath)) return [];
    $content = @file_get_contents($filePath);
    if ($content === false) return [];
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

// 初始化用户身份
if (!isset($_COOKIE['chat_identity_fixed'])) {
    $_SESSION['user_identity'] = generateUserIdentity();
    setcookie('chat_identity_fixed', '1', time() + 86400 * 30, '/', '', false, true);
} elseif (!isset($_SESSION['user_identity'])) {
    $_SESSION['user_identity'] = generateUserIdentity();
}

// 设置客户端Cookie
if (isset($_POST['set_cookie']) && !empty($_POST['set_cookie'])) {
    $data = json_decode($_POST['set_cookie'], true);
    if (is_array($data)) {
        $allowedCookieNames = ['client_timezone', 'client_screen', 'client_id'];
        foreach ($data as $name => $value) {
            if (in_array($name, $allowedCookieNames)) {
                setcookie($name, $value, time() + 86400 * 30, '/', '', false, true);
            }
        }
    }
    exit(json_encode(['status' => 'success']));
}

// 初始化目录和文件
$uploadDir = 'uploads';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$chatFile = 'chat_messages.json';
if (!file_exists($chatFile)) {
    file_put_contents($chatFile, json_encode([]), LOCK_EX);
}

// 处理消息发送
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['set_cookie'])) {
    $messages = safeJsonDecode($chatFile); // 使用安全解析函数
    $newMessage = null;
    $currentUserId = $_SESSION['user_identity']['id'] ?? '';

    // 1. 禁言判断（独立提示：仅返回禁言文案）
    if (isUserBlacklisted($currentUserId)) {
        exit(json_encode(['status' => 'error', 'message' => '您已被禁止发送消息！']));
    }

    // 2. 频率限制判断（独立提示：带剩余时间，与禁言文案完全区分）
    $messageLimitCheck = checkMessageLimit($currentUserId);
    if ($messageLimitCheck['is_over_limit']) {
        $remainingSeconds = $messageLimitCheck['remaining_seconds'];
        if ($remainingSeconds >= 60) {
            $minutes = floor($remainingSeconds / 60);
            $seconds = $remainingSeconds % 60;
            $timeStr = $minutes . '分' . str_pad($seconds, 2, '0', STR_PAD_LEFT) . '秒';
        } else {
            $timeStr = $remainingSeconds . '秒';
        }
        $tips = "消息发送过于频繁，请等待 {$timeStr} 后再试！";
        exit(json_encode(['status' => 'error', 'message' => $tips]));
    }

    // 文本消息处理
    if (isset($_POST['message']) && !empty(trim($_POST['message']))) {
        $newMessage = [
            'id' => uniqid('msg_', true),
            'text' => htmlspecialchars(trim($_POST['message'])),
            'color' => $_SESSION['user_identity']['color'],
            'name' => $_SESSION['user_identity']['name'],
            'time' => date('H:i'),
            'is_self' => true,
            'type' => 'text',
            'user_id' => $_SESSION['user_identity']['id']
        ];
        recordUserMessageTime($currentUserId);
    }
    // 媒体消息处理
    elseif (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['media_file'];
        $fileType = explode('/', $file['type'])[0];
        if (in_array($fileType, ['image', 'video'])) {
            $maxFileSize = 20 * 1024 * 1024;
            if ($file['size'] > $maxFileSize) {
                exit(json_encode(['status' => 'error', 'message' => '文件大小超过20M限制']));
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'avi', 'mov'];
            if (!in_array($ext, $allowedExts)) {
                exit(json_encode(['status' => 'error', 'message' => '不支持的文件格式']));
            }
            $filename = uniqid('media_', true) . '.' . $ext;
            $uploadPath = $uploadDir . '/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $newMessage = [
                    'id' => uniqid('msg_', true),
                    'media_url' => $uploadPath,
                    'media_type' => $fileType,
                    'color' => $_SESSION['user_identity']['color'],
                    'name' => $_SESSION['user_identity']['name'],
                    'time' => date('H:i'),
                    'is_self' => true,
                    'type' => $fileType,
                    'user_id' => $_SESSION['user_identity']['id']
                ];
                recordUserMessageTime($currentUserId);
            } else {
                exit(json_encode(['status' => 'error', 'message' => '文件上传失败']));
            }
        } else {
            exit(json_encode(['status' => 'error', 'message' => '仅支持图片和视频文件']));
        }
    }

    // 保存消息
    if (isset($newMessage)) {
        $messages[] = $newMessage;
        file_put_contents($chatFile, json_encode($messages, JSON_UNESCAPED_UNICODE), LOCK_EX);
        exit(json_encode(['status' => 'success']));
    } else {
        exit(json_encode(['status' => 'error', 'message' => '无效的消息类型或空消息']));
    }
}

// 获取聊天消息 - 仅允许AJAX异步请求访问，直接访问提示非法请求
if (isset($_GET['get_messages'])) {
    // 关键：判断是否为AJAX请求（前端fetch已添加该请求头，直接访问无此头）
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    // 非AJAX请求（直接地址栏访问）：提示非法请求，不跳转
    if (!$isAjax) {
        // 可选：设置HTTP 403禁止访问状态码（更规范）
        header('HTTP/1.1 403 Forbidden');
        // 输出非法请求提示（纯文本，无JSON数据暴露）
        echo "非法请求！禁止直接访问该接口。";
        exit;
    }

    $messages = safeJsonDecode($chatFile); // 使用安全解析函数
    $currentUser = $_SESSION['user_identity']['id'];
    
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $host = $_SERVER['HTTP_HOST'];
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    $fullBasePath = rtrim($protocol . "://" . $host . $scriptPath, '/') . '/';
    
    foreach ($messages as $key => $msg) {
        $messages[$key]['is_self'] = ($msg['user_id'] === $currentUser);
        if (isset($msg['media_url']) && strpos($msg['media_url'], 'http') !== 0) {
            $messages[$key]['media_url'] = $fullBasePath . $msg['media_url'];
        }
    }
    exit(json_encode($messages, JSON_UNESCAPED_UNICODE));
}

// 表情定义
$emojis = [
    'smileys' => ['😀', '😁', '😂', '🤣', '😃', '😄', '😅', '😆', '😉', '😊', '😋', '😎', '😍', '😘', '🥰', '😗', '😙', '😚', '🙂', '🤗', '🤩', '🤔', '🤨', '😐', '😑', '😶', '🙄', '😏', '😣', '😥', '😮', '🤐', '😯', '😪', '😫', '😴', '😌', '😛', '😜', '😝', '🤤', '😒', '😓', '😔', '😕', '🙃', '🤑', '😲', '☹️', '🙁', '😖', '😞', '😟', '😤', '😢', '😭', '😦', '😧', '😨', '😩', '🤯', '😬', '😰', '😱', '😳', '🤪', '😵', '😡', '😠', '🤬', '😷', '🤒', '🤕', '🤢', '🤮', '🤧', '🥵', '🥶', '🥴', '😵‍💫', '🤯'],
    'hearts' => ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '💟', '❤️‍🔥', '❤️‍🩹'],
    'hands' => ['👋', '🤚', '✋', '🖖', '👌', '👍', '👎', '👏', '🙌', '🤝', '👍🏼', '👍🏽', '👍🏾', '👍🏿', '👎🏼', '👎🏽', '👎🏾', '👎🏿', '👏🏼', '👏🏽', '👏🏾', '👏🏿', '🙏', '🙏🏼', '🙏🏽', '🙏🏾', '🙏🏿'],
    'animals' => ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮', '🐷', '🐽', '🐸', '🐵', '🐔', '🐧', '🐦', '🐤', '🐣', '🐥', '🦆', '🦅', '🦉', '🦇', '🐺', '🐗', '🐴', '🦄', '🐝', '🐛', '🦋', '🐌', '🐚', '🐠', '🐟', '🐡', '🦈', '🐬', '🐋', '🐳', '🐙', '🦑', '🦐', '🦞', '🦀', '🐌', '🐢', '🐍', '🦎', '🦖', '🦕', '🐙', '🦩', '🦚', '🦜', '🦢', '🦃', '🐪', '🐫', '🦙', '🦘', '🐘', '🐅', '🐆', '🦓', '🦍', '🦧', '🐘', '🐿️', '🦔', '🦡', '🦨', '🦦', '🐩']
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>匿名聊天室</title>
    <!-- Fancybox CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0.33/dist/fancybox/fancybox.css">
    <link rel="icon" href="public/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="public/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="public/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="public/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="public/favicon-16x16.png">
    <link rel="manifest" href="public/site.webmanifest">
    <meta name="theme-color" content="#07C160">
    <meta name="description" content="一个简单的匿名聊天室">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="聊天室">
    <meta name="application-name" content="聊天室">
    <meta name="msapplication-TileColor" content="#07C160">
    <meta name="msapplication-config" content="public/browserconfig.xml">
    <meta name="msapplication-TileImage" content="public/android-chrome-192x192.png">
    <link rel="mask-icon" href="public/safari-pinned-tab.svg" color="#07C160">
    <style>
        /* 浅色模式变量 */
        :root {
            --bg-primary: #F2F2F2;
            --bg-secondary: #ffffff;
            --bg-tertiary: #f8f8f8;
            --bg-input: #f8f8f8;
            --text-primary: #333333;
            --text-secondary: #666666;
            --text-tertiary: #999999;
            --border-color: #eaeaea;
            --border-light: #f5f5f5;
            --accent-color: #07C160;
            --accent-hover: #06b058;
            --accent-light: #f0fbf4;
            --message-self: #95EC69;
            --message-other: #ffffff;
            --shadow-light: 0 2px 8px rgba(0, 0, 0, 0.03);
            --shadow-medium: 0 2px 20px rgba(0, 0, 0, 0.2);
        }
        
        /* 深色模式变量 */
        [data-theme="dark"] {
            --bg-primary: #1a1a1a;
            --bg-secondary: #2d2d2d;
            --bg-tertiary: #3d3d3d;
            --bg-input: #3d3d3d;
            --text-primary: #e0e0e0;
            --text-secondary: #b0b0b0;
            --text-tertiary: #808080;
            --border-color: #404040;
            --border-light: #353535;
            --accent-color: #07C160;
            --accent-hover: #06b058;
            --accent-light: #1a3a25;
            --message-self: #1e3a25;
            --message-other: #2d2d2d;
            --shadow-light: 0 2px 8px rgba(0, 0, 0, 0.3);
            --shadow-medium: 0 2px 20px rgba(0, 0, 0, 0.5);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            height: 100vh;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .chat-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            max-width: 800px;
            margin: 0 auto;
            background: var(--bg-secondary);
        }
        .header {
            background: var(--bg-secondary);
            padding: 12px 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--border-color);
            box-shadow: var(--shadow-light);
            position: relative;
            z-index: 10;
        }
        .header-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: var(--accent-light);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .group-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .group-name {
            font-size: 17px;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .group-desc {
            font-size: 12px;
            color: var(--text-tertiary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .chat-content {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
            background: var(--bg-primary);
            padding-bottom: 80px;
        }
        .message {
            display: flex;
            margin: 15px 0;
        }
        .message.self {
            flex-direction: row-reverse;
        }
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: white;
            font-weight: bold;
        }
        .message-content {
            max-width: 60%;
            margin: 0 10px;
        }
        .user-info {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        .username {
            font-size: 12px;
            color: var(--text-secondary);
        }
        .message-time {
            font-size: 10px;
            color: var(--text-tertiary);
            margin-left: 8px;
        }
        .message-bubble {
            background: var(--message-other);
            border-radius: 4px;
            padding: 8px 12px;
            position: relative;
            word-break: break-word;
        }
        .message.self .message-bubble {
            background: var(--message-self);
        }
        .message-bubble::before {
            content: '';
            position: absolute;
            top: 0;
            width: 0;
            height: 0;
            border-style: solid;
        }
        .message.other .message-bubble::before {
            left: -6px;
            border-width: 6px 6px 0 0;
            border-color: var(--message-other) transparent transparent transparent;
        }
        .message.self .message-bubble::before {
            right: -6px;
            border-width: 0 6px 6px 0;
            border-color: transparent var(--message-self) transparent transparent;
        }
        .media-message {
            max-width: 100%;
            border-radius: 4px;
            margin-top: 5px;
        }
        .media-message img, .media-message video {
            max-width: 100%;
            max-height: 300px;
            border-radius: 4px;
        }
        .identity-info {
            position: fixed;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 1000;
            transition: opacity 1s ease;
        }
        .emoji-picker {
            position: fixed;
            bottom: 60px;
            right: 10px;
            background: var(--bg-secondary);
            border-radius: 10px;
            box-shadow: var(--shadow-medium);
            width: 300px;
            max-height: 400px;
            z-index: 1001;
            display: none;
            flex-direction: column;
            border: 1px solid var(--border-color);
        }
        .emoji-picker.show {
            display: flex;
        }
        .emoji-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
        }
        .emoji-tab {
            flex: 1;
            padding: 10px;
            text-align: center;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 20px;
            color: var(--text-primary);
        }
        .emoji-tab.active {
            background: var(--bg-tertiary);
        }
        .emoji-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 5px;
            padding: 10px;
            overflow-y: auto;
            max-height: 300px;
        }
        .emoji-item {
            font-size: 24px;
            padding: 5px;
            text-align: center;
            cursor: pointer;
            border-radius: 5px;
        }
        .emoji-item:hover {
            background: var(--bg-tertiary);
        }
        .input-area {
            background: var(--bg-secondary);
            padding: 12px 15px;
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.02);
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 999;
            max-width: 800px;
            margin: 0 auto;
        }
        .message-input {
            flex: 1;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 12px 18px;
            font-size: 14px;
            outline: none;
            transition: all 0.3s ease;
            color: var(--text-primary);
        }
        .message-input:focus {
            border-color: var(--accent-color);
            background: var(--bg-secondary);
            box-shadow: 0 0 0 2px rgba(7, 193, 96, 0.1);
        }
        .send-btn {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--accent-color);
            color: white;
            border: none;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(7, 193, 96, 0.2);
        }
        .send-btn.plus {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }
        .send-btn:hover:not(.plus) {
            background: var(--accent-hover);
            transform: scale(1.05);
        }
        #file-input {
            display: none;
        }
        
        /* 主题切换按钮 */
        .theme-toggle {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 20px;
            color: var(--text-secondary);
            padding: 4px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        .theme-toggle:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        .admin-login-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 20px;
            color: var(--text-secondary);
            padding: 4px;
            border-radius: 4px;
            transition: all 0.3s ease;
            margin-right: 8px;
            text-decoration: none;
        }
        .admin-login-btn:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        /* 滚动条样式 */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--bg-tertiary);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-tertiary);
        }

        /* ========== 美化自定义弹窗样式 ========== */
        .custom-alert {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--bg-secondary);
            border-radius: 12px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.15);
            padding: 24px 30px;
            max-width: 400px;
            width: 90%;
            z-index: 9999; /* 最高层级，不被遮挡 */
            border: 1px solid var(--border-color);
            display: none; /* 默认隐藏 */
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .custom-alert.show {
            display: flex; /* 显示弹窗 */
            animation: fadeIn 0.3s ease-in-out; /* 淡入动画 */
        }
        .alert-icon {
            font-size: 40px;
            margin-bottom: 16px;
            color: #ff4d4f; /* 错误图标红色 */
        }
        .alert-message {
            font-size: 16px;
            color: var(--text-primary);
            line-height: 1.5;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-close {
            padding: 8px 20px;
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .alert-close:hover {
            background: var(--accent-hover);
        }
        /* 弹窗遮罩（可选，暗化背景） */
        .alert-mask {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.2);
            z-index: 9998;
            display: none;
        }
        .alert-mask.show {
            display: block;
            animation: fadeMask 0.3s ease-in-out;
        }
        /* 动画效果 */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translate(-50%, -40%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }
        @keyframes fadeMask {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <!-- PWA安装提示 -->
    <div id="pwa-install-prompt" class="pwa-install-prompt" style="display: none; position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%); background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; padding: 16px 20px; box-shadow: var(--shadow-medium); z-index: 9999; max-width: 400px; width: 90%;">
        <div class="pwa-install-content" style="display: flex; align-items: center; gap: 12px;">
            <div class="pwa-icon" style="width: 48px; height: 48px; background: var(--accent-color); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">💬</div>
            <div class="pwa-text" style="flex: 1;">
                <h3 style="margin: 0 0 4px 0; font-size: 16px; font-weight: 600; color: var(--text-primary);">添加到主屏幕</h3>
                <p style="margin: 0; font-size: 14px; color: var(--text-secondary);">将聊天室添加到主屏幕，获得更好的使用体验</p>
            </div>
        </div>
        <div class="pwa-buttons" style="display: flex; gap: 8px; margin-top: 12px; justify-content: flex-end;">
            <button id="pwa-dismiss" style="padding: 8px 16px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-tertiary); color: var(--text-primary); cursor: pointer; font-size: 14px; transition: all 0.3s ease;">暂不</button>
            <button id="pwa-install" style="padding: 8px 16px; border: none; border-radius: 8px; background: var(--accent-color); color: white; cursor: pointer; font-size: 14px; transition: all 0.3s ease;">添加</button>
        </div>
    </div>
    <!-- 自定义弹窗 DOM 结构 -->
    <div class="alert-mask" id="alertMask"></div>
    <div class="custom-alert" id="customAlert">
        <div class="alert-icon" id="alertIcon">❌</div>
        <div class="alert-message" id="alertMessage"></div>
        <button class="alert-close" id="alertCloseBtn">确定</button>
    </div>

    <div class="chat-container">
        <div class="header">
            <div class="header-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#07C160" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                </svg>
            </div>
            <div class="group-info">
                <div class="group-name">匿名聊天室</div>
                <div class="group-desc">全员在线 · 实时互动</div>
            </div>
            <div class="header-actions">
                <a href="admin.php" class="admin-login-btn" title="管理员登录">🔑</a>
                <button class="theme-toggle" id="pwaInstallButton" title="添加到主屏幕">📱</button>
                <button class="theme-toggle" id="themeToggle" title="切换主题">🌙</button>
            </div>
        </div>
        
        <div class="chat-content" id="chatContent"></div>
        
        <div class="input-area">
            <input type="text" class="message-input" id="messageInput" placeholder="请输入消息...">
            <button class="send-btn plus emoji-btn" id="emojiButton">😀</button>
            <button class="send-btn plus" id="sendButton">+</button>
        </div>
        
        <div class="emoji-picker" id="emojiPicker">
            <div class="emoji-tabs" id="emojiTabs">
                <button class="emoji-tab active" data-tab="smileys">😀</button>
                <button class="emoji-tab" data-tab="hearts">❤️</button>
                <button class="emoji-tab" data-tab="hands">👋</button>
                <button class="emoji-tab" data-tab="animals">🐶</button>
            </div>
            <div class="emoji-grid" id="emojiGrid"></div>
        </div>
        <input type="file" id="file-input" accept="image/*,video/*">
    </div>

    <script>
let currentPlayingVideo = null;
const emojiData = <?php echo json_encode($emojis, JSON_UNESCAPED_UNICODE); ?>;
let existingMessageIds = new Set(); // 全局消息ID集合，用于去重

// ========== 自定义弹窗核心函数 ==========
function showCustomAlert(message, autoClose = 3000) {
    const customAlert = document.getElementById('customAlert');
    const alertMask = document.getElementById('alertMask');
    const alertMessage = document.getElementById('alertMessage');
    const alertCloseBtn = document.getElementById('alertCloseBtn');

    // 设置弹窗消息
    alertMessage.textContent = message;

    // 显示弹窗和遮罩
    customAlert.classList.add('show');
    alertMask.classList.add('show');

    // 自动关闭逻辑
    let closeTimer = setTimeout(() => {
        hideCustomAlert();
    }, autoClose);

    // 手动关闭按钮点击事件
    alertCloseBtn.onclick = function() {
        hideCustomAlert();
        clearTimeout(closeTimer); // 清除自动关闭定时器
    };

    // 隐藏弹窗函数
    function hideCustomAlert() {
        customAlert.classList.remove('show');
        alertMask.classList.remove('show');
    }
}

// 滚动到最新消息
function scrollToLatestMessage() {
    const chatContent = document.getElementById('chatContent');
    if (!chatContent) return;
    chatContent.scrollTop = chatContent.scrollHeight;
    setTimeout(() => {
        if (chatContent) chatContent.scrollTop = chatContent.scrollHeight;
    }, 100);
}

// 生成客户端ID
function generateClientId() {
    if (!localStorage.getItem('chat_client_id')) {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        for (let i = 0; i < 16; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        result += '_' + Date.now();
        localStorage.setItem('chat_client_id', result);
    }
    return localStorage.getItem('chat_client_id');
}

// 设置客户端Cookie
function setClientInfoCookies() {
    const clientInfo = {
        'client_timezone': new Date().getTimezoneOffset(),
        'client_screen': window.screen.width + 'x' + window.screen.height + 'x' + window.devicePixelRatio,
        'client_id': generateClientId()
    };
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'set_cookie=' + encodeURIComponent(JSON.stringify(clientInfo))
    }).catch(err => console.error('设置Cookie失败:', err));
}

// 显示消息
function displayMessage(message) {
    const chatContent = document.getElementById('chatContent');
    const existingMessage = document.querySelector(`[data-message-id="${message.id}"]`);
    if (existingMessage) {
        if (message.type === 'video' && currentPlayingVideo && currentPlayingVideo.parentElement === existingMessage) {
            return;
        }
        existingMessage.remove();
    }

    const messageElement = document.createElement('div');
    messageElement.className = `message ${message.is_self ? 'self' : 'other'}`;
    messageElement.dataset.messageId = message.id;

    let contentHtml = '';
    if (message.type === 'text') {
        contentHtml = `<div class="message-bubble">${message.text}</div>`;
    } else if (message.type === 'image') {
            contentHtml = `<div class="message-bubble"><div class="media-message"><a href="${message.media_url}" data-fancybox="gallery" data-caption="图片消息"><img src="${message.media_url}" alt="图片" loading="lazy" style="cursor: pointer;"></a></div></div>`;
    } else if (message.type === 'video') {
        contentHtml = `<div class="message-bubble"><div class="media-message"><video controls preload="metadata"><source src="${message.media_url}" type="video/mp4">您的浏览器不支持视频播放</video></div></div>`;
    }

    messageElement.innerHTML = `
        <div class="avatar" style="background-color: ${message.color}">
            ${message.name.substring(0, 2)}
        </div>
        <div class="message-content">
            <div class="user-info">
                <span class="username">${message.is_self ? '我' : message.name}</span>
                <span class="message-time">${message.time}</span>
            </div>
            ${contentHtml}
        </div>
    `;

    chatContent.appendChild(messageElement);
    scrollToLatestMessage();

    // 视频播放优化：暂停其他所有视频
    if (message.type === 'video') {
        const video = messageElement.querySelector('video');
        video.addEventListener('play', () => {
            document.querySelectorAll('.media-message video').forEach(v => {
                if (v !== video) v.pause();
            });
            currentPlayingVideo = video;
        });
        video.addEventListener('pause', () => {
            if (currentPlayingVideo === video) {
                currentPlayingVideo = null;
            }
        });
        video.addEventListener('ended', () => {
            if (currentPlayingVideo === video) {
                currentPlayingVideo = null;
            }
        });
    }
}

// 加载新消息（添加AJAX请求头 + 消息去重）
function loadNewMessages() {
    fetch(window.location.href + '?get_messages=1', {
        cache: 'no-cache',
        headers: {
            'X-Requested-With': 'XMLHttpRequest' // 标识AJAX请求
        }
    })
        .then(response => {
            if (!response.ok) throw new Error('网络响应异常');
            return response.json();
        })
        .then(messages => {
            messages.forEach(msg => {
                if (!existingMessageIds.has(msg.id)) {
                    displayMessage(msg);
                    existingMessageIds.add(msg.id); // 加入去重集合
                }
            });
            // 清理过期消息ID，防止Set过大
            if (existingMessageIds.size > 1000) {
                const oldIds = Array.from(existingMessageIds).slice(0, 500);
                oldIds.forEach(id => existingMessageIds.delete(id));
            }
        })
        .catch(err => {
            console.error('加载新消息失败:', err);
            showCustomAlert('加载新消息失败，请检查网络！'); // 使用自定义弹窗
        });
}

// 加载所有消息（添加AJAX请求头 + 清空去重集合）
function loadAllMessages() {
    existingMessageIds.clear(); // 清空去重集合
    fetch(window.location.href + '?get_messages=1', {
        cache: 'no-cache',
        headers: {
            'X-Requested-With': 'XMLHttpRequest' // 标识AJAX请求
        }
    })
        .then(response => {
            if (!response.ok) throw new Error('网络响应异常');
            return response.json();
        })
        .then(messages => {
            document.getElementById('chatContent').innerHTML = '';
            messages.forEach(displayMessage);
            // 所有消息加入去重集合
            messages.forEach(msg => existingMessageIds.add(msg.id));
            scrollToLatestMessage();
            setTimeout(scrollToLatestMessage, 50);
        })
        .catch(err => {
            console.error('加载所有消息失败:', err);
            showCustomAlert('加载聊天记录失败，请检查网络！'); // 使用自定义弹窗
        });
}

// 发送文本消息（使用自定义弹窗，严格区分禁言和频率限制提示）
function sendTextMessage() {
    const input = document.getElementById('messageInput');
    const text = input.value.trim();
    if (!text) return;

    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'message=' + encodeURIComponent(text)
    })
        .then(response => {
            // 仅HTTP状态码异常时进入catch
            if (!response.ok) throw new Error('服务器响应异常，请稍后重试');
            return response.json();
        })
        .then(data => {
            // 业务状态判断：成功/失败（禁言/频率限制/其他业务错误）
            if (data.status === 'success') {
                input.value = '';
                updateSendButton();
                loadNewMessages();
            } else {
                // 使用自定义弹窗展示提示，自动区分禁言和频率限制
                showCustomAlert(data.message);
            }
        })
        .catch(err => {
            // 仅处理系统异常（网络/服务器错误）
            console.error('发送文本消息系统异常:', err);
            showCustomAlert('发送失败，网络或服务器异常！');
        });
}

// 发送媒体消息（使用自定义弹窗）
function sendMediaMessage(file) {
    const formData = new FormData();
    formData.append('media_file', file);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
        .then(response => {
            if (!response.ok) throw new Error('服务器响应异常，请稍后重试');
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                loadNewMessages();
            } else {
                // 使用自定义弹窗展示提示
                showCustomAlert(data.message);
            }
        })
        .catch(err => {
            console.error('发送媒体消息系统异常:', err);
            showCustomAlert('文件上传失败，网络或服务器异常！');
        });
}

// 更新发送按钮状态
function updateSendButton() {
    const input = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendButton');
    if (input.value.trim()) {
        sendBtn.textContent = '发送';
        sendBtn.classList.remove('plus');
    } else {
        sendBtn.textContent = '+';
        sendBtn.classList.add('plus');
    }
}

// 显示身份信息
function showIdentityInfo() {
    const userIdentity = <?php echo json_encode($_SESSION['user_identity'], JSON_UNESCAPED_UNICODE); ?>;
    const identityInfo = document.getElementById('identityInfo');
    identityInfo.textContent = `身份: ${userIdentity.name}`;
    setTimeout(() => {
        identityInfo.style.opacity = '0';
        setTimeout(() => identityInfo.style.display = 'none', 1000);
    }, 3000);
}

// 初始化表情面板
function initEmojiPicker() {
    const emojiPicker = document.getElementById('emojiPicker');
    const emojiButton = document.getElementById('emojiButton');
    const emojiTabs = document.getElementById('emojiTabs');
    const emojiGrid = document.getElementById('emojiGrid');
    const messageInput = document.getElementById('messageInput');

    emojiTabs.addEventListener('click', (e) => {
        if (e.target.classList.contains('emoji-tab')) {
            document.querySelectorAll('.emoji-tab').forEach(tab => tab.classList.remove('active'));
            e.target.classList.add('active');
            renderEmojiGrid(e.target.dataset.tab);
        }
    });

    function renderEmojiGrid(tabName) {
        emojiGrid.innerHTML = '';
        const emojis = emojiData[tabName] || [];
        emojis.forEach(emoji => {
            const emojiItem = document.createElement('div');
            emojiItem.className = 'emoji-item';
            emojiItem.textContent = emoji;
            emojiItem.addEventListener('click', (e) => {
                e.stopPropagation(); // 防止点击表情关闭面板
                messageInput.value += emoji;
                updateSendButton();
                emojiPicker.classList.remove('show');
            });
            emojiGrid.appendChild(emojiItem);
        });
    }

    emojiButton.addEventListener('click', () => {
        emojiPicker.classList.toggle('show');
        if (emojiPicker.classList.contains('show')) {
            const activeTab = document.querySelector('.emoji-tab.active');
            renderEmojiGrid(activeTab.dataset.tab);
        }
    });

    document.addEventListener('click', (e) => {
        if (!emojiPicker.contains(e.target) && e.target !== emojiButton) {
            emojiPicker.classList.remove('show');
        }
    });

    // 表情面板点击不冒泡
    emojiGrid.addEventListener('click', (e) => {
        e.stopPropagation();
    });
}

// 页面初始化
document.addEventListener('DOMContentLoaded', () => {
    setClientInfoCookies();
    const userIdentity = <?php echo json_encode($_SESSION['user_identity'], JSON_UNESCAPED_UNICODE); ?>;
    showIdentityInfo();
    initEmojiPicker();
    loadAllMessages();
    setInterval(loadNewMessages, 5000);

    const input = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendButton');
    const fileInput = document.getElementById('file-input');

    input.focus(); // 页面加载后自动聚焦输入框
    input.addEventListener('input', updateSendButton);
    sendBtn.addEventListener('click', () => {
        if (input.value.trim()) {
            sendTextMessage();
        } else {
            fileInput.click();
        }
    });
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            sendMediaMessage(e.target.files[0]);
            fileInput.value = '';
        }
    });
    input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && input.value.trim()) {
            e.preventDefault();
            sendTextMessage();
        }
    });

    // 欢迎消息
    displayMessage({
        id: 'welcome-message-' + Date.now(),
        text: `欢迎加入聊天室！您的匿名身份是 ${userIdentity.name}，此身份仅在此次会话有效`,
        color: '#888',
        name: '系统',
        time: '<?php echo date('H:i'); ?>',
        is_self: false,
        type: 'text'
    });

    updateSendButton();
    window.addEventListener('load', scrollToLatestMessage);
    scrollToLatestMessage();
});
    </script>
    
    <!-- Fancybox JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0.33/dist/fancybox/fancybox.umd.js"></script>
    <script>
        // 注册Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('service-worker.js')
                    .then(function(registration) {
                        console.log('ServiceWorker 注册成功:', registration.scope);
                    })
                    .catch(function(error) {
                        console.log('ServiceWorker 注册失败:', error);
                    });
            });
        }

        // PWA安装提示
        let deferredPrompt;
        const pwaPrompt = document.getElementById('pwa-install-prompt');
        const pwaInstall = document.getElementById('pwa-install');
        const pwaDismiss = document.getElementById('pwa-dismiss');
        const pwaInstallButton = document.getElementById('pwaInstallButton');

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            if (!localStorage.getItem('pwa_dismissed')) {
                pwaPrompt.style.display = 'block';
            }
        });

        if (pwaInstall) {
            pwaInstall.addEventListener('click', async () => {
                if (!deferredPrompt) return;
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                console.log(`用户选择: ${outcome}`);
                deferredPrompt = null;
                pwaPrompt.style.display = 'none';
            });
        }

        if (pwaDismiss) {
            pwaDismiss.addEventListener('click', () => {
                pwaPrompt.style.display = 'none';
                localStorage.setItem('pwa_dismissed', 'true');
            });
        }

        if (pwaInstallButton) {
            pwaInstallButton.addEventListener('click', () => {
                if (deferredPrompt) {
                    pwaPrompt.style.display = 'block';
                } else {
                    showCustomAlert('请稍后再试，PWA安装准备中...');
                }
            });
        }
        // 主题切换功能
        document.addEventListener('DOMContentLoaded', function() {
            // 从本地存储加载主题偏好
            const savedTheme = localStorage.getItem('chat_theme') || 'light';
            const htmlElement = document.documentElement;
            const themeToggle = document.getElementById('themeToggle');
            
            // 初始化主题
            function initTheme() {
                if (savedTheme === 'dark') {
                    htmlElement.setAttribute('data-theme', 'dark');
                    themeToggle.textContent = '☀️';
                } else {
                    htmlElement.removeAttribute('data-theme');
                    themeToggle.textContent = '🌙';
                }
            }
            
            // 切换主题
            function toggleTheme() {
                if (htmlElement.hasAttribute('data-theme')) {
                    // 切换到浅色模式
                    htmlElement.removeAttribute('data-theme');
                    themeToggle.textContent = '🌙';
                    localStorage.setItem('chat_theme', 'light');
                } else {
                    // 切换到深色模式
                    htmlElement.setAttribute('data-theme', 'dark');
                    themeToggle.textContent = '☀️';
                    localStorage.setItem('chat_theme', 'dark');
                }
            }
            
            // 初始化主题
            initTheme();
            
            // 添加主题切换事件
            if (themeToggle) {
                themeToggle.addEventListener('click', toggleTheme);
            }
            
            // 初始化 Fancybox
            Fancybox.bind("[data-fancybox]", {
                // 配置选项
                Thumbs: {
                    autoStart: false
                },
                Toolbar: {
                    display: {
                        left: [],
                        middle: [],
                        right: [
                            "zoomIn",
                            "zoomOut",
                            "toggle1to1",
                            "rotateCCW",
                            "rotateCW",
                            "flipX",
                            "flipY",
                            "close"
                        ]
                    }
                }
            });
        });
    </script>
</body>
</html>