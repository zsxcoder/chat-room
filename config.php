<?php
// 确保只有从 admin.php 引入时才执行（防止直接访问）
if (!defined('CHAT_SYSTEM')) {
    exit('禁止直接访问该配置文件！');
}

/**
 * 管理员登录验证函数（核心：解决账号密码错误问题）
 * 你可以自行修改 $adminUser 和 $adminPass 的值
 */
function checkAdminLogin($username, $password) {
    // 自定义管理员账号密码（可根据需求修改）
    $adminUser = 'zsx'; // 默认账号
    $adminPass = 'qweasdzxc123'; // 默认密码（建议后续修改为复杂密码）
    
    // 严格校验（已去除前后空格，和 admin.php 中的 trim 对应）
    return $username === $adminUser && $password === $adminPass;
}

/**
 * 获取聊天室配置（消息频率、黑名单等）
 */
function getChatConfig() {
    $configFile = 'chat_system_config.json';
    // 若配置文件不存在，先初始化
    if (!file_exists($configFile)) {
        $initConfig = [
            'message_limit' => ['per_minute' => 10, 'enable' => true],
            'user_blacklist' => []
        ];
        // 添加 @ 抑制文件写入错误，增加写入结果判断
        $writeResult = @file_put_contents($configFile, json_encode($initConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        if ($writeResult === false) {
            // 写入失败时返回默认配置，避免系统中断
            return $initConfig;
        }
        return $initConfig;
    }
    // 读取并解析配置文件，@ 抑制文件读取错误
    $configContent = @file_get_contents($configFile);
    if ($configContent === false) {
        // 读取失败时返回默认配置
        return [
            'message_limit' => ['per_minute' => 10, 'enable' => true],
            'user_blacklist' => []
        ];
    }
    // 解析失败时返回默认配置
    $config = json_decode($configContent, true);
    if (!is_array($config)) {
        return [
            'message_limit' => ['per_minute' => 10, 'enable' => true],
            'user_blacklist' => []
        ];
    }
    return $config;
}

/**
 * 更新聊天室配置（消息频率限制等功能依赖）
 * @param array $newConfig 新的配置项（如 message_limit）
 */
function updateChatConfig($newConfig) {
    $configFile = 'chat_system_config.json';
    // 验证传入的新配置是否为数组
    if (!is_array($newConfig)) {
        return false; // 非法参数直接返回失败
    }
    // 获取原有配置
    $oldConfig = getChatConfig();
    // 合并新配置（保留原有未修改的配置，仅更新传入的配置项）
    $finalConfig = array_merge($oldConfig, $newConfig);
    // 写入文件并加锁，@ 抑制写入错误
    $writeResult = @file_put_contents($configFile, json_encode($finalConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    // 返回写入结果（true 成功，false 失败）
    return $writeResult !== false;
}
?>