<?php
/**
 * Plugin Name: 巴法云微信通知推送
 * Plugin URI: https://github.com/liseezn/Bemfa-wechat-notify
 * Description: 基于巴法云微信接口的WordPress推送插件，支持设备预警/提醒，后台可视化配置，推送事件开关，一键测试推送，日志开关，POST/GET双请求方式，短代码/函数双调用，完全贴合官方API规范。
 * Version: 1.2
 * Author: liseezn
 * Author URI: https://github.com/liseezn
 * License: GPLv2 or later
 * Text Domain: bemfa-wechat-notify
 * Domain Path: /languages
 */

// 防止直接访问文件
if (!defined('ABSPATH')) {
    exit('Access denied');
}

// ====================== 插件核心常量定义 ======================
define('BEMFA_WECHAT_VERSION', '1.2');
define('BEMFA_WECHAT_PLUGIN_FILE', __FILE__);
define('BEMFA_WECHAT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BEMFA_WECHAT_PLUGIN_URL', plugin_dir_url(__FILE__));

// ====================== 按依赖顺序引入模块文件（避免函数未定义） ======================
$inc_files = [
    'log.php',               // 日志（基础依赖，其他模块可调用）
    'config.php',            // 配置（基础依赖，所有模块需获取配置）
    'api-request.php',       // API请求（依赖：日志/配置）
    'admin-page.php',        // 后台页面（依赖：配置/API请求）
    'shortcode.php',         // 短代码（依赖：API请求）
    'auto-push.php',         // 自动推送（依赖：配置/API请求）
    'activate-uninstall.php',// 生命周期（依赖：配置）
    'i18n.php'               // 国际化（无依赖，最后引入）
];

foreach ($inc_files as $file) {
    $file_path = BEMFA_WECHAT_PLUGIN_DIR . 'inc/' . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    }
}

// ====================== 统一注册核心钩子（便于管理，所有钩子在此集中注册） ======================
// 后台菜单 + 配置项初始化（显式优先级10，WP默认）
add_action('admin_menu', 'bemfa_wechat_add_admin_menu', 10);
add_action('admin_init', 'bemfa_wechat_register_settings', 10);
// AJAX测试推送（仅管理员可调用，显式优先级10）
add_action('wp_ajax_bemfa_wechat_test_push', 'bemfa_wechat_test_push_ajax', 10);
// 国际化语言包加载
add_action('plugins_loaded', 'bemfa_wechat_load_textdomain', 10);
// 插件激活/卸载钩子（函数在activate-uninstall.php中定义）
register_activation_hook(__FILE__, 'bemfa_wechat_activate');
register_uninstall_hook(__FILE__, 'bemfa_wechat_uninstall');
