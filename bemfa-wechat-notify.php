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

// ====================== 1. 后台配置页 + 新增高级配置（日志开关/请求方式） ======================
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

add_action('admin_init', 'bemfa_wechat_register_settings');
function bemfa_wechat_register_settings() {
    register_setting(
        'bemfa_wechat_settings_group',
        'bemfa_wechat_settings',
        [
            'sanitize_callback' => 'bemfa_wechat_sanitize_settings',
            'default' => [
                // 核心配置
                'uid' => '',
                'device' => 'WordPress站点',
                'group' => 'default',
                'use_http' => false,
                // 事件开关
                'event_post_publish' => false,
                'event_comment_approved' => false,
                'event_wc_new_order' => false,
                // 新增：高级配置
                'enable_log' => true,       // 日志开关，默认开启
                'request_method' => 'post'  // 请求方式，默认POST（post/get）
            ]
        ]
    );

    // 1. 核心配置节
    add_settings_section(
        'bemfa_wechat_main_section',
        __('核心配置（巴法云控制台获取）', 'bemfa-wechat-notify'),
        'bemfa_wechat_section_desc',
        'bemfa-wechat-notify'
    );

    // 2. 推送事件开关节
    add_settings_section(
        'bemfa_wechat_event_section',
        __('推送事件开关（按需启用）', 'bemfa-wechat-notify'),
        'bemfa_wechat_event_section_desc',
        'bemfa-wechat-notify'
    );

    // 新增：3. 高级配置节（日志开关/请求方式）
    add_settings_section(
        'bemfa_wechat_advanced_section',
        __('高级配置（日志/接口请求）', 'bemfa-wechat-notify'),
        'bemfa_wechat_advanced_section_desc',
        'bemfa-wechat-notify'
    );

    // 4. 测试推送节
    add_settings_section(
        'bemfa_wechat_test_section',
        __('测试推送（验证配置是否正常）', 'bemfa-wechat-notify'),
        'bemfa_wechat_test_section_desc',
        'bemfa-wechat-notify'
    );

    // 核心配置字段
    add_settings_field('bemfa_wechat_uid', __('巴法云用户私钥UID <span style="color:red;">*</span>', 'bemfa-wechat-notify'), 'bemfa_wechat_field_uid', 'bemfa-wechat-notify', 'bemfa_wechat_main_section');
    add_settings_field('bemfa_wechat_device', __('默认设备名称', 'bemfa-wechat-notify'), 'bemfa_wechat_field_device', 'bemfa-wechat-notify', 'bemfa_wechat_main_section');
    add_settings_field('bemfa_wechat_group', __('默认消息分组', 'bemfa-wechat-notify'), 'bemfa_wechat_field_group', 'bemfa-wechat-notify', 'bemfa_wechat_main_section');
    add_settings_field('bemfa_wechat_use_http', __('使用HTTP协议（本地/硬件设备）', 'bemfa-wechat-notify'), 'bemfa_wechat_field_use_http', 'bemfa-wechat-notify', 'bemfa_wechat_main_section');

    // 推送事件开关字段
    add_settings_field('bemfa_wechat_event_post_publish', __('新文章发布时推送', 'bemfa-wechat-notify'), 'bemfa_wechat_field_event_post_publish', 'bemfa-wechat-notify', 'bemfa_wechat_event_section');
    add_settings_field('bemfa_wechat_event_comment_approved', __('新评论审核通过时推送', 'bemfa-wechat-notify'), 'bemfa_wechat_field_event_comment_approved', 'bemfa-wechat-notify', 'bemfa_wechat_event_section');
    if (class_exists('WooCommerce')) {
        add_settings_field('bemfa_wechat_event_wc_new_order', __('Woocommerce新订单生成时推送', 'bemfa-wechat-notify'), 'bemfa_wechat_field_event_wc_new_order', 'bemfa-wechat-notify', 'bemfa_wechat_event_section');
    }

    // 新增：高级配置字段（日志开关/请求方式）
    add_settings_field('bemfa_wechat_enable_log', __('是否开启插件日志', 'bemfa-wechat-notify'), 'bemfa_wechat_field_enable_log', 'bemfa-wechat-notify', 'bemfa_wechat_advanced_section');
    add_settings_field('bemfa_wechat_request_method', __('接口请求方式', 'bemfa-wechat-notify'), 'bemfa_wechat_field_request_method', 'bemfa-wechat-notify', 'bemfa_wechat_advanced_section');
}

// 核心配置节描述
function bemfa_wechat_section_desc() {
    echo '<p class="description">' . __('请先在<a href="https://cloud.bemfa.com/" target="_blank">巴法云控制台</a>绑定微信（关注"巴法云"公众号），UID在个人中心获取。', 'bemfa-wechat-notify') . '</p>';
    echo '<p class="description">' . __('分组仅限字母/数字，不存在会自动创建；本地/硬件设备请勾选HTTP协议（对应80端口），线上站点用HTTPS（443端口）。', 'bemfa-wechat-notify') . '</p>';
    echo '<p class="description" style="color:#0073aa;">' . __('隐私提示：本插件仅将您填写的UID存储在站点本地数据库，不收集、传输任何站点其他数据，推送消息仅转发至巴法云官方接口。', 'bemfa-wechat-notify') . '</p>';
}

// 推送事件开关节描述
function bemfa_wechat_event_section_desc() {
    echo '<p class="description">' . __('勾选后，对应事件触发时将自动推送微信消息，消息内容为系统默认模板，无需额外配置。', 'bemfa-wechat-notify') . '</p>';
}

// 新增：高级配置节描述（贴合巴法云API文档）
function bemfa_wechat_advanced_section_desc() {
    echo '<p class="description">' . __('日志开启后，插件会记录推送成功/失败日志（WP后台可查看）；关闭后不记录任何日志，减少冗余。', 'bemfa-wechat-notify') . '</p>';
    echo '<p class="description">' . __('请求方式贴合巴法云官方API：<b>POST</b>（JSON传参，线上站点推荐）、<b>GET</b>（URL传参，硬件/本地设备推荐，warn接口自动追加type=2参数）。', 'bemfa-wechat-notify') . '</p>';
}

// 测试推送节描述
function bemfa_wechat_test_section_desc() {
    echo '<p class="description">' . __('点击下方按钮发送测试消息，验证巴法云UID配置、接口连通性是否正常，测试结果将实时显示在按钮下方。', 'bemfa-wechat-notify') . '</p>';
}

// 原有核心配置字段渲染
function bemfa_wechat_field_uid() {
    $settings = bemfa_wechat_get_settings();
    $uid = $settings['uid'] ?? '';
    echo '<input type="text" name="bemfa_wechat_settings[uid]" value="' . esc_attr($uid) . '" class="regular-text" placeholder="' . __('例：4d9ec352e0376f2110a0c601a2857225', 'bemfa-wechat-notify') . '" required>';
}
function bemfa_wechat_field_device() {
    $settings = bemfa_wechat_get_settings();
    $device = $settings['device'] ?? 'WordPress站点';
    echo '<input type="text" name="bemfa_wechat_settings[device]" value="' . esc_attr($device) . '" class="regular-text" placeholder="' . __('例：我的WP博客', 'bemfa-wechat-notify') . '">';
}
function bemfa_wechat_field_group() {
    $settings = bemfa_wechat_get_settings();
    $group = $settings['group'] ?? 'default';
    echo '<input type="text" name="bemfa_wechat_settings[group]" value="' . esc_attr($group) . '" class="regular-text" placeholder="' . __('例：wp_notify（仅限字母/数字）', 'bemfa-wechat-notify') . '">';
}
function bemfa_wechat_field_use_http() {
    $settings = bemfa_wechat_get_settings();
    $use_http = $settings['use_http'] ?? false;
    echo '<input type="checkbox" name="bemfa_wechat_settings[use_http]" value="1" ' . checked(1, $use_http, false) . '>';
    echo '<label class="description" style="margin-left:5px;">' . __('本地环境/硬件设备勾选（使用HTTP接口80端口），线上站点不勾选（HTTPS接口443端口）', 'bemfa-wechat-notify') . '</label>';
}

// 原有推送事件开关字段渲染
function bemfa_wechat_field_event_post_publish() {
    $settings = bemfa_wechat_get_settings();
    $enabled = $settings['event_post_publish'] ?? false;
    echo '<input type="checkbox" name="bemfa_wechat_settings[event_post_publish]" value="1" ' . checked(1, $enabled, false) . '>';
    echo '<label class="description" style="margin-left:5px;">' . __('发布新文章（普通文章）时，自动推送微信提醒', 'bemfa-wechat-notify') . '</label>';
}
function bemfa_wechat_field_event_comment_approved() {
    $settings = bemfa_wechat_get_settings();
    $enabled = $settings['event_comment_approved'] ?? false;
    echo '<input type="checkbox" name="bemfa_wechat_settings[event_comment_approved]" value="1" ' . checked(1, $enabled, false) . '>';
    echo '<label class="description" style="margin-left:5px;">' . __('评论审核通过后，自动推送微信提醒', 'bemfa-wechat-notify') . '</label>';
}
function bemfa_wechat_field_event_wc_new_order() {
    $settings = bemfa_wechat_get_settings();
    $enabled = $settings['event_wc_new_order'] ?? false;
    echo '<input type="checkbox" name="bemfa_wechat_settings[event_wc_new_order]" value="1" ' . checked(1, $enabled, false) . '>';
    echo '<label class="description" style="margin-left:5px;">' . __('新订单生成时，自动推送微信提醒（含订单号、金额、下单人）', 'bemfa-wechat-notify') . '</label>';
}

// 新增：高级配置字段渲染（日志开关/请求方式）
function bemfa_wechat_field_enable_log() {
    $settings = bemfa_wechat_get_settings();
    $enable_log = $settings['enable_log'] ?? true;
    echo '<label><input type="radio" name="bemfa_wechat_settings[enable_log]" value="1" ' . checked(1, $enable_log, false) . '> ' . __('开启', 'bemfa-wechat-notify') . '</label>';
    echo '<label style="margin-left:15px;"><input type="radio" name="bemfa_wechat_settings[enable_log]" value="0" ' . checked(0, $enable_log, false) . '> ' . __('关闭', 'bemfa-wechat-notify') . '</label>';
}
function bemfa_wechat_field_request_method() {
    $settings = bemfa_wechat_get_settings();
    $method = $settings['request_method'] ?? 'post';
    echo '<label><input type="radio" name="bemfa_wechat_settings[request_method]" value="post" ' . checked('post', $method, false) . '> ' . __('POST（JSON传参，线上推荐）', 'bemfa-wechat-notify') . '</label>';
    echo '<label style="margin-left:15px;"><input type="radio" name="bemfa_wechat_settings[request_method]" value="get" ' . checked('get', $method, false) . '> ' . __('GET（URL传参，硬件/本地推荐）', 'bemfa-wechat-notify') . '</label>';
}

// 配置数据验证/清洗 + 新增高级配置清洗
function bemfa_wechat_sanitize_settings($input) {
    $sanitized = [];
    // 核心配置清洗
    $sanitized['uid'] = isset($input['uid']) ? trim(sanitize_text_field($input['uid'])) : '';
    $sanitized['device'] = isset($input['device']) ? sanitize_text_field($input['device']) : 'WordPress站点';
    $sanitized['device'] = empty($sanitized['device']) ? 'WordPress站点' : $sanitized['device'];
    $sanitized['group'] = isset($input['group']) ? preg_replace('/[^a-zA-Z0-9]/', '', $input['group']) : 'default';
    $sanitized['group'] = empty($sanitized['group']) ? 'default' : $sanitized['group'];
    $sanitized['use_http'] = isset($input['use_http']) ? (bool)$input['use_http'] : false;

    // 事件开关清洗
    $sanitized['event_post_publish'] = isset($input['event_post_publish']) ? (bool)$input['event_post_publish'] : false;
    $sanitized['event_comment_approved'] = isset($input['event_comment_approved']) ? (bool)$input['event_comment_approved'] : false;
    $sanitized['event_wc_new_order'] = isset($input['event_wc_new_order']) ? (bool)$input['event_wc_new_order'] : false;

    // 新增：高级配置清洗
    $sanitized['enable_log'] = isset($input['enable_log']) ? (bool)$input['enable_log'] : true; // 日志默认开启
    $sanitized['request_method'] = isset($input['request_method']) && in_array($input['request_method'], ['post', 'get']) ? $input['request_method'] : 'post'; // 请求方式默认POST

    return $sanitized;
}

// 后台配置页HTML渲染（无修改，保留测试推送AJAX）
function bemfa_wechat_admin_page_render() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'bemfa-wechat-notify'));
    }
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

        <!-- 测试推送按钮 + 结果显示 -->
        <div id="bemfa-wechat-test-wrap" style="margin-top:20px;padding:15px;border:1px solid #ccd0d4;background:#f9f9f9;border-radius:4px;">
            <button type="button" id="bemfa-wechat-test-btn" class="button button-primary"><?php _e('发送测试消息', 'bemfa-wechat-notify'); ?></button>
            <div id="bemfa-wechat-test-result" style="margin-top:10px;height:24px;line-height:24px;"></div>
        </div>

        <!-- 使用帮助面板 -->
        <div class="postbox" style="margin-top:20px;">
            <h3 class="hndle"><span><?php _e('使用帮助', 'bemfa-wechat-notify'); ?></span></h3>
            <div class="inside">
                <h4><?php _e('1. 基础配置', 'bemfa-wechat-notify'); ?></h4>
                <p><?php _e('填写巴法云UID，按需配置设备名/分组，高级配置可设置日志和请求方式，保存后即可使用。', 'bemfa-wechat-notify'); ?></p>
                <h4><?php _e('2. 事件推送', 'bemfa-wechat-notify'); ?></h4>
                <p><?php _e('勾选「推送事件开关」中的场景，保存后对应事件触发时自动推送，无需额外操作。', 'bemfa-wechat-notify'); ?></p>
                <h4 style="margin-top:15px;"><?php _e('3. 短代码调用', 'bemfa-wechat-notify'); ?></h4>
                <pre style="background:#f5f5f5;padding:10px;border:1px solid #ddd;">
<!-- 基础提醒推送 -->
[bafayun_warn msg="新评论提醒：用户XXX评论了您的文章"]
<!-- 高级用法（自定义设备名/分组/跳转链接） -->
[bafayun_alert msg="站点异常：磁盘空间不足！" device="WP服务器" group="wp_alert" url="https://your-site.com/wp-admin/"]
                </pre>
                <h4 style="margin-top:15px;"><?php _e('4. 函数调用', 'bemfa-wechat-notify'); ?></h4>
                <pre style="background:#f5f5f5;padding:10px;border:1px solid #ddd;">
// 提醒推送（日常通知）
bafayun_wechat_warn("文章《XXX》已发布");
// 预警推送（重要告警）
bafayun_wechat_alert("数据库连接失败！");
// 快捷函数（默认提醒类型）
bafayun_wechat_push("新订单生成：NO.123456");
                </pre>
                <p class="description"><?php _e('更多使用场景请参考：<a href="https://github.com/liseezn/Bemfa-wechat-notify" target="_blank">GitHub文档</a> | 巴法云官方API：<a href="https://cloud.bemfa.com/" target="_blank">巴法云控制台</a>', 'bemfa-wechat-notify'); ?></p>
            </div>
        </div>
    </div>

    <!-- 测试推送AJAX脚本 -->
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#bemfa-wechat-test-btn').click(function() {
                var $btn = $(this), $result = $('#bemfa-wechat-test-result');
                $btn.prop('disabled', true).text('<?php _e('发送中...', 'bemfa-wechat-notify'); ?>');
                $result.html('').removeClass('notice-success notice-error');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bemfa_wechat_test_push',
                        nonce: '<?php echo wp_create_nonce('bemfa-wechat-test-nonce'); ?>'
                    },
                    success: function(res) {
                        if (res.success) {
                            $result.html('<span style="color:#46b450;">✅ ' + res.data.msg + '</span>');
                        } else {
                            $result.html('<span style="color:#dc3232;">❌ ' + res.data.msg + '</span>');
                        }
                    },
                    error: function() {
                        $result.html('<span style="color:#dc3232;">❌ <?php _e('请求失败，请刷新页面重试', 'bemfa-wechat-notify'); ?></span>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('<?php _e('发送测试消息', 'bemfa-wechat-notify'); ?>');
                    }
                });
            });
        });
    </script>
    <?php
}

/**
 * 获取配置项（带默认值，含新增高级配置）
 */
function bemfa_wechat_get_settings() {
    return get_option('bemfa_wechat_settings', [
        'uid' => '',
        'device' => 'WordPress站点',
        'group' => 'default',
        'use_http' => false,
        'event_post_publish' => false,
        'event_comment_approved' => false,
        'event_wc_new_order' => false,
        'enable_log' => true,
        'request_method' => 'post'
    ]);
}

// ====================== 2. 新增：日志工具函数（开关控制） ======================
/**
 * 插件日志记录函数（根据日志开关判断是否记录）
 * @param string $msg 日志内容
 */
function bemfa_wechat_log($msg) {
    $settings = bemfa_wechat_get_settings();
    if ($settings['enable_log']) { // 仅日志开启时记录
        error_log("[Bemfa WeChat] {$msg}");
    }
}

// ====================== 3. 测试推送AJAX处理函数（无修改） ======================
add_action('wp_ajax_bemfa_wechat_test_push', 'bemfa_wechat_test_push_ajax');
function bemfa_wechat_test_push_ajax() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'bemfa-wechat-test-nonce')) {
        wp_send_json_error(['msg' => __('权限不足，操作失败', 'bemfa-wechat-notify')]);
    }
    $test_msg = __('【巴法云微信通知测试】配置正常，测试消息推送成功！', 'bemfa-wechat-notify');
    $result = bafayun_wechat_warn($test_msg);
    if ($result['success']) {
        wp_send_json_success(['msg' => $result['msg']]);
    } else {
        wp_send_json_error(['msg' => $result['msg']]);
    }
    wp_die();
}

// ====================== 4. 核心工具函数（重构，支持POST/GET，贴合巴法云API文档） ======================
/**
 * 巴法云API请求基础方法（支持POST/GET，严格贴合官方API规范）
 * @param string $api_type 接口类型：alert/warn
 * @param array $params 请求参数
 * @return array 响应结果：['success' => bool, 'msg' => string, 'data' => array]
 */
function bemfa_wechat_api_request($api_type, $params) {
    $settings = bemfa_wechat_get_settings();
    $protocol = $settings['use_http'] ? 'http' : 'https';
    $request_method = strtolower($settings['request_method']); // post/get
    $is_alert = $api_type === 'alert';

    // 1. 接口地址配置（严格贴合巴法云API：POST带Json后缀，GET无后缀）
    if ($request_method === 'post') {
        $api_suffix = $is_alert ? 'wechatAlertJson' : 'wechatWarnJson';
    } else {
        $api_suffix = $is_alert ? 'wechatAlert' : 'wechatWarn';
    }
    $api_url = "{$protocol}://apis.bemfa.com/vb/wechat/v1/{$api_suffix}";

    // 2. 构造基础请求参数（必填+可选）
    $base_params = [
        'uid' => $settings['uid'],
        'device' => $params['device'] ?? $settings['device'],
        'message' => $params['message'] ?? '',
        'group' => $params['group'] ?? $settings['group']
    ];
    // 可选参数：跳转链接（过滤合法URL）
    if (!empty($params['url']) && filter_var($params['url'], FILTER_VALIDATE_URL)) {
        $base_params['url'] = $params['url'];
    }
    // GET方式专属：warn接口必须追加type=2（严格贴合巴法云API文档）
    if ($request_method === 'get' && !$is_alert) {
        $base_params['type'] = 2;
    }

    // 3. 校验必填参数
    if (empty($base_params['uid'])) {
        $error_msg = __('未配置巴法云UID，请先在后台设置', 'bemfa-wechat-notify');
        bemfa_wechat_log($error_msg); // 替换为日志函数
        return ['success' => false, 'msg' => $error_msg, 'data' => []];
    }
    if (empty($base_params['message'])) {
        $error_msg = __('推送消息不能为空', 'bemfa-wechat-notify');
        bemfa_wechat_log($error_msg);
        return ['success' => false, 'msg' => $error_msg, 'data' => []];
    }

    // 4. 按请求方式构造WP请求参数（POST/GET区分处理）
    $wp_request_args = [
        'timeout' => 15,
        'sslverify' => !$settings['use_http'], // HTTP模式关闭SSL验证
        'redirection' => 5
    ];
    if ($request_method === 'post') {
        // POST方式：JSON传参，设置Content-Type（贴合官方API）
        $wp_request_args['method'] = 'POST';
        $wp_request_args['headers'] = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json'
        ];
        $wp_request_args['body'] = json_encode($base_params, JSON_UNESCAPED_UNICODE);
    } else {
        // GET方式：URL拼接参数（贴合官方API，WP会自动编码）
        $wp_request_args['method'] = 'GET';
        $api_url = add_query_arg($base_params, $api_url); // 拼接参数到URL
    }

    // 5. 发起WP原生请求
    $response = wp_remote_request($api_url, $wp_request_args);

    // 6. 处理请求错误
    if (is_wp_error($response)) {
        $error_msg = $response->get_error_message();
        bemfa_wechat_log("API请求失败：{$error_msg}，参数：" . json_encode($base_params));
        return ['success' => false, 'msg' => __('接口请求失败：' . $error_msg, 'bemfa-wechat-notify'), 'data' => []];
    }

    // 7. 解析响应
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true) ?? [];

    // 8. 处理HTTP状态码
    if ($response_code != 200) {
        $error_msg = __('HTTP请求错误，状态码：' . $response_code, 'bemfa-wechat-notify');
        bemfa_wechat_log("{$error_msg}，响应：{$response_body}");
        return ['success' => false, 'msg' => $error_msg, 'data' => $response_data];
    }

    // 9. 处理巴法云业务响应
    if (isset($response_data['code']) && $response_data['code'] == 0) {
        bemfa_wechat_log("推送成功，参数：" . json_encode($base_params) . "，请求方式：{$request_method}");
        return ['success' => true, 'msg' => __('推送成功', 'bemfa-wechat-notify'), 'data' => $response_data];
    } else {
        $error_msg = $response_data['msg'] ?? __('未知错误', 'bemfa-wechat-notify');
        bemfa_wechat_log("推送失败：{$error_msg}，参数：" . json_encode($base_params) . "，请求方式：{$request_method}，响应：{$response_body}");
        return ['success' => false, 'msg' => __('推送失败：' . $error_msg, 'bemfa-wechat-notify'), 'data' => $response_data];
    }
}

// ====================== 5. 核心推送函数（无修改，兼容新请求方式） ======================
function bafayun_wechat_alert($message, $device = '', $group = '', $url = '') {
    return bemfa_wechat_api_request('alert', ['message' => $message, 'device' => $device, 'group' => $group, 'url' => $url]);
}
function bafayun_wechat_warn($message, $device = '', $group = '', $url = '') {
    return bemfa_wechat_api_request('warn', ['message' => $message, 'device' => $device, 'group' => $group, 'url' => $url]);
}
function bafayun_wechat_push($message, $type = 'warn') {
    return $type === 'alert' ? bafayun_wechat_alert($message) : bafayun_wechat_warn($message);
}

// ====================== 6. 短代码支持（无修改） ======================
add_shortcode('bafayun_warn', 'bemfa_wechat_warn_shortcode');
function bemfa_wechat_warn_shortcode($atts) {
    $atts = shortcode_atts(['msg' => '', 'device' => '', 'group' => '', 'url' => ''], $atts, 'bafayun_warn');
    $result = bafayun_wechat_warn($atts['msg'], $atts['device'], $atts['group'], $atts['url']);
    return bemfa_wechat_shortcode_result_render($result, 'warn');
}
add_shortcode('bafayun_alert', 'bemfa_wechat_alert_shortcode');
function bemfa_wechat_alert_shortcode($atts) {
    $atts = shortcode_atts(['msg' => '', 'device' => '', 'group' => '', 'url' => ''], $atts, 'bafayun_alert');
    $result = bafayun_wechat_alert($atts['msg'], $atts['device'], $atts['group'], $atts['url']);
    return bemfa_wechat_shortcode_result_render($result, 'alert');
}
function bemfa_wechat_shortcode_result_render($result, $type) {
    if (!current_user_can('manage_options')) return '';
    $type_text = $type === 'alert' ? __('预警', 'bemfa-wechat-notify') : __('提醒', 'bemfa-wechat-notify');
    $class = $result['success'] ? 'notice-success' : 'notice-error';
    return sprintf('<div class="notice %s inline" style="padding:10px;margin:10px 0;"><p><strong>%s</strong>：%s</p></div>', esc_attr($class), sprintf(__('巴法云%s推送', 'bemfa-wechat-notify'), $type_text), esc_html($result['msg']));
}

// ====================== 7. 自动事件推送核心逻辑（无修改） ======================
add_action('publish_post', 'bemfa_wechat_auto_push_post_publish', 10, 2);
function bemfa_wechat_auto_push_post_publish($post_ID, $post) {
    $settings = bemfa_wechat_get_settings();
    if (!$settings['event_post_publish'] || $post->post_type != 'post' || wp_is_post_autosave($post_ID)) return;
    $msg = sprintf(__('【新文章发布】%s%s访问链接：%s', 'bemfa-wechat-notify'), "\n", $post->post_title, "\n", get_permalink($post_ID));
    bafayun_wechat_warn($msg);
}

add_action('comment_post', 'bemfa_wechat_auto_push_comment_approved', 10, 2);
function bemfa_wechat_auto_push_comment_approved($comment_ID, $comment_approved) {
    $settings = bemfa_wechat_get_settings();
    if (!$settings['event_comment_approved'] || $comment_approved != 1) return;
    $comment = get_comment($comment_ID);
    $post = get_post($comment->comment_post_ID);
    if (!$comment || !$post) return;
    $comment_content = mb_substr($comment->comment_content, 0, 50, 'utf-8') . (mb_strlen($comment->comment_content) > 50 ? '...' : '');
    $msg = sprintf(
        __('【新评论提醒】%s评论人：%s%s评论内容：%s%s所属文章：%s', 'bemfa-wechat-notify'),
        "\n", $comment->comment_author, "\n", $comment_content, "\n", $post->post_title
    );
    bafayun_wechat_warn($msg, '', '', get_permalink($post->ID) . '#comment-' . $comment_ID);
}

if (class_exists('WooCommerce')) {
    add_action('woocommerce_new_order', 'bemfa_wechat_auto_push_wc_new_order');
    function bemfa_wechat_auto_push_wc_new_order($order_id) {
        $settings = bemfa_wechat_get_settings();
        if (!$settings['event_wc_new_order']) return;
        $order = wc_get_order($order_id);
        if (!$order) return;
        $msg = sprintf(
            __('【新订单提醒】%s订单号：%s%s下单人：%s%s订单金额：%s%s下单时间：%s', 'bemfa-wechat-notify'),
            "\n", $order->get_order_number(), "\n", $order->get_billing_full_name(), "\n", $order->get_formatted_order_total(), "\n", $order->get_date_created()->format('Y-m-d H:i:s')
        );
        bafayun_wechat_warn($msg, 'WP电商站点', 'wp_order', admin_url('post.php?post=' . $order_id . '&action=edit'));
    }
}

// ====================== 8. 插件激活/卸载（含新增配置默认值） ======================
register_activation_hook(__FILE__, 'bemfa_wechat_activate');
function bemfa_wechat_activate() {
    $default_settings = [
        'uid' => '',
        'device' => 'WordPress站点',
        'group' => 'default',
        'use_http' => false,
        'event_post_publish' => false,
        'event_comment_approved' => false,
        'event_wc_new_order' => false,
        'enable_log' => true,
        'request_method' => 'post'
    ];
    add_option('bemfa_wechat_settings', $default_settings);
}
register_uninstall_hook(__FILE__, 'bemfa_wechat_uninstall');
function bemfa_wechat_uninstall() {
    if (!current_user_can('activate_plugins')) return;
    delete_option('bemfa_wechat_settings');
}

// ====================== 9. 国际化支持（无修改） ======================
add_action('plugins_loaded', 'bemfa_wechat_load_textdomain');
function bemfa_wechat_load_textdomain() {
    load_plugin_textdomain('bemfa-wechat-notify', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
