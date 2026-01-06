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
    <link rel="icon" type="image/x-icon" href="public/favicon.ico">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Microsoft YaHei", "Helvetica Neue", Arial, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            padding: 20px;
        }
        .login-box {
            background: #ffffff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
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
            color: #2c3e50;
            font-size: 24px;
            font-weight: 600;
        }
        .form-item {
            margin-bottom: 20px;
        }
        .form-item label {
            display: block;
            margin-bottom: 8px;
            color: #34495e;
            font-size: 14px;
            font-weight: 500;
        }
        .form-item input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e0e6ed;
            border-radius: 8px;
            outline: none;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f8fafc;
        }
        .form-item input:focus {
            border-color: #07C160;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(7, 193, 96, 0.1);
        }
        .login-btn {
            width: 100%;
            padding: 12px;
            background: #07C160;
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
            background: #06b058;
            transform: translateY(-2px);
        }
        .error-tip {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 20px;
            font-size: 14px;
            padding: 10px;
            border-radius: 8px;
            background: #fef5f5;
            border: 1px solid #fde2e2;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>后台管理登录</h2>
        <?php if (isset($loginError)) { echo '<div class="error-tip">'.$loginError.'</div>'; } ?>
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
    <link rel="icon" type="image/x-icon" href="public/favicon.ico">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Microsoft YaHei", "Helvetica Neue", Arial, sans-serif;
        }
        body {
            background: #f8fafc;
            padding: 15px;
            color: #2c3e50;
            font-size: 14px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        body.dark-mode {
            background: #121212;
            color: #e0e0e0;
        }
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            width: 100%;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
        }
        body.dark-mode .admin-container {
            background: #1e1e1e;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        .admin-header {
            background: linear-gradient(135deg, #07C160 0%, #06b058 100%);
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
            background: linear-gradient(90deg, #07C160, #95EC69);
        }
        .admin-header h2 {
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
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
        .theme-toggle {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: background-color 0.3s ease;
            color: white;
        }
        .theme-toggle:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .chatroom-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .chatroom-btn:hover {
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
            border-bottom: 1px solid #eef2f7;
            transition: border-color 0.3s ease;
        }
        body.dark-mode .tab-nav {
            border-bottom-color: #3d3d3d;
        }
        .tab-nav-item {
            padding: 10px 15px;
            background: #f8fafc;
            border: 1px solid #eef2f7;
            border-bottom: none;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            color: #34495e;
            transition: all 0.3s ease;
            flex-shrink: 0;
            text-decoration: none;
        }
        body.dark-mode .tab-nav-item {
            background: #2d2d2d;
            border-color: #3d3d3d;
            color: #e0e0e0;
        }
        .tab-nav-item:hover {
            background: #f0f9f0;
            color: #07C160;
        }
        body.dark-mode .tab-nav-item:hover {
            background: #1a365d;
        }
        .tab-nav-item.active {
            background: #ffffff;
            color: #07C160;
            border-color: #07C160;
            border-bottom-color: #ffffff;
            position: relative;
            z-index: 10;
        }
        body.dark-mode .tab-nav-item.active {
            background: #1e1e1e;
            border-bottom-color: #1e1e1e;
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
            border: 1px solid #eef2f7;
            border-radius: 12px;
            overflow: hidden;
            background: #fefefe;
            transition: all 0.3s ease;
            width: 100%;
        }
        body.dark-mode .tab-item {
            border-color: #3d3d3d;
            background: #2d2d2d;
        }
        .tab-item:hover {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        body.dark-mode .tab-item:hover {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
        .tab-header {
            background: #f8fafc;
            padding: 12px 15px;
            font-size: 15px;
            font-weight: 600;
            border-bottom: 1px solid #eef2f7;
            color: #2c3e50;
            display: flex;
            align-items: center;
            transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
        }
        body.dark-mode .tab-header {
            background: #3d3d3d;
            border-bottom-color: #4d4d4d;
            color: #e0e0e0;
        }
        .tab-header::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 16px;
            background: #07C160;
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
            color: #34495e;
            font-size: 13px;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        body.dark-mode .form-group label {
            color: #e0e0e0;
        }
        .form-group input {
            padding: 8px 12px;
            border: 1px solid #eef2f7;
            border-radius: 8px;
            outline: none;
            font-size: 13px;
            width: 100%;
            transition: all 0.3s ease;
            background: #f8fafc;
        }
        body.dark-mode .form-group input {
            border-color: #4d4d4d;
            background: #3d3d3d;
            color: #e0e0e0;
        }
        .form-group input:focus {
            border-color: #07C160;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(7, 193, 96, 0.1);
        }
        body.dark-mode .form-group input:focus {
            background: #4d4d4d;
        }
        .btn {
            padding: 8px 15px;
            background: #07C160;
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
            background: #06b058;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(7, 193, 96, 0.2);
        }
        .btn-danger {
            background: #e74c3c;
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
            background: #2ecc71;
        }
        .btn-success:hover {
            background: #27ae60;
            box-shadow: 0 4px 12px rgba(46, 204, 113, 0.2);
        }
        .btn-default {
            background: #95a5a6;
        }
        .btn-default:hover {
            background: #7f8c8d;
            box-shadow: 0 4px 12px rgba(149, 165, 166, 0.2);
        }
        .tip-success {
            color: #27ae60;
            margin-bottom: 15px;
            font-size: 13px;
            padding: 10px 12px;
            border-radius: 8px;
            background: #f0f9f0;
            border: 1px solid #d4f0d4;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        body.dark-mode .tip-success {
            background: #1b2e1f;
            border-color: #2e7d32;
            color: #4caf50;
        }
        .tip-success::before {
            content: '✓';
            display: inline-block;
            width: 18px;
            height: 18px;
            background: #27ae60;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 18px;
            font-size: 11px;
        }
        .tip-error {
            color: #e74c3c;
            margin-bottom: 15px;
            font-size: 13px;
            padding: 10px 12px;
            border-radius: 8px;
            background: #fef5f5;
            border: 1px solid #fde2e2;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        body.dark-mode .tip-error {
            background: #2e1b1b;
            border-color: #7d2e2e;
            color: #f44336;
        }
        .tip-error::before {
            content: '×';
            display: inline-block;
            width: 18px;
            height: 18px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 18px;
            font-size: 11px;
        }
        .table-search-wrapper {
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            padding: 18px 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        body.dark-mode .table-search-wrapper {
            background: #2d2d2d;
            border-color: #3d3d3d;
        }
        .table-search-wrapper:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            border-color: #d1e7dd;
        }
        body.dark-mode .table-search-wrapper:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            border-color: #1a365d;
        }
        .search-title {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.3s ease;
        }
        body.dark-mode .search-title {
            color: #e0e0e0;
        }
        .search-title::after {
            content: '';
            width: 6px;
            height: 6px;
            background: #07C160;
            border-radius: 50%;
        }
        .empty-tip {
            color: #7f8c8d;
            font-size: 13px;
            padding: 30px 20px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #eef2f7;
            text-align: center;
            margin: 10px 0;
            transition: all 0.3s ease;
        }
        body.dark-mode .empty-tip {
            background: #2d2d2d;
            border-color: #3d3d3d;
            color: #999;
        }
        .empty-tip:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        body.dark-mode .empty-tip:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin: 10px 0 20px;
            transition: all 0.3s ease;
        }
        body.dark-mode .table-wrapper {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        .table-wrapper:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        body.dark-mode .table-wrapper:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        .data-table {
            width: 100%;
            min-width: 600px;
            border-collapse: collapse;
            border-radius: 12px;
            overflow: hidden;
        }
        .data-table thead {
            background: linear-gradient(135deg, #f8fafc 0%, #f0f9f0 100%);
        }
        body.dark-mode .data-table thead {
            background: linear-gradient(135deg, #2d2d2d 0%, #1a365d 100%);
        }
        .data-table th {
            padding: 12px 15px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 1px solid #eef2f7;
            white-space: nowrap;
            transition: color 0.3s ease, border-bottom-color 0.3s ease;
        }
        body.dark-mode .data-table th {
            color: #e0e0e0;
            border-bottom-color: #3d3d3d;
        }
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            font-size: 12px;
            color: #34495e;
            border-bottom: 1px solid #f5f7fa;
            white-space: nowrap;
            transition: all 0.2s ease, color 0.3s ease, border-bottom-color 0.3s ease;
        }
        body.dark-mode .data-table td {
            color: #e0e0e0;
            border-bottom-color: #3d3d3d;
        }
        .data-table tbody tr {
            background: #ffffff;
            transition: background-color 0.3s ease;
        }
        body.dark-mode .data-table tbody tr {
            background: #2d2d2d;
        }
        .data-table tbody tr:hover {
            background: #f8fafc;
            transform: translateX(2px);
        }
        body.dark-mode .data-table tbody tr:hover {
            background: #3d3d3d;
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
            background: #d1e7dd;
            color: #27ae60;
        }
        body.dark-mode .status-normal {
            background: #1b2e1f;
            color: #4caf50;
        }
        .status-ban {
            background: #f8d7da;
            color: #e74c3c;
        }
        body.dark-mode .status-ban {
            background: #2e1b1b;
            color: #f44336;
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
            background: #d1e7dd;
            color: #27ae60;
        }
        body.dark-mode .msg-type-text {
            background: #1b2e1f;
            color: #4caf50;
        }
        .msg-type-image {
            background: #cce5ff;
            color: #3498db;
        }
        body.dark-mode .msg-type-image {
            background: #1b232e;
            color: #2196f3;
        }
        .msg-type-video {
            background: #fff3cd;
            color: #e67e22;
        }
        body.dark-mode .msg-type-video {
            background: #2e251b;
            color: #ff9800;
        }
        .avatar-color {
            width: 20px;
            height: 20px;
            border-radius: 6px;
            display: inline-block;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border: 1px solid #eef2f7;
        }
        body.dark-mode .avatar-color {
            border-color: #3d3d3d;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        .preview-link {
            color: #3498db;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        body.dark-mode .preview-link {
            color: #2196f3;
        }
        .preview-link:hover {
            color: #2980b9;
            text-decoration: underline;
        }
        body.dark-mode .preview-link:hover {
            color: #1976d2;
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
            border: 1px solid #eef2f7;
            border-radius: 8px;
            color: #34495e;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            white-space: nowrap;
            background: #ffffff;
        }
        body.dark-mode .pagination a {
            border-color: #3d3d3d;
            color: #e0e0e0;
            background: #2d2d2d;
        }
        .pagination a:hover {
            background: #f0f9f0;
            border-color: #07C160;
            color: #07C160;
            transform: translateY(-2px);
        }
        body.dark-mode .pagination a:hover {
            background: #1a365d;
        }
        .pagination a.active {
            background: linear-gradient(135deg, #07C160, #06b058);
            color: white;
            border-color: #07C160;
            box-shadow: 0 2px 8px rgba(7, 193, 96, 0.2);
        }
        .pagination a.disabled {
            color: #bdc3c7;
            border-color: #eef2f7;
            cursor: not-allowed;
            background: #f8fafc;
        }
        body.dark-mode .pagination a.disabled {
            color: #666;
            border-color: #3d3d3d;
            background: #2d2d2d;
        }
        .pagination a.disabled:hover {
            color: #bdc3c7;
            border-color: #eef2f7;
            background: #f8fafc;
            transform: none;
        }
        body.dark-mode .pagination a.disabled:hover {
            color: #666;
            border-color: #3d3d3d;
            background: #2d2d2d;
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
            background: #f8fafc;
            padding: 18px 20px;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            transition: all 0.3s ease;
        }
        body.dark-mode .clear-item {
            background: #2d2d2d;
            border-color: #3d3d3d;
        }
        .clear-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            border-color: #d1e7dd;
            background: #f0f9f0;
        }
        body.dark-mode .clear-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            border-color: #1a365d;
            background: #1a365d;
        }
        .clear-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .clear-title {
            font-size: 15px;
            font-weight: 600;
            color: #2c3e50;
            transition: color 0.3s ease;
        }
        body.dark-mode .clear-title {
            color: #e0e0e0;
        }
        .clear-desc {
            font-size: 12px;
            color: #7f8c8d;
            line-height: 1.4;
            transition: color 0.3s ease;
        }
        body.dark-mode .clear-desc {
            color: #999;
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
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            padding: 20px;
            transition: all 0.3s ease;
        }
        body.dark-mode .limit-wrapper {
            background: #2d2d2d;
            border-color: #3d3d3d;
        }
        .limit-wrapper:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            border-color: #d1e7dd;
            background: #f0f9f0;
        }
        body.dark-mode .limit-wrapper:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            border-color: #1a365d;
            background: #1a365d;
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
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.3s ease;
        }
        body.dark-mode .limit-label {
            color: #e0e0e0;
        }
        .limit-label::after {
            content: '';
            display: inline-block;
            width: 6px;
            height: 6px;
            background: #07C160;
            border-radius: 50%;
        }
        .limit-input {
            padding: 10px 15px;
            border: 1px solid #eef2f7;
            border-radius: 8px;
            outline: none;
            font-size: 14px;
            background: #ffffff;
            transition: all 0.3s ease;
            width: 100%;
            max-width: 300px;
        }
        body.dark-mode .limit-input {
            border-color: #4d4d4d;
            background: #3d3d3d;
            color: #e0e0e0;
        }
        .limit-input:focus {
            border-color: #07C160;
            box-shadow: 0 0 0 3px rgba(7, 193, 96, 0.1);
        }
        body.dark-mode .limit-input:focus {
            background: #4d4d4d;
        }
        .limit-desc {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: -5px;
            line-height: 1.4;
            transition: color 0.3s ease;
        }
        body.dark-mode .limit-desc {
            color: #999;
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
            background: #eef2f7;
            border-radius: 10px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        body.dark-mode .switch-checkbox {
            background: #3d3d3d;
        }
        .switch-checkbox:checked {
            background: #07C160;
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
            color: #34495e;
            font-weight: 500;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        body.dark-mode .switch-label {
            color: #e0e0e0;
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
            transition: background-color 0.3s ease;
        }
        body.dark-mode .media-preview-box {
            background: #2d2d2d;
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
            transition: color 0.3s ease;
        }
        body.dark-mode .preview-title {
            color: #e0e0e0;
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
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h2>匿名聊天室后台管理系统</h2>
            <div class="header-actions">
                <a href="index.php" class="chatroom-btn">返回聊天室</a>
                <button class="theme-toggle" id="themeToggle">🌙</button>
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
        // 深色模式切换
        function initDarkMode() {
            const themeToggle = document.getElementById('themeToggle');
            const body = document.body;
            
            // 检查本地存储中的主题设置
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                body.classList.add('dark-mode');
                themeToggle.textContent = '☀️';
            }
            
            // 主题切换事件
            themeToggle.addEventListener('click', () => {
                body.classList.toggle('dark-mode');
                const isDarkMode = body.classList.contains('dark-mode');
                themeToggle.textContent = isDarkMode ? '☀️' : '🌙';
                localStorage.setItem('theme', isDarkMode ? 'dark' : 'light');
            });
        }

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
        });

        // 初始化深色模式
        document.addEventListener('DOMContentLoaded', function() {
            initDarkMode();
        });
    </script>

</body>
</html>