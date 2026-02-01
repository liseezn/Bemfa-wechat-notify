<?php
// 防止直接访问文件
if (!defined('ABSPATH')) {
    exit('Access denied');
}

/**
 * 插件激活钩子函数
 * 初始化默认配置，避免首次使用无配置报错
 */
function bemfa_wechat_activate() {
    // 仅在无配置时添加默认配置
    if (!get_option('bemfa_wechat_settings')) {
        $default_settings = [
            'uid'           => '',
            'device'        => 'WordPress站点',
            'group'         => 'default',
            'use_http'      => false,
            'event_post'    => false,
            'event_comment' => false,
            'event_woo'     => false,
            'enable_log'    => true,
            'request_method'=> 'post'
        ];
        add_option('bemfa_wechat_settings', $default_settings);
    }
    // 激活日志
    bemfa_wechat_log("插件激活成功，版本：" . BEMFA_WECHAT_VERSION);
}

/**
 * 插件卸载钩子函数
 * 彻底清理所有配置，无数据库残留
 * 注意：仅通过WP插件中心卸载才会触发，直接删除文件不会触发
 */
function bemfa_wechat_uninstall() {
    // 检查权限（仅管理员可卸载）
    if (!current_user_can('manage_options')) {
        wp_die('您没有权限卸载此插件');
    }
    // 清理配置项
    delete_option('bemfa_wechat_settings');
    // 卸载日志
    error_log("[Bemfa WeChat] 插件卸载成功，已清理所有配置");
}
