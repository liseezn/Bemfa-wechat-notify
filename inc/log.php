<?php
// 防止直接访问文件
if (!defined('ABSPATH')) {
    exit('Access denied');
}

/**
 * 巴法云插件专属日志记录函数
 * @param string $msg 日志内容
 * 日志位置：WP后台 → 工具 → 站点健康 → 日志 → PHP错误日志
 */
function bemfa_wechat_log($msg) {
    $settings = bemfa_wechat_get_settings();
    // 仅开启日志开关时记录，避免冗余日志
    if ($settings['enable_log']) {
        error_log("[Bemfa WeChat] " . $msg);
    }
}
