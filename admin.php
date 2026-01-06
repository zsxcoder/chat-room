<?php
session_start(); // 移到最顶部，优先于所有代码/引入操作
define('CHAT_SYSTEM', true); // 允许加载配置文件
require_once 'config.php';
// 管理员登出
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_logged']);
    header('Location: admin.php');
    exit;
}

// 处理历史用户直接禁言/取消禁言
if (isset($_SESSION['admin_logged']) && isset($_GET['action']) && isset($_GET['user_id']) && in_array($_GET['action'], ['ban', 'unban'])) {
    $action = $_GET['action'];
    $targetUserId = trim($_GET['user_id']);
    $configFile = 'chat_system_config.json';
    $currentTab = isset($_GET['tab']) ? trim($_GET['tab']) : 'user-manage';
    $currentPage = isset($_GET['page_user']) ? intval($_GET['page_user']) : 1;
    $currentPage = $currentPage < 1 ? 1 : $currentPage;
    

    if (!file_exists($configFile)) {
        $initConfig = [
            'message_limit' => ['per_minute' => 10, 'enable' => true],
            'user_blacklist' => []
        ];
        file_put_contents($configFile, json_encode($initConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    $config = json_decode(file_get_contents($configFile), true);
    if (!is_array($config)) {
        $config = ['message_limit' => ['per_minute' => 10, 'enable' => true], 'user_blacklist' => []];
    }
    $blacklist = isset($config['user_blacklist']) ? $config['user_blacklist'] : [];
    $blacklist = is_array($blacklist) ? $blacklist : [];

    if ($action === 'ban') {
        if (!in_array($targetUserId, $blacklist)) {
            $blacklist[] = $targetUserId;
            $config['user_blacklist'] = $blacklist;
            file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        }
    } elseif ($action === 'unban') {
        $newBlacklist = [];
        foreach ($blacklist as $uid) {
            if (trim($uid) !== $targetUserId) {
                $newBlacklist[] = $uid;
            }
        }
        $config['user_blacklist'] = $newBlacklist;
        file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    $redirectUrl = "admin.php?tab=" . urlencode($currentTab) . "&page_user=" . $currentPage;
    header("Location: " . $redirectUrl);
    exit;
}

// 新增：删除单条消息功能
if (isset($_SESSION['admin_logged']) && isset($_GET['action']) && $_GET['action'] === 'delete_msg' && isset($_GET['msg_id'])) {
    $targetMsgId = trim($_GET['msg_id']);
    $chatFile = 'chat_messages.json';
    $currentTab = isset($_GET['tab']) ? trim($_GET['tab']) : 'message-manage';
    $currentPage = isset($_GET['page_msg']) ? intval($_GET['page_msg']) : 1;
    $filterKeyword = isset($_GET['filter_keyword']) ? urlencode(trim($_GET['filter_keyword'])) : '';

    if (file_exists($chatFile)) {
        $messages = json_decode(file_get_contents($chatFile), true);
        if (is_array($messages)) {
            $newMessages = array_filter($messages, function($msg) use ($targetMsgId) {
                return $msg['id'] !== $targetMsgId;
            });
            $newMessages = array_values($newMessages);
            file_put_contents($chatFile, json_encode($newMessages, JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
    }

    $redirectUrl = "admin.php?tab=" . urlencode($currentTab) . "&page_msg=" . $currentPage;
    if (!empty($filterKeyword)) {
        $redirectUrl .= "&filter_keyword=" . $filterKeyword;
    }
    header("Location: " . $redirectUrl);
    exit;
}

// 管理员登录验证
if (!isset($_SESSION['admin_logged']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    if (checkAdminLogin($username, $password)) {
        $_SESSION['admin_logged'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $loginError = '账号或密码错误！';
    }
}

// 仅管理员可访问后台功能
if (!isset($_SESSION['admin_logged'])) {
    // 登录界面
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>聊天室后台管理 - 登录</title>
    <style>
        /* 浅色模式变量 */
        :root {
            --bg-primary: #f5f7fa;
            --bg-secondary: #ffffff;
            --bg-tertiary: #f8fafc;
            --bg-input: #f8fafc;
            --text-primary: #2c3e50;
            --text-secondary: #34495e;
            --text-tertiary: #7f8c8d;
            --border-color: #e0e6ed;
            --border-light: #eef2f7;
            --accent-color: #07C160;
            --accent-hover: #06b058;
            --accent-light: #f0fbf4;
            --error-color: #e74c3c;
            --error-light: #fef5f5;
            --success-color: #27ae60;
            --success-light: #f0f9f0;
            --shadow-light: 0 8px 32px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 4px 12px rgba(0, 0, 0, 0.06);
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
            --error-color: #e74c3c;
            --error-light: #3a1a1a;
            --success-color: #27ae60;
            --success-light: #1a3a25;
            --shadow-light: 0 8px 32px rgba(0, 0, 0, 0.3);
            --shadow-medium: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Microsoft YaHei", "Helvetica Neue", Arial, sans-serif;
        }
        body {
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-tertiary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            padding: 20px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .login-box {
            background: var(--bg-secondary);
            padding: 40px;
            border-radius: 16px;
            box-shadow: var(--shadow-light);
            width: 100%;
            max-width: 400px;
            transition: all 0.3s ease;
        }
        .login-box:hover {
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }
        .login-box h2 {
            text-align: center;
            margin-bottom: 30px;
            color: var(--text-primary);
            font-size: 24px;
            font-weight: 600;
        }
        .form-item {
            margin-bottom: 20px;
        }
        .form-item label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
        }
        .form-item input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            outline: none;
            font-size: 15px;
            transition: all 0.3s ease;
            background: var(--bg-input);
            color: var(--text-primary);
        }
        .form-item input:focus {
            border-color: var(--accent-color);
            background: var(--bg-secondary);
            box-shadow: 0 0 0 3px rgba(7, 193, 96, 0.1);
        }
        .login-btn {
            width: 100%;
            padding: 12px;
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        .login-btn:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
        }
        .error-tip {
            color: var(--error-color);
            text-align: center;
            margin-bottom: 20px;
            font-size: 14px;
            padding: 10px;
            border-radius: 8px;
            background: var(--error-light);
            border: 1px solid var(--border-color);
        }
    </style>
</head>
<body>
    <div class="login-box">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>后台管理登录</h2>
            <button class="theme-toggle" id="themeToggle" title="切换主题" style="background: none; border: none; cursor: pointer; font-size: 20px; color: var(--text-secondary); padding: 4px; border-radius: 4px; transition: all 0.3s ease;">🌙</button>
        </div>
        <?php if (isset($loginError)) { echo '<div class="error-tip">'.$loginError.'</div>'; } ?>
        <div style="text-align: center; margin-bottom: 20px;">
            <a href="index.php" style="color: var(--accent-color); text-decoration: none; font-size: 14px;">← 回到聊天室</a>
        </div>
        <form method="post" action="admin.php">
            <div class="form-item">
                <label for="username">管理员账号</label>
                <input type="text" id="username" name="username" required placeholder="请输入账号">
            </div>
            <div class="form-item">
                <label for="password">管理员密码</label>
                <input type="password" id="password" name="password" required placeholder="请输入密码">
            </div>
            <button type="submit" name="login" class="login-btn">登录</button>
        </form>
    </div>
</body>
    
    <!-- 主题切换功能 -->
    <script>
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
                    if (themeToggle) themeToggle.textContent = '☀️';
                } else {
                    htmlElement.removeAttribute('data-theme');
                    if (themeToggle) themeToggle.textContent = '🌙';
                }
            }
            
            // 切换主题
            function toggleTheme() {
                if (htmlElement.hasAttribute('data-theme')) {
                    // 切换到浅色模式
                    htmlElement.removeAttribute('data-theme');
                    if (themeToggle) themeToggle.textContent = '🌙';
                    localStorage.setItem('chat_theme', 'light');
                } else {
                    // 切换到深色模式
                    htmlElement.setAttribute('data-theme', 'dark');
                    if (themeToggle) themeToggle.textContent = '☀️';
                    localStorage.setItem('chat_theme', 'dark');
                }
            }
            
            // 初始化主题
            initTheme();
            
            // 添加主题切换事件
            if (themeToggle) {
                themeToggle.addEventListener('click', toggleTheme);
            }
            
            // 注册Service Worker
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('service-worker.js')
                    .then(function(registration) {
                        console.log('ServiceWorker 注册成功:', registration.scope);
                    })
                    .catch(function(error) {
                        console.log('ServiceWorker 注册失败:', error);
                    });
            }
        });
    </script>
</html>
<?php
    exit;
}

// 后台功能处理
// 1. 更新消息频率限制
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_limit'])) {
    $perMinute = intval($_POST['per_minute']);
    $enable = isset($_POST['enable_limit']) ? true : false;
    $newConfig = [
        'message_limit' => [
            'per_minute' => $perMinute > 0 ? $perMinute : 1,
            'enable' => $enable
        ]
    ];
    updateChatConfig($newConfig);
    $currentTab = isset($_POST['tab']) ? trim($_POST['tab']) : 'message-limit';
    $redirectUrl = "admin.php?tab=" . urlencode($currentTab) . "&success=1";
    header("Location: " . $redirectUrl);
    exit;
}

// 2. 清除所有聊天数据
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_chat'])) {
    $chatFile = 'chat_messages.json';
    file_put_contents($chatFile, json_encode([]), LOCK_EX);
    $currentTab = isset($_POST['tab']) ? trim($_POST['tab']) : 'data-clear';
    header("Location: admin.php?tab=" . urlencode($currentTab));
    exit;
}

// 3. 清除用户消息发送日志
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    $userLogFile = 'user_message_logs.json';
    if (file_exists($userLogFile)) {
        unlink($userLogFile);
    }
    $currentTab = isset($_POST['tab']) ? trim($_POST['tab']) : 'data-clear';
    header("Location: admin.php?tab=" . urlencode($currentTab));
    exit;
}

// 获取当前配置
$config = getChatConfig();
$messageLimit = $config['message_limit'] ?? ['per_minute' => 10, 'enable' => true];
$blacklist = $config['user_blacklist'] ?? [];
$blacklist = is_array($blacklist) ? $blacklist : [];

// 获取所有用户（从聊天消息中提取）
$chatFile = 'chat_messages.json';
$messages = file_exists($chatFile) ? json_decode(file_get_contents($chatFile), true) : [];
$allUsers = [];
if (is_array($messages)) {
    foreach ($messages as $msg) {
        if (isset($msg['user_id'], $msg['name'], $msg['color'])) {
            $userId = trim($msg['user_id']);
            $allUsers[$userId] = [
                'name' => $msg['name'],
                'color' => $msg['color']
            ];
        }
    }
}

// 消息列表：分页配置
$filterKeyword = isset($_GET['filter_keyword']) ? trim($_GET['filter_keyword']) : '';
$filteredMessages = $messages;
if (!empty($filterKeyword) && is_array($filteredMessages)) {
    $filteredMessages = array_filter($filteredMessages, function($msg) use ($filterKeyword) {
        if ($msg['type'] === 'text' && isset($msg['text'])) {
            return strpos($msg['text'], $filterKeyword) !== false;
        }
        return false;
    });
    $filteredMessages = array_values($filteredMessages);
}
$pageSize = 10;
$pageMsg = isset($_GET['page_msg']) ? intval($_GET['page_msg']) : 1;
$pageMsg = $pageMsg < 1 ? 1 : $pageMsg;
$totalMsg = count($filteredMessages);
$totalPageMsg = ceil($totalMsg / $pageSize);
$offsetMsg = ($pageMsg - 1) * $pageSize;
$pagedMessages = array_slice($filteredMessages, $offsetMsg, $pageSize);

// 用户列表：分页配置
$pageUser = isset($_GET['page_user']) ? intval($_GET['page_user']) : 1;
$pageUser = $pageUser < 1 ? 1 : $pageUser;
$totalUser = count($allUsers);
$totalPageUser = ceil($totalUser / $pageSize);
$offsetUser = ($pageUser - 1) * $pageSize;
$allUserIds = array_keys($allUsers);
$pagedUserIds = array_slice($allUserIds, $offsetUser, $pageSize);
$pagedUsersAssoc = [];
foreach ($pagedUserIds as $userId) {
    $pagedUsersAssoc[$userId] = $allUsers[$userId];
}

// 获取当前激活的选项卡
$activeTab = isset($_GET['tab']) ? trim($_GET['tab']) : 'message-limit';
$showSuccessTip = isset($_GET['success']) && $_GET['success'] == 1;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>聊天室后台管理系统</title>
    <link rel="icon" href="public/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="public/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="public/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="public/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="public/favicon-16x16.png">
    <link rel="manifest" href="public/site.webmanifest">
    <meta name="theme-color" content="#07C160">
    <meta name="description" content="匿名聊天室管理员面板">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="聊天室管理">
    <style>
        /* 浅色模式变量 */
        :root {
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --bg-tertiary: #f8fafc;
            --bg-input: #f8fafc;
            --text-primary: #2c3e50;
            --text-secondary: #34495e;
            --text-tertiary: #7f8c8d;
            --border-color: #eef2f7;
            --border-light: #f5f7fa;
            --accent-color: #07C160;
            --accent-hover: #06b058;
            --accent-light: #f0fbf4;
            --error-color: #e74c3c;
            --error-light: #fef5f5;
            --success-color: #27ae60;
            --success-light: #f0f9f0;
            --shadow-light: 0 4px 20px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 2px 10px rgba(0, 0, 0, 0.05);
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
            --error-color: #e74c3c;
            --error-light: #3a1a1a;
            --success-color: #27ae60;
            --success-light: #1a3a25;
            --shadow-light: 0 4px 20px rgba(0, 0, 0, 0.2);
            --shadow-medium: 0 2px 10px rgba(0, 0, 0, 0.15);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Microsoft YaHei", "Helvetica Neue", Arial, sans-serif;
        }
        body {
            background: var(--bg-primary);
            padding: 15px;
            color: var(--text-primary);
            font-size: 14px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            background: var(--bg-secondary);
            border-radius: 16px;
            box-shadow: var(--shadow-light);
            overflow: hidden;
            width: 100%;
        }
        .admin-header {
            background: linear-gradient(135deg, var(--accent-color) 0%, var(--accent-hover) 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        .admin-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-color), #95EC69);
        }
        .admin-header h2 {
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .logout-btn {
            color: white;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        .admin-content {
            padding: 0 15px 15px;
        }
        .tab-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            padding: 15px 0 0;
            border-bottom: 1px solid var(--border-color);
        }
        .tab-nav-item {
            padding: 10px 15px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-bottom: none;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            transition: all 0.3s ease;
            flex-shrink: 0;
            text-decoration: none;
        }
        .tab-nav-item:hover {
            background: var(--accent-light);
            color: var(--accent-color);
        }
        .tab-nav-item.active {
            background: var(--bg-secondary);
            color: var(--accent-color);
            border-color: var(--accent-color);
            border-bottom-color: var(--bg-secondary);
            position: relative;
            z-index: 10;
        }
        .tab-content {
            position: relative;
            min-height: 300px;
            padding: 15px;
        }
        .tab-content-item {
            display: none;
            width: 100%;
        }
        .tab-content-item.active {
            display: block;
            animation: fadeIn 0.3s ease-in-out;
        }
        .tab-item {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            background: var(--bg-secondary);
            transition: all 0.3s ease;
            width: 100%;
        }
        .tab-item:hover {
            box-shadow: var(--shadow-medium);
        }
        .tab-header {
            background: var(--bg-tertiary);
            padding: 12px 15px;
            font-size: 15px;
            font-weight: 600;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            display: flex;
            align-items: center;
        }
        .tab-header::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 16px;
            background: var(--accent-color);
            margin-right: 10px;
            border-radius: 2px;
        }
        .tab-body {
            padding: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 500;
        }
        .form-group input {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            outline: none;
            font-size: 13px;
            width: 100%;
            transition: all 0.3s ease;
            background: var(--bg-input);
            color: var(--text-primary);
        }
        .form-group input:focus {
            border-color: var(--accent-color);
            background: var(--bg-secondary);
            box-shadow: 0 0 0 3px rgba(7, 193, 96, 0.1);
        }
        .btn {
            padding: 8px 15px;
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(7, 193, 96, 0.2);
        }
        .btn-danger {
            background: var(--error-color);
        }
        .btn-danger:hover {
            background: #c0392b;
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.2);
        }
        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
            white-space: nowrap;
        }
        .btn-success {
            background: var(--success-color);
        }
        .btn-success:hover {
            background: #27ae60;
            box-shadow: 0 4px 12px rgba(46, 204, 113, 0.2);
        }
        .btn-default {
            background: var(--text-tertiary);
        }
        .btn-default:hover {
            background: #7f8c8d;
            box-shadow: 0 4px 12px rgba(149, 165, 166, 0.2);
        }
        .tip-success {
            color: var(--success-color);
            margin-bottom: 15px;
            font-size: 13px;
            padding: 10px 12px;
            border-radius: 8px;
            background: var(--success-light);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tip-success::before {
            content: '✓';
            display: inline-block;
            width: 18px;
            height: 18px;
            background: var(--success-color);
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 18px;
            font-size: 11px;
        }
        .tip-error {
            color: var(--error-color);
            margin-bottom: 15px;
            font-size: 13px;
            padding: 10px 12px;
            border-radius: 8px;
            background: var(--error-light);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tip-error::before {
            content: '×';
            display: inline-block;
            width: 18px;
            height: 18px;
            background: var(--error-color);
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 18px;
            font-size: 11px;
        }
        .table-search-wrapper {
            background: var(--bg-tertiary);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 18px 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .table-search-wrapper:hover {
            box-shadow: var(--shadow-medium);
            border-color: var(--accent-light);
        }
        .search-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .search-title::after {
            content: '';
            width: 6px;
            height: 6px;
            background: var(--accent-color);
            border-radius: 50%;
        }
        .empty-tip {
            color: var(--text-tertiary);
            font-size: 13px;
            padding: 30px 20px;
            border-radius: 12px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            text-align: center;
            margin: 10px 0;
            transition: all 0.3s ease;
        }
        .empty-tip:hover {
            box-shadow: var(--shadow-medium);
        }
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 12px;
            box-shadow: var(--shadow-medium);
            margin: 10px 0 20px;
            transition: all 0.3s ease;
        }
        .table-wrapper:hover {
            box-shadow: var(--shadow-light);
        }
        .data-table {
            width: 100%;
            min-width: 600px;
            border-collapse: collapse;
            border-radius: 12px;
            overflow: hidden;
        }
        .data-table thead {
            background: linear-gradient(135deg, var(--bg-tertiary) 0%, var(--accent-light) 100%);
        }
        .data-table th {
            padding: 12px 15px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            font-size: 12px;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
            transition: all 0.2s ease;
        }
        .data-table tbody tr {
            background: var(--bg-secondary);
        }
        .data-table tbody tr:hover {
            background: var(--bg-tertiary);
            transform: translateX(2px);
        }
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        .status-tag {
            padding: 3px 10px;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 500;
            white-space: nowrap;
        }
        .status-normal {
            background: var(--success-light);
            color: var(--success-color);
        }
        .status-ban {
            background: var(--error-light);
            color: var(--error-color);
        }
        .msg-type-tag {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 500;
            white-space: nowrap;
        }
        .msg-type-text {
            background: var(--success-light);
            color: var(--success-color);
        }
        .msg-type-image {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }
        .msg-type-video {
            background: rgba(230, 126, 34, 0.1);
            color: #e67e22;
        }
        .avatar-color {
            width: 20px;
            height: 20px;
            border-radius: 6px;
            display: inline-block;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--border-color);
        }
        .preview-link {
            color: #3498db;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .preview-link:hover {
            color: #2980b9;
            text-decoration: underline;
        }
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            padding: 10px 0;
            margin-top: 10px;
        }
        .pagination a {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            white-space: nowrap;
            background: var(--bg-secondary);
        }
        .pagination a:hover {
            background: var(--accent-light);
            border-color: var(--accent-color);
            color: var(--accent-color);
            transform: translateY(-2px);
        }
        .pagination a.active {
            background: linear-gradient(135deg, var(--accent-color), var(--accent-hover));
            color: white;
            border-color: var(--accent-color);
            box-shadow: 0 2px 8px rgba(7, 193, 96, 0.2);
        }
        .pagination a.disabled {
            color: var(--text-tertiary);
            border-color: var(--border-color);
            cursor: not-allowed;
            background: var(--bg-tertiary);
        }
        .pagination a.disabled:hover {
            color: var(--text-tertiary);
            border-color: var(--border-color);
            background: var(--bg-tertiary);
            transform: none;
        }
        .clear-wrapper {
            display: flex;
            flex-direction: column;
            gap: 20px;
            padding: 10px 0;
        }
        .clear-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--bg-tertiary);
            padding: 18px 20px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        .clear-item:hover {
            box-shadow: var(--shadow-medium);
            border-color: var(--accent-light);
            background: var(--accent-light);
        }
        .clear-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .clear-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
        }
        .clear-desc {
            font-size: 12px;
            color: var(--text-tertiary);
            line-height: 1.4;
        }
        .clear-btn-wrap {
            flex-shrink: 0;
        }
        .clear-chat-btn {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
        }
        .clear-chat-btn:hover {
            box-shadow: 0 6px 16px rgba(231, 76, 60, 0.25);
            transform: translateY(-2px);
        }
        .clear-logs-btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
        }
        .clear-logs-btn:hover {
            box-shadow: 0 6px 16px rgba(52, 152, 219, 0.25);
            transform: translateY(-2px);
        }
        .limit-wrapper {
            background: var(--bg-tertiary);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 20px;
            transition: all 0.3s ease;
        }
        .limit-wrapper:hover {
            box-shadow: var(--shadow-medium);
            border-color: var(--accent-light);
            background: var(--accent-light);
        }
        .limit-form-item {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .limit-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .limit-label::after {
            content: '';
            display: inline-block;
            width: 6px;
            height: 6px;
            background: var(--accent-color);
            border-radius: 50%;
        }
        .limit-input {
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            outline: none;
            font-size: 14px;
            background: var(--bg-input);
            color: var(--text-primary);
            transition: all 0.3s ease;
            width: 100%;
            max-width: 300px;
        }
        .limit-input:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(7, 193, 96, 0.1);
            background: var(--bg-secondary);
        }
        .limit-desc {
            font-size: 12px;
            color: var(--text-tertiary);
            margin-top: -5px;
            line-height: 1.4;
        }
        .switch-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
        }
        .switch-checkbox {
            appearance: none;
            width: 40px;
            height: 20px;
            background: var(--border-color);
            border-radius: 10px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .switch-checkbox:checked {
            background: var(--accent-color);
        }
        .switch-checkbox::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            background: #ffffff;
            border-radius: 50%;
            top: 2px;
            left: 2px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .switch-checkbox:checked::after {
            left: 22px;
        }
        .switch-label {
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 500;
            cursor: pointer;
        }
        .limit-submit-btn {
            background: linear-gradient(135deg, #07C160, #06b058);
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            margin-top: 10px;
        }
        .limit-submit-btn:hover {
            box-shadow: 0 6px 16px rgba(7, 193, 96, 0.25);
            transform: translateY(-2px);
        }
        .media-preview-mask {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
            padding: 15px;
        }
        .media-preview-mask.show {
            display: flex;
            animation: fadeIn 0.3s ease-in-out;
        }
        .media-preview-box {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            max-width: 90%;
            max-height: 80%;
            overflow: auto;
            position: relative;
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.3);
        }
        .preview-close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 30px;
            height: 30px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .preview-close-btn:hover {
            background: #c0392b;
            transform: rotate(90deg);
        }
        .preview-title {
            text-align: center;
            margin-bottom: 15px;
            color: #2c3e50;
            font-size: 16px;
            font-weight: 600;
        }
        .preview-content {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .preview-content img, .preview-content video {
            max-width: 100%;
            max-height: 60vh;
            border-radius: 8px;
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
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
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h2>匿名聊天室后台管理系统</h2>
            <div class="header-actions">
            <a href="index.php" class="logout-btn">回到聊天室</a>
            <button class="theme-toggle" id="themeToggle" title="切换主题" style="background: rgba(255, 255, 255, 0.2); border: none; cursor: pointer; font-size: 20px; color: white; padding: 6px 10px; border-radius: 8px; transition: all 0.3s ease; margin: 0 8px;">🌙</button>
            <a href="admin.php?action=logout" class="logout-btn">安全登出</a>
        </div>
        </div>
        <div class="admin-content">
            <?php if (isset($operateMsg)) { echo '<div class="tip-success">'.$operateMsg.'</div>'; } ?>
            <?php if (isset($adminError)) { echo '<div class="tip-error">'.$adminError.'</div>'; } ?>

            <!-- 选项卡导航 -->
            <div class="tab-nav">
                <a href="?tab=message-limit" class="tab-nav-item <?php echo $activeTab === 'message-limit' ? 'active' : ''; ?>">消息频率限制</a>
                <a href="?tab=message-manage" class="tab-nav-item <?php echo $activeTab === 'message-manage' ? 'active' : ''; ?>">消息列表管理</a>
                <a href="?tab=user-manage" class="tab-nav-item <?php echo $activeTab === 'user-manage' ? 'active' : ''; ?>">用户管理</a>
                <a href="?tab=data-clear" class="tab-nav-item <?php echo $activeTab === 'data-clear' ? 'active' : ''; ?>">数据清理</a>
            </div>

            <!-- 选项卡内容容器 -->
            <div class="tab-content">
                <!-- 1. 消息频率限制 -->
                <div class="tab-content-item <?php echo $activeTab === 'message-limit' ? 'active' : ''; ?>" id="message-limit">
                    <div class="tab-item">
                        <div class="tab-header">消息发送频率限制</div>
                        <div class="tab-body">
                            <form method="post" action="admin.php">
                                <input type="hidden" name="tab" value="message-limit">
                                <div class="limit-wrapper">
                                    <div class="limit-form-item">
                                        <label class="limit-label">每分钟最多发送消息数</label>
                                        <input type="number" name="per_minute" class="limit-input" 
                                               value="<?php echo $messageLimit['per_minute']; ?>" min="1" required>
                                        <div class="limit-desc">设置单个用户每分钟内可发送的最大消息数量，最小值为1，建议设置为10-20条</div>
                                    </div>
                                    <!-- 保存频率设置成功提示 -->
<?php if ($activeTab === 'message-limit' && $showSuccessTip) { ?>
    <div class="tip-success">消息频率设置已成功保存！</div>
<?php } ?>
                                    <div class="limit-form-item switch-group">
                                        <input type="checkbox" name="enable_limit" class="switch-checkbox" 
                                               id="enableLimit" <?php echo $messageLimit['enable'] ? 'checked' : ''; ?>>
                                        <label for="enableLimit" class="switch-label">启用消息频率限制</label>
                                    </div>
                                    <button type="submit" name="update_limit" class="btn limit-submit-btn">保存频率设置</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 2. 消息列表管理 -->
                <div class="tab-content-item <?php echo $activeTab === 'message-manage' ? 'active' : ''; ?>" id="message-manage">
                    <div class="tab-item">
                        <div class="tab-header">消息列表管理</div>
                        <div class="tab-body">
                            <div class="table-search-wrapper">
                                <div class="search-title">消息内容筛选</div>
                                <form method="get" action="admin.php">
                                    <input type="hidden" name="tab" value="message-manage">
                                    <input type="text" name="filter_keyword" class="form-group input filter-input" 
                                           placeholder="输入关键词筛选文本消息" value="<?php echo htmlspecialchars($filterKeyword); ?>">
                                    <button type="submit" class="btn">开始筛选</button>
                                    <?php if (!empty($filterKeyword)) { ?>
                                        <a href="?tab=message-manage" class="btn btn-default">清空筛选</a>
                                    <?php } ?>
                                </form>
                            </div>

                            <?php if (empty($filteredMessages)) { ?>
                                <div class="empty-tip">暂无消息记录（或未匹配到筛选关键词）</div>
                            <?php } else { ?>
                                <div class="table-wrapper">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>消息ID</th>
                                                <th>发送用户ID</th>
                                                <th>用户昵称</th>
                                                <th>消息类型</th>
                                                <th>消息内容/资源</th>
                                                <th>发送时间</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pagedMessages as $msg) { ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($msg['id']); ?></td>
                                                    <td><?php echo htmlspecialchars($msg['user_id'] ?? '未知'); ?></td>
                                                    <td><?php echo htmlspecialchars($msg['name'] ?? '未知用户'); ?></td>
                                                    <td>
                                                        <?php if ($msg['type'] === 'text') { ?>
                                                            <span class="msg-type-tag msg-type-text">文本</span>
                                                        <?php } elseif ($msg['type'] === 'image') { ?>
                                                            <span class="msg-type-tag msg-type-image">图片</span>
                                                        <?php } elseif ($msg['type'] === 'video') { ?>
                                                            <span class="msg-type-tag msg-type-video">视频</span>
                                                        <?php } else { ?>
                                                            <span class="msg-type-tag" style="background:#e9ecef;color:#6c757d;">未知</span>
                                                        <?php } ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($msg['type'] === 'text') { ?>
                                                            <?php echo htmlspecialchars($msg['text'] ?? '空文本'); ?>
                                                        <?php } elseif (in_array($msg['type'], ['image', 'video']) && isset($msg['media_url'])) { ?>
                                                            <a href="javascript:;" class="preview-link media-preview-link" 
                                                               data-media-url="<?php echo htmlspecialchars($msg['media_url']); ?>"
                                                               data-media-type="<?php echo htmlspecialchars($msg['type']); ?>">
                                                                点击查看<?php echo $msg['type']; ?>资源
                                                            </a>
                                                        <?php } else { ?>
                                                            -
                                                        <?php } ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($msg['time'] ?? '未知时间'); ?></td>
                                                    <td>
                                                        <a href="?tab=message-manage&action=delete_msg&msg_id=<?php echo urlencode($msg['id']); ?>&page_msg=<?php echo $pageMsg; ?>&filter_keyword=<?php echo urlencode($filterKeyword); ?>" 
                                                           class="btn btn-sm btn-danger" 
                                                           onclick="return confirm('确定要删除这条消息吗？此操作不可恢复！');">
                                                            删除
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="pagination">
                                    <a href="?tab=message-manage&page_msg=<?php echo $pageMsg - 1; ?>&filter_keyword=<?php echo urlencode($filterKeyword); ?>" 
                                       class="<?php echo $pageMsg <= 1 ? 'disabled' : ''; ?>"
                                       <?php if ($pageMsg <= 1) echo 'onclick="return false;"'; ?>>上一页</a>
                                    <?php for ($i = 1; $i <= $totalPageMsg; $i++) { ?>
                                        <a href="?tab=message-manage&page_msg=<?php echo $i; ?>&filter_keyword=<?php echo urlencode($filterKeyword); ?>" 
                                           class="<?php echo $i == $pageMsg ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                    <?php } ?>
                                    <a href="?tab=message-manage&page_msg=<?php echo $pageMsg + 1; ?>&filter_keyword=<?php echo urlencode($filterKeyword); ?>" 
                                       class="<?php echo $pageMsg >= $totalPageMsg ? 'disabled' : ''; ?>"
                                       <?php if ($pageMsg >= $totalPageMsg) echo 'onclick="return false;"'; ?>>下一页</a>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <!-- 3. 用户管理（关键修复：操作链接参数完整，逻辑简化） -->
                <div class="tab-content-item <?php echo $activeTab === 'user-manage' ? 'active' : ''; ?>" id="user-manage">
                    <div class="tab-item">
                        <div class="tab-header">用户管理</div>
                        <div class="tab-body">
                            <div class="search-title">历史聊天用户列表</div>

                            <?php if (empty($allUsers)) { ?>
                                <div class="empty-tip">暂无用户记录</div>
                            <?php } else { ?>
                                <div class="table-wrapper">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>用户ID</th>
                                                <th>用户昵称</th>
                                                <th>头像颜色</th>
                                                <th>账号状态</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pagedUsersAssoc as $userId => $userInfo) { 
                                                $isBanned = in_array($userId, $blacklist);
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($userId); ?></td>
                                                    <td><?php echo htmlspecialchars($userInfo['name']); ?></td>
                                                    <td><div class="avatar-color" style="background:<?php echo htmlspecialchars($userInfo['color']); ?>;"></div></td>
                                                    <td>
                                                        <?php if ($isBanned) { ?>
                                                            <span class="status-tag status-ban">黑名单（已禁言）</span>
                                                        <?php } else { ?>
                                                            <span class="status-tag status-normal">正常（可发言）</span>
                                                        <?php } ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($isBanned) { ?>
                                                            <!-- 取消禁言链接：参数完整，编码正确 -->
                                                            <a href="?tab=user-manage&action=unban&user_id=<?php echo urlencode($userId); ?>&page_user=<?php echo $pageUser; ?>" 
                                                               class="btn btn-sm btn-success" 
                                                               onclick="return confirm('确定要取消该用户的禁言吗？');">
                                                                取消禁言
                                                            </a>
                                                        <?php } else { ?>
                                                            <!-- 禁言链接：参数完整，编码正确 -->
                                                            <a href="?tab=user-manage&action=ban&user_id=<?php echo urlencode($userId); ?>&page_user=<?php echo $pageUser; ?>" 
                                                               class="btn btn-sm btn-danger" 
                                                               onclick="return confirm('确定要禁言该用户吗？禁言后用户无法发送消息！');">
                                                                立即禁言
                                                            </a>
                                                        <?php } ?>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="pagination">
                                    <a href="?tab=user-manage&page_user=<?php echo $pageUser - 1; ?>" 
                                       class="<?php echo $pageUser <= 1 ? 'disabled' : ''; ?>"
                                       <?php if ($pageUser <= 1) echo 'onclick="return false;"'; ?>>上一页</a>
                                    <?php for ($i = 1; $i <= $totalPageUser; $i++) { ?>
                                        <a href="?tab=user-manage&page_user=<?php echo $i; ?>" 
                                           class="<?php echo $i == $pageUser ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                    <?php } ?>
                                    <a href="?tab=user-manage&page_user=<?php echo $pageUser + 1; ?>" 
                                       class="<?php echo $pageUser >= $totalPageUser ? 'disabled' : ''; ?>"
                                       <?php if ($pageUser >= $totalPageUser) echo 'onclick="return false;"'; ?>>下一页</a>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <!-- 4. 数据清理 -->
                <div class="tab-content-item <?php echo $activeTab === 'data-clear' ? 'active' : ''; ?>" id="data-clear">
                    <div class="tab-item">
                        <div class="tab-header">数据清理</div>
                        <div class="tab-body">
                            <div class="clear-wrapper">
                                <div class="clear-item">
                                    <div class="clear-info">
                                        <div class="clear-title">清除所有聊天消息</div>
                                        <div class="clear-desc">此操作将删除所有历史聊天记录（文本、图片、视频），操作不可恢复，请谨慎执行！</div>
                                    </div>
                                    <div class="clear-btn-wrap">
                                        <form method="post" action="admin.php" onsubmit="return confirm('确定要清除所有聊天数据吗？此操作不可恢复！');">
                                            <input type="hidden" name="tab" value="data-clear">
                                            <button type="submit" name="clear_chat" class="btn clear-chat-btn">立即清除</button>
                                        </form>
                                    </div>
                                </div>
                                <div class="clear-item">
                                    <div class="clear-info">
                                        <div class="clear-title">清除用户消息发送日志</div>
                                        <div class="clear-desc">此操作将删除用户消息发送频率日志，仅重置日志记录，不影响聊天消息，可安全执行</div>
                                    </div>
                                    <div class="clear-btn-wrap">
                                        <form method="post" action="admin.php" onsubmit="return confirm('确定要清除用户消息日志吗？');">
                                            <input type="hidden" name="tab" value="data-clear">
                                            <button type="submit" name="clear_logs" class="btn clear-logs-btn">立即清除</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 媒体资源预览弹窗 DOM -->
    <div class="media-preview-mask" id="mediaPreviewMask">
        <div class="media-preview-box">
            <button class="preview-close-btn" id="previewCloseBtn">×</button>
            <div class="preview-title" id="previewTitle">媒体资源预览</div>
            <div class="preview-content" id="previewContent"></div>
        </div>
    </div>

    <script>
        // 选项卡切换逻辑
        const tabNavItems = document.querySelectorAll('.tab-nav-item');
        const tabContentItems = document.querySelectorAll('.tab-content-item');

        tabNavItems.forEach(item => {
            item.addEventListener('click', function(e) {
                // 保持URL跳转逻辑，确保状态保留
            });
        });

        // 媒体资源预览弹窗逻辑
        const mediaPreviewMask = document.getElementById('mediaPreviewMask');
        const previewCloseBtn = document.getElementById('previewCloseBtn');
        const previewContent = document.getElementById('previewContent');
        const previewTitle = document.getElementById('previewTitle');
        const mediaPreviewLinks = document.querySelectorAll('.media-preview-link');

        mediaPreviewLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const mediaUrl = this.getAttribute('data-media-url');
                const mediaType = this.getAttribute('data-media-type');
                const mediaTitle = mediaType === 'image' ? '图片预览' : '视频预览';

                previewContent.innerHTML = '';
                previewTitle.textContent = mediaTitle;

                if (mediaType === 'image') {
                    const img = document.createElement('img');
                    img.src = mediaUrl;
                    img.alt = '图片预览';
                    previewContent.appendChild(img);
                } else if (mediaType === 'video') {
                    const video = document.createElement('video');
                    video.controls = true;
                    const source = document.createElement('source');
                    source.src = mediaUrl;
                    source.type = 'video/mp4';
                    video.appendChild(source);
                    video.innerHTML += '您的浏览器不支持视频播放';
                    previewContent.appendChild(video);
                }

                mediaPreviewMask.classList.add('show');
            });
        });

        previewCloseBtn.addEventListener('click', function() {
            mediaPreviewMask.classList.remove('show');
        });

        mediaPreviewMask.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && mediaPreviewMask.classList.contains('show')) {
                mediaPreviewMask.classList.remove('show');
            }
        });}
    </script>
    
    <!-- 主题切换功能 -->
    <script>
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
                    if (themeToggle) themeToggle.textContent = '☀️';
                } else {
                    htmlElement.removeAttribute('data-theme');
                    if (themeToggle) themeToggle.textContent = '🌙';
                }
            }
            
            // 切换主题
            function toggleTheme() {
                if (htmlElement.hasAttribute('data-theme')) {
                    // 切换到浅色模式
                    htmlElement.removeAttribute('data-theme');
                    if (themeToggle) themeToggle.textContent = '🌙';
                    localStorage.setItem('chat_theme', 'light');
                } else {
                    // 切换到深色模式
                    htmlElement.setAttribute('data-theme', 'dark');
                    if (themeToggle) themeToggle.textContent = '☀️';
                    localStorage.setItem('chat_theme', 'dark');
                }
            }
            
            // 初始化主题
            initTheme();
            
            // 添加主题切换事件
            if (themeToggle) {
                themeToggle.addEventListener('click', toggleTheme);
            }
        });
    </script>
</body>
</html>