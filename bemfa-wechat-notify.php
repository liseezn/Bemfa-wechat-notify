<?php
/**
 * Plugin Name: 微信通知推送
 * Plugin URI: https://github.com/liseezn/Bemfa-wechat-notify
 * Description: 基于巴法云微信接口的WordPress推送插件，支持设备预警、提醒，后台可视化配置，短代码/函数双调用，适配各类场景。
 * Version: 1.0
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
define('BEMFA_WECHAT_VERSION', '1.0');
define('BEMFA_WECHAT_PLUGIN_FILE', __FILE__);
define('BEMFA_WECHAT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BEMFA_WECHAT_PLUGIN_URL', plugin_dir_url(__FILE__));

// ====================== 1. 后台配置页 ======================
/**
 * 添加后台菜单
 */
add_action('admin_menu', 'bemfa_wechat_add_admin_menu');
function bemfa_wechat_add_admin_menu() {
    add_options_page(
        __('巴法云微信通知配置', 'bemfa-wechat-notify'),
        __('巴法云微信通知', 'bemfa-wechat-notify'),
        'manage_options',
        'bemfa-wechat-notify',
        'bemfa_wechat_admin_page_render'
    );
}

/**
 * 注册配置项（存储到wp_options）
 */
add_action('admin_init', 'bemfa_wechat_register_settings');
function bemfa_wechat_register_settings() {
    // 注册配置组
    register_setting(
        'bemfa_wechat_settings_group',
        'bemfa_wechat_settings',
        [
            'sanitize_callback' => 'bemfa_wechat_sanitize_settings',
            'default' => [
                'uid' => '',
                'device' => 'WordPress站点',
                'group' => 'default',
                'use_http' => false
            ]
        ]
    );

    // 添加配置节
    add_settings_section(
        'bemfa_wechat_main_section',
        __('核心配置（巴法云控制台获取）', 'bemfa-wechat-notify'),
        'bemfa_wechat_section_desc',
        'bemfa-wechat-notify'
    );

    // 配置字段：UID（必填）
    add_settings_field(
        'bemfa_wechat_uid',
        __('巴法云用户私钥UID <span style="color:red;">*</span>', 'bemfa-wechat-notify'),
        'bemfa_wechat_field_uid',
        'bemfa-wechat-notify',
        'bemfa_wechat_main_section'
    );

    // 配置字段：默认设备名
    add_settings_field(
        'bemfa_wechat_device',
        __('默认设备名称', 'bemfa-wechat-notify'),
        'bemfa_wechat_field_device',
        'bemfa-wechat-notify',
        'bemfa_wechat_main_section'
    );

    // 配置字段：默认分组
    add_settings_field(
        'bemfa_wechat_group',
        __('默认消息分组', 'bemfa-wechat-notify'),
        'bemfa_wechat_field_group',
        'bemfa-wechat-notify',
        'bemfa_wechat_main_section'
    );

    // 配置字段：HTTP/HTTPS切换
    add_settings_field(
        'bemfa_wechat_use_http',
        __('使用HTTP协议（本地/硬件设备）', 'bemfa-wechat-notify'),
        'bemfa_wechat_field_use_http',
        'bemfa-wechat-notify',
        'bemfa_wechat_main_section'
    );
}

/**
 * 配置节描述
 */
function bemfa_wechat_section_desc() {
    echo '<p class="description">' . __('请先在<a href="https://cloud.bemfa.com/" target="_blank">巴法云控制台</a>绑定微信（关注"巴法云"公众号），UID在个人中心获取。', 'bemfa-wechat-notify') . '</p>';
    echo '<p class="description">' . __('分组仅限字母/数字，不存在会自动创建；本地/硬件设备请勾选HTTP协议。', 'bemfa-wechat-notify') . '</p>';
}

/**
 * 字段渲染：UID
 */
function bemfa_wechat_field_uid() {
    $settings = bemfa_wechat_get_settings();
    $uid = $settings['uid'] ?? '';
    echo '<input type="text" name="bemfa_wechat_settings[uid]" value="' . esc_attr($uid) . '" class="regular-text" placeholder="' . __('例：4d9ec352e0376f2110a0c601a2857225', 'bemfa-wechat-notify') . '" required>';
}

/**
 * 字段渲染：默认设备名
 */
function bemfa_wechat_field_device() {
    $settings = bemfa_wechat_get_settings();
    $device = $settings['device'] ?? 'WordPress站点';
    echo '<input type="text" name="bemfa_wechat_settings[device]" value="' . esc_attr($device) . '" class="regular-text" placeholder="' . __('例：我的WP博客', 'bemfa-wechat-notify') . '">';
}

/**
 * 字段渲染：默认分组
 */
function bemfa_wechat_field_group() {
    $settings = bemfa_wechat_get_settings();
    $group = $settings['group'] ?? 'default';
    echo '<input type="text" name="bemfa_wechat_settings[group]" value="' . esc_attr($group) . '" class="regular-text" placeholder="' . __('例：wp_notify（仅限字母/数字）', 'bemfa-wechat-notify') . '">';
}

/**
 * 字段渲染：HTTP/HTTPS切换
 */
function bemfa_wechat_field_use_http() {
    $settings = bemfa_wechat_get_settings();
    $use_http = $settings['use_http'] ?? false;
    echo '<input type="checkbox" name="bemfa_wechat_settings[use_http]" value="1" ' . checked(1, $use_http, false) . '>';
    echo '<label class="description" style="margin-left:5px;">' . __('本地环境/硬件设备勾选（使用HTTP接口），线上站点不勾选（HTTPS）', 'bemfa-wechat-notify') . '</label>';
}

/**
 * 配置数据验证/清洗
 */
function bemfa_wechat_sanitize_settings($input) {
    $sanitized = [];

    // UID：保留32位字符串，去除首尾空格
    $sanitized['uid'] = isset($input['uid']) ? trim(sanitize_text_field($input['uid'])) : '';

    // 设备名：清洗特殊字符
    $sanitized['device'] = isset($input['device']) ? sanitize_text_field($input['device']) : 'WordPress站点';
    if (empty($sanitized['device'])) {
        $sanitized['device'] = 'WordPress站点';
    }

    // 分组：过滤非字母/数字，默认default
    $sanitized['group'] = isset($input['group']) ? preg_replace('/[^a-zA-Z0-9]/', '', $input['group']) : 'default';
    if (empty($sanitized['group'])) {
        $sanitized['group'] = 'default';
    }

    // HTTP开关：布尔值
    $sanitized['use_http'] = isset($input['use_http']) ? (bool)$input['use_http'] : false;

    return $sanitized;
}

/**
 * 后台配置页HTML渲染
 */
function bemfa_wechat_admin_page_render() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'bemfa-wechat-notify'));
    }

    // 保存成功提示
    if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
        add_settings_error('bemfa_wechat_notices', 'bemfa_wechat_success', __('配置保存成功！', 'bemfa-wechat-notify'), 'updated');
    }
    settings_errors('bemfa_wechat_notices');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('bemfa_wechat_settings_group');
            do_settings_sections('bemfa-wechat-notify');
            submit_button(__('保存配置', 'bemfa-wechat-notify'), 'primary', 'bemfa_wechat_save_settings');
            ?>
        </form>

        <!-- 使用帮助面板 -->
        <div class="postbox" style="margin-top:20px;">
            <h3 class="hndle"><span><?php _e('使用帮助', 'bemfa-wechat-notify'); ?></span></h3>
            <div class="inside">
                <h4><?php _e('1. 短代码调用', 'bemfa-wechat-notify'); ?></h4>
                <pre style="background:#f5f5f5;padding:10px;border:1px solid #ddd;">
<!-- 基础提醒推送 -->
[bafayun_warn msg="新评论提醒：用户XXX评论了您的文章"]

<!-- 高级用法（自定义设备名/分组/跳转链接） -->
[bafayun_alert 
  msg="站点异常：磁盘空间不足！" 
  device="WP服务器" 
  group="wp_alert" 
  url="https://your-site.com/wp-admin/"
]
                </pre>

                <h4 style="margin-top:15px;"><?php _e('2. 函数调用', 'bemfa-wechat-notify'); ?></h4>
                <pre style="background:#f5f5f5;padding:10px;border:1px solid #ddd;">
// 提醒推送（日常通知）
bafayun_wechat_warn("文章《XXX》已发布");

// 预警推送（重要告警）
bafayun_wechat_alert("数据库连接失败！");

// 快捷函数（默认提醒类型）
bafayun_wechat_push("新订单生成：NO.123456");
                </pre>

                <p class="description"><?php _e('更多使用场景请参考：<a href="https://github.com/liseezn/Bemfa-wechat-notify#%E4%BD%BF%E7%94%A8%E6%95%99%E7%A8%8B" target="_blank">GitHub文档</a>', 'bemfa-wechat-notify'); ?></p>
            </div>
        </div>
    </div>
    <?php
}

/**
 * 获取配置项（带默认值）
 */
function bemfa_wechat_get_settings() {
    return get_option('bemfa_wechat_settings', [
        'uid' => '',
        'device' => 'WordPress站点',
        'group' => 'default',
        'use_http' => false
    ]);
}

// ====================== 2. 核心工具函数 ======================
/**
 * 巴法云API请求基础方法
 * @param string $api_type 接口类型：alert/warn
 * @param array $params 请求参数
 * @return array 响应结果：['success' => bool, 'msg' => string, 'data' => array]
 */
function bemfa_wechat_api_request($api_type, $params) {
    $settings = bemfa_wechat_get_settings();
    
    // 1. 接口地址配置
    $protocol = $settings['use_http'] ? 'http' : 'https';
    $api_suffix = $api_type === 'alert' ? 'wechatAlertJson' : 'wechatWarnJson';
    $api_url = "{$protocol}://apis.bemfa.com/vb/wechat/v1/{$api_suffix}";

    // 2. 构造请求参数
    $request_params = [
        'uid' => $settings['uid'],
        'device' => $params['device'] ?? $settings['device'],
        'message' => $params['message'] ?? '',
        'group' => $params['group'] ?? $settings['group']
    ];
    // 可选参数：跳转链接
    if (!empty($params['url']) && filter_var($params['url'], FILTER_VALIDATE_URL)) {
        $request_params['url'] = $params['url'];
    }

    // 3. 校验必填参数
    if (empty($request_params['uid'])) {
        $error_msg = __('未配置巴法云UID，请先在后台设置', 'bemfa-wechat-notify');
        error_log("[Bemfa WeChat] {$error_msg}");
        return ['success' => false, 'msg' => $error_msg, 'data' => []];
    }
    if (empty($request_params['message'])) {
        $error_msg = __('推送消息不能为空', 'bemfa-wechat-notify');
        error_log("[Bemfa WeChat] {$error_msg}");
        return ['success' => false, 'msg' => $error_msg, 'data' => []];
    }

    // 4. 发起WP原生请求（兼容性更强）
    $response = wp_remote_post($api_url, [
        'method' => 'POST',
        'timeout' => 15,
        'headers' => [
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json'
        ],
        'body' => json_encode($request_params, JSON_UNESCAPED_UNICODE),
        'sslverify' => !$settings['use_http'], // HTTP模式关闭SSL验证
        'redirection' => 5
    ]);

    // 5. 处理请求错误
    if (is_wp_error($response)) {
        $error_msg = $response->get_error_message();
        error_log("[Bemfa WeChat] API请求失败：{$error_msg}，参数：" . json_encode($request_params));
        return ['success' => false, 'msg' => __('接口请求失败：' . $error_msg, 'bemfa-wechat-notify'), 'data' => []];
    }

    // 6. 解析响应
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true) ?? [];

    // 7. 处理HTTP状态码
    if ($response_code != 200) {
        $error_msg = __('HTTP请求错误，状态码：' . $response_code, 'bemfa-wechat-notify');
        error_log("[Bemfa WeChat] {$error_msg}，响应：{$response_body}");
        return ['success' => false, 'msg' => $error_msg, 'data' => $response_data];
    }

    // 8. 处理巴法云业务响应
    if (isset($response_data['code']) && $response_data['code'] == 0) {
        error_log("[Bemfa WeChat] 推送成功，参数：" . json_encode($request_params));
        return ['success' => true, 'msg' => __('推送成功', 'bemfa-wechat-notify'), 'data' => $response_data];
    } else {
        $error_msg = $response_data['msg'] ?? __('未知错误', 'bemfa-wechat-notify');
        error_log("[Bemfa WeChat] 推送失败：{$error_msg}，参数：" . json_encode($request_params) . "，响应：{$response_body}");
        return ['success' => false, 'msg' => __('推送失败：' . $error_msg, 'bemfa-wechat-notify'), 'data' => $response_data];
    }
}

// ====================== 3. 核心推送函数 ======================
/**
 * 设备预警推送（wechatAlertJson接口）
 * @param string $message 推送消息
 * @param string $device 设备名（可选）
 * @param string $group 分组（可选）
 * @param string $url 跳转链接（可选）
 * @return array 推送结果
 */
function bafayun_wechat_alert($message, $device = '', $group = '', $url = '') {
    return bemfa_wechat_api_request('alert', [
        'message' => $message,
        'device' => $device,
        'group' => $group,
        'url' => $url
    ]);
}

/**
 * 设备提醒推送（wechatWarnJson接口）
 * @param string $message 推送消息
 * @param string $device 设备名（可选）
 * @param string $group 分组（可选）
 * @param string $url 跳转链接（可选）
 * @return array 推送结果
 */
function bafayun_wechat_warn($message, $device = '', $group = '', $url = '') {
    return bemfa_wechat_api_request('warn', [
        'message' => $message,
        'device' => $device,
        'group' => $group,
        'url' => $url
    ]);
}

/**
 * 全局快捷推送函数（默认提醒类型）
 * @param string $message 推送消息
 * @param string $type 类型：alert/warn（默认warn）
 * @return array 推送结果
 */
function bafayun_wechat_push($message, $type = 'warn') {
    return $type === 'alert' ? bafayun_wechat_alert($message) : bafayun_wechat_warn($message);
}

// ====================== 4. 短代码支持 ======================
/**
 * 提醒推送短代码 [bafayun_warn]
 */
add_shortcode('bafayun_warn', 'bemfa_wechat_warn_shortcode');
function bemfa_wechat_warn_shortcode($atts) {
    $atts = shortcode_atts([
        'msg' => '',
        'device' => '',
        'group' => '',
        'url' => ''
    ], $atts, 'bafayun_warn');

    $result = bafayun_wechat_warn($atts['msg'], $atts['device'], $atts['group'], $atts['url']);
    return bemfa_wechat_shortcode_result_render($result, 'warn');
}

/**
 * 预警推送短代码 [bafayun_alert]
 */
add_shortcode('bafayun_alert', 'bemfa_wechat_alert_shortcode');
function bemfa_wechat_alert_shortcode($atts) {
    $atts = shortcode_atts([
        'msg' => '',
        'device' => '',
        'group' => '',
        'url' => ''
    ], $atts, 'bafayun_alert');

    $result = bafayun_wechat_alert($atts['msg'], $atts['device'], $atts['group'], $atts['url']);
    return bemfa_wechat_shortcode_result_render($result, 'alert');
}

/**
 * 短代码结果渲染（仅管理员可见）
 */
function bemfa_wechat_shortcode_result_render($result, $type) {
    if (!current_user_can('manage_options')) {
        return ''; // 普通游客隐藏结果
    }

    $type_text = $type === 'alert' ? __('预警', 'bemfa-wechat-notify') : __('提醒', 'bemfa-wechat-notify');
    $class = $result['success'] ? 'notice-success' : 'notice-error';
    return sprintf(
        '<div class="notice %s inline" style="padding:10px;margin:10px 0;">
            <p><strong>%s</strong>：%s</p>
        </div>',
        esc_attr($class),
        sprintf(__('巴法云%s推送', 'bemfa-wechat-notify'), $type_text),
        esc_html($result['msg'])
    );
}

// ====================== 5. 插件激活/卸载 ======================
/**
 * 插件激活：初始化配置
 */
register_activation_hook(__FILE__, 'bemfa_wechat_activate');
function bemfa_wechat_activate() {
    $default_settings = [
        'uid' => '',
        'device' => 'WordPress站点',
        'group' => 'default',
        'use_http' => false
    ];
    add_option('bemfa_wechat_settings', $default_settings);
}

/**
 * 插件卸载：清理配置
 */
register_uninstall_hook(__FILE__, 'bemfa_wechat_uninstall');
function bemfa_wechat_uninstall() {
    if (!current_user_can('activate_plugins')) {
        return;
    }
    delete_option('bemfa_wechat_settings');
}

// ====================== 6. 国际化支持 ======================
add_action('plugins_loaded', 'bemfa_wechat_load_textdomain');
function bemfa_wechat_load_textdomain() {
    load_plugin_textdomain('bemfa-wechat-notify', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
