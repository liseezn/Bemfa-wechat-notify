<?php
/**
 * Plugin Name: 微信通知推送
 * Plugin URI: https://github.com/liseezn/Bemfa-wechat-notify
 * Description: 基于巴法云微信接口的WordPress推送插件，支持设备预警/提醒，后台可视化配置，推送事件开关，一键测试推送，日志开关，POST/GET双请求方式，短代码/函数双调用，完全贴合官方API规范。
 * Version: 1.4
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
define('BEMFA_WECHAT_VERSION', '1.4');
define('BEMFA_WECHAT_PLUGIN_FILE', __FILE__);
define('BEMFA_WECHAT_LOG_TABLE', 'bemfa_wechat_logs'); // 日志表名

// ====================== 【核心新增：内置日志系统】- 数据库日志/筛选/清理 ======================
function bemfa_wechat_create_log_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . BEMFA_WECHAT_LOG_TABLE;
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        api_type varchar(20) NOT NULL,
        request_method varchar(10) NOT NULL,
        device varchar(100) DEFAULT '',
        message text NOT NULL,
        status varchar(20) NOT NULL,
        response_code int(11) DEFAULT 0,
        response_msg text,
        ip_address varchar(45) DEFAULT '',
        user_agent text,
        PRIMARY KEY (id),
        KEY time (time),
        KEY api_type (api_type),
        KEY status (status)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function bemfa_wechat_log($msg, $data = []) {
    $settings = bemfa_wechat_get_settings();
    
    // 记录到数据库
    if ($settings['enable_log']) {
        global $wpdb;
        $table_name = $wpdb->prefix . BEMFA_WECHAT_LOG_TABLE;
        
        $log_data = wp_parse_args($data, [
            'api_type' => $data['api_type'] ?? 'unknown',
            'request_method' => $data['request_method'] ?? '',
            'device' => $data['device'] ?? '',
            'message' => $msg,
            'status' => $data['status'] ?? 'info',
            'response_code' => $data['response_code'] ?? 0,
            'response_msg' => $data['response_msg'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
        
        $wpdb->insert($table_name, [
            'time' => current_time('mysql'),
            'api_type' => $log_data['api_type'],
            'request_method' => $log_data['request_method'],
            'device' => $log_data['device'],
            'message' => $log_data['message'],
            'status' => $log_data['status'],
            'response_code' => $log_data['response_code'],
            'response_msg' => is_array($log_data['response_msg']) ? json_encode($log_data['response_msg'], JSON_UNESCAPED_UNICODE) : $log_data['response_msg'],
            'ip_address' => $log_data['ip_address'],
            'user_agent' => $log_data['user_agent'],
        ]);
    }
    
    // 同时保留error_log记录（可选）
    if ($settings['enable_error_log']) {
        error_log("[Bemfa WeChat] " . $msg);
    }
}

function bemfa_wechat_clean_old_logs() {
    $settings = bemfa_wechat_get_settings();
    $retention_days = absint($settings['log_retention_days'] ?? 30);
    
    if ($retention_days > 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . BEMFA_WECHAT_LOG_TABLE;
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE time < %s",
            $cutoff_date
        ));
        
        bemfa_wechat_log("清理{$retention_days}天前的日志记录", ['status' => 'cleanup']);
    }
}

// ====================== 【日志系统：后台日志查看页面】 ======================
function bemfa_wechat_add_log_menu() {
    add_submenu_page(
        'options-general.php',
        '推送日志',
        '推送日志',
        'manage_options',
        'bemfa-wechat-logs',
        'bemfa_wechat_render_log_page'
    );
}

function bemfa_wechat_render_log_page() {
    if (!current_user_can('manage_options')) {
        wp_die('无权限访问');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . BEMFA_WECHAT_LOG_TABLE;
    $per_page = 50;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // 筛选条件
    $where = [];
    $where_clause = '';
    if (!empty($_GET['status'])) $where[] = $wpdb->prepare("status = %s", sanitize_text_field($_GET['status']));
    if (!empty($_GET['api_type'])) $where[] = $wpdb->prepare("api_type = %s", sanitize_text_field($_GET['api_type']));
    if (!empty($_GET['device'])) $where[] = $wpdb->prepare("device = %s", sanitize_text_field($_GET['device']));
    if (!empty($_GET['date_from'])) $where[] = $wpdb->prepare("time >= %s", sanitize_text_field($_GET['date_from']) . ' 00:00:00');
    if (!empty($_GET['date_to'])) $where[] = $wpdb->prepare("time <= %s", sanitize_text_field($_GET['date_to']) . ' 23:59:59');
    if (!empty($where)) $where_clause = ' WHERE ' . implode(' AND ', $where);
    
    // 获取总数和日志数据
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where_clause");
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name $where_clause ORDER BY time DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ));
    
    // 统计和设备列表
    $stats = $wpdb->get_results("SELECT status, COUNT(*) as count FROM $table_name WHERE time >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY status");
    $devices = $wpdb->get_results("SELECT device, COUNT(*) as count FROM $table_name GROUP BY device ORDER BY count DESC LIMIT 10");
    $total_success = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'success'");
    $rate = $total > 0 ? round(($total_success / $total) * 100, 2) : 0;
    ?>
    <div class="wrap" style="max-width:1400px;margin:0 auto;">
        <h1>推送日志 <span style="font-size:14px;color:#666;">(共 <?php echo $total; ?> 条记录)</span></h1>
        <!-- 筛选表单 -->
        <form method="get" action="<?php echo admin_url('options-general.php'); ?>">
            <input type="hidden" name="page" value="bemfa-wechat-logs">
            <div class="tablenav top">
                <div class="alignleft actions">
                    <label>状态：</label>
                    <select name="status"><option value="">全部</option>
                        <option value="success" <?php selected($_GET['status'] ?? '', 'success'); ?>>成功</option>
                        <option value="error" <?php selected($_GET['status'] ?? '', 'error'); ?>>失败</option>
                        <option value="warning" <?php selected($_GET['status'] ?? '', 'warning'); ?>>警告</option>
                    </select>
                    <label style="margin-left:10px;">接口类型：</label>
                    <select name="api_type"><option value="">全部</option>
                        <option value="warn" <?php selected($_GET['api_type'] ?? '', 'warn'); ?>>设备提醒</option>
                        <option value="alert" <?php selected($_GET['api_type'] ?? '', 'alert'); ?>>设备预警</option>
                    </select>
                    <label style="margin-left:10px;">设备：</label>
                    <select name="device"><option value="">全部</option>
                        <?php foreach ($devices as $d): ?>
                            <option value="<?php echo esc_attr($d->device); ?>" <?php selected($_GET['device'] ?? '', $d->device); ?>>
                                <?php echo esc_html($d->device) . " ({$d->count})"; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label style="margin-left:10px;">日期：</label>
                    <input type="date" name="date_from" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>">
                    <span style="margin:0 5px;">至</span>
                    <input type="date" name="date_to" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>">
                    <button type="submit" class="button">筛选</button>
                    <a href="<?php echo admin_url('options-general.php?page=bemfa-wechat-logs'); ?>" class="button">重置</a>
                </div>
            </div>
        </form>
        <!-- 统计信息 -->
        <div style="margin:20px 0;padding:15px;background:#f5f5f5;border-radius:5px;">
            <h3>最近7天统计</h3>
            <?php foreach ($stats as $stat): ?>
                <span style="margin-right:20px;"><strong><?php echo esc_html($stat->status); ?>:</strong> <?php echo $stat->count; ?> 次</span>
            <?php endforeach; ?>
            <span style="margin-right:20px;"><strong>总成功率:</strong> <?php echo $rate; ?>%</span>
            <a href="#" id="clear-logs" class="button button-secondary" style="float:right;">清理日志</a>
        </div>
        <!-- 日志表格 -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="5%">ID</th><th width="15%">时间</th><th width="10%">接口</th><th width="10%">设备</th>
                    <th width="30%">消息</th><th width="10%">状态</th><th width="10%">响应码</th><th width="10%">IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo $log->id; ?></td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($log->time)); ?></td>
                            <td><?php echo esc_html($log->api_type); ?></td>
                            <td><?php echo esc_html($log->device); ?></td>
                            <td><div style="max-height:60px;overflow:auto;"><?php echo esc_html($log->message); ?>
                                <?php if (!empty($log->response_msg)): ?>
                                    <br><small style="color:#666;">响应: <?php echo esc_html($log->response_msg); ?></small>
                                <?php endif; ?></div></td>
                            <td><span class="dashicons dashicons-<?php echo $log->status === 'success' ? 'yes' : ($log->status === 'error' ? 'no' : 'warning'); ?>"></span>
                                <?php echo esc_html($log->status); ?></td>
                            <td><?php echo $log->response_code; ?></td>
                            <td><?php echo esc_html($log->ip_address); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center;">暂无日志记录</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <!-- 分页 -->
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'total' => ceil($total / $per_page),
                    'current' => $current_page,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;'
                ]); ?>
            </div>
        </div>
        <!-- 清理日志JS -->
        <script>
            jQuery(document).ready(function($){
                $('#clear-logs').click(function(e){
                    e.preventDefault();
                    if(confirm('确定要清理所有日志吗？此操作不可恢复！')){
                        $.ajax({
                            url: ajaxurl,type: 'POST',
                            data: {action:'bemfa_wechat_clear_logs',nonce:'<?php echo wp_create_nonce('bemfa_clear_logs'); ?>'},
                            success: function(){location.reload();}
                        });
                    }
                });
            });
        </script>
    </div>
    <?php
}

// AJAX清理日志
function bemfa_wechat_clear_logs_ajax() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'bemfa_clear_logs')) wp_die('权限验证失败');
    global $wpdb;
    $table_name = $wpdb->prefix . BEMFA_WECHAT_LOG_TABLE;
    $wpdb->query("TRUNCATE TABLE $table_name");
    wp_send_json_success(['msg' => '日志已清空']);
}

// ====================== 【模块2：配置模块】- 融合事件增强+日志配置 ======================
function bemfa_wechat_get_settings() {
    return get_option('bemfa_wechat_settings', [
        // 核心基础配置
        'uid'               => '',
        'device'            => 'WordPress站点',
        'group'             => 'default',
        'use_http'          => false,
        // 原生推送事件开关
        'event_post'        => false,
        'event_comment'     => false,
        'event_woo'         => false,
        // 6个事件独立开关（默认关闭）
        'event_comment_new'  => false, // 新评论即时提交
        'event_user'         => false, // 新用户注册
        'event_page'         => false, // 新页面发布
        'event_cpt'          => false, // 自定义文章发布
        'event_woo_paid'     => false, // Woo订单付款
        'event_woo_complete' => false, // Woo订单完成
        // 日志相关配置
        'enable_log'        => false,
        'enable_error_log'  => false,
        'log_retention_days'=> 30,
        'auto_clean_logs'   => false,
        // 基础请求配置
        'request_method'    => 'post',
    ]);
}

function bemfa_wechat_register_settings() {
    register_setting(
        'bemfa_wechat_group',
        'bemfa_wechat_settings',
        ['sanitize_callback' => 'bemfa_wechat_sanitize_settings']
    );

    // 注册配置节 - 适配标签页：页面名改为 前缀+配置节名
    add_settings_section('bemfa_sec_main', '核心配置（巴法云控制台获取）<span style="color:red;">*</span>', 'bemfa_wechat_sec_main_desc', 'bemfa-wechat-page-bemfa_sec_main');
    add_settings_section('bemfa_sec_event', '推送事件开关', 'bemfa_wechat_sec_event_desc', 'bemfa-wechat-page-bemfa_sec_event');
    add_settings_section('bemfa_sec_log', '日志系统配置', 'bemfa_wechat_sec_log_desc', 'bemfa-wechat-page-bemfa_sec_log');
    add_settings_section('bemfa_sec_advanced', '高级配置（协议/请求）', 'bemfa_wechat_sec_advanced_desc', 'bemfa-wechat-page-bemfa_sec_advanced');
    add_settings_section('bemfa_sec_test', '一键测试推送', 'bemfa_wechat_sec_test_desc', 'bemfa-wechat-page-bemfa_sec_test');

    // 核心配置字段 - 适配标签页
    add_settings_field('bemfa_field_uid', '巴法云32位私钥', 'bemfa_wechat_field_uid', 'bemfa-wechat-page-bemfa_sec_main', 'bemfa_sec_main');
    add_settings_field('bemfa_field_device', '默认设备名', 'bemfa_field_device', 'bemfa-wechat-page-bemfa_sec_main', 'bemfa_sec_main');
    add_settings_field('bemfa_field_group', '消息分组', 'bemfa_field_group', 'bemfa-wechat-page-bemfa_sec_main', 'bemfa_sec_main');

    // 【融合】所有推送事件字段（原生+6新增）- 适配标签页
    add_settings_field('bemfa_field_event_post', '新文章发布推送', 'bemfa_wechat_field_event_post', 'bemfa-wechat-page-bemfa_sec_event', 'bemfa_sec_event');
    add_settings_field('bemfa_field_event_comment', '评论审核通过推送', 'bemfa_wechat_field_event_comment', 'bemfa-wechat-page-bemfa_sec_event', 'bemfa_sec_event');
    add_settings_field('bemfa_field_event_comment_new', '新评论提交即时推送', 'bemfa_wechat_field_event_comment_new', 'bemfa-wechat-page-bemfa_sec_event', 'bemfa_sec_event');
    add_settings_field('bemfa_field_event_user', '新用户注册推送', 'bemfa_wechat_field_event_user', 'bemfa-wechat-page-bemfa_sec_event', 'bemfa_sec_event');
    add_settings_field('bemfa_field_event_page', '新页面发布推送', 'bemfa_wechat_field_event_page', 'bemfa-wechat-page-bemfa_sec_event', 'bemfa_sec_event');
    add_settings_field('bemfa_field_event_cpt', '自定义文章发布推送', 'bemfa_wechat_field_event_cpt', 'bemfa-wechat-page-bemfa_sec_event', 'bemfa_sec_event');
    if (class_exists('WooCommerce')) {
        add_settings_field('bemfa_field_event_woo', 'Woo新订单推送', 'bemfa_wechat_field_event_woo', 'bemfa-wechat-page-bemfa_sec_event', 'bemfa_sec_event');
        add_settings_field('bemfa_field_event_woo_paid', 'Woo订单付款推送', 'bemfa_wechat_field_event_woo_paid', 'bemfa-wechat-page-bemfa_sec_event', 'bemfa_sec_event');
        add_settings_field('bemfa_field_event_woo_complete', 'Woo订单完成推送', 'bemfa_wechat_field_event_woo_complete', 'bemfa-wechat-page-bemfa_sec_event', 'bemfa_sec_event');
    }

    // 日志系统配置字段 - 适配标签页
    add_settings_field('bemfa_field_enable_log', '启用内置数据库日志', 'bemfa_wechat_field_enable_log', 'bemfa-wechat-page-bemfa_sec_log', 'bemfa_sec_log');
    add_settings_field('bemfa_field_enable_error_log', '同时记录服务器error_log', 'bemfa_wechat_field_enable_error_log', 'bemfa-wechat-page-bemfa_sec_log', 'bemfa_sec_log');
    add_settings_field('bemfa_field_log_retention', '日志保留天数', 'bemfa_wechat_field_log_retention', 'bemfa-wechat-page-bemfa_sec_log', 'bemfa_sec_log');
    add_settings_field('bemfa_field_auto_clean', '自动清理旧日志', 'bemfa_wechat_field_auto_clean', 'bemfa-wechat-page-bemfa_sec_log', 'bemfa_sec_log');

    // 高级配置字段 - 适配标签页
    add_settings_field('bemfa_field_http', '使用HTTP协议', 'bemfa_wechat_field_http', 'bemfa-wechat-page-bemfa_sec_advanced', 'bemfa_sec_advanced');
    add_settings_field('bemfa_field_method', '请求方式', 'bemfa_wechat_field_method', 'bemfa-wechat-page-bemfa_sec_advanced', 'bemfa_sec_advanced');
}

function bemfa_wechat_sanitize_settings($input) {
    $output = [];
    $settings = bemfa_wechat_get_settings();

    // 核心配置清洗
    $output['uid'] = !empty($input['uid']) ? trim($input['uid']) : $settings['uid'];
    $output['device'] = !empty($input['device']) ? trim($input['device']) : $settings['device'];
    $output['group'] = !empty($input['group']) ? preg_replace('/[^a-zA-Z0-9]/', '', $input['group']) : 'default';
    $output['use_http'] = isset($input['use_http']) && $input['use_http'] === 'on';
    // 所有推送事件开关清洗
    $output['event_post'] = isset($input['event_post']) && $input['event_post'] === 'on';
    $output['event_comment'] = isset($input['event_comment']) && $input['event_comment'] === 'on';
    $output['event_comment_new'] = isset($input['event_comment_new']) && $input['event_comment_new'] === 'on';
    $output['event_user'] = isset($input['event_user']) && $input['event_user'] === 'on';
    $output['event_page'] = isset($input['event_page']) && $input['event_page'] === 'on';
    $output['event_cpt'] = isset($input['event_cpt']) && $input['event_cpt'] === 'on';
    $output['event_woo'] = isset($input['event_woo']) && $input['event_woo'] === 'on';
    $output['event_woo_paid'] = isset($input['event_woo_paid']) && $input['event_woo_paid'] === 'on';
    $output['event_woo_complete'] = isset($input['event_woo_complete']) && $input['event_woo_complete'] === 'on';
    // 日志配置清洗
    $output['enable_log'] = isset($input['enable_log']) && $input['enable_log'] === 'on';
    $output['enable_error_log'] = isset($input['enable_error_log']) && $input['enable_error_log'] === 'on';
    $output['log_retention_days'] = !empty($input['log_retention_days']) ? intval($input['log_retention_days']) : 30;
    $output['log_retention_days'] = max(1, min(365, $output['log_retention_days']));
    $output['auto_clean_logs'] = isset($input['auto_clean_logs']) && $input['auto_clean_logs'] === 'on';
    // 请求配置清洗
    $output['request_method'] = in_array($input['request_method'], ['post', 'get']) ? $input['request_method'] : 'post';

    return $output;
}

// 配置节描述
function bemfa_wechat_sec_main_desc() {
    echo '<p class="description">1. 巴法云控制台（获取32位私钥）<a href="https://cloud.bemfa.com/tcp/devicemqtt.html" target="_blank">点此进入</a>，一定要绑定微信!!!；</p>';
    echo '<p class="description">2. 分组仅支持字母/数字，不存在会自动创建，默认default；</p>';
    echo '<p class="description">3. 私钥为必填项，错误会导致所有推送失败。</p>';
}
function bemfa_wechat_sec_event_desc() { echo '<p class="description">开启后对应事件触发时自动推送微信通知，每个事件独立开关，按需开启！</p>'; }
function bemfa_wechat_sec_log_desc() {
    echo '<p class="description">1. 内置日志将推送记录保存到数据库，可在【设置→推送日志】查看详细记录；</p>';
    echo '<p class="description">2. 建议开启自动清理，避免日志表过大影响服务器性能；</p>';
    echo '<p class="description">3. error_log会记录到服务器错误日志，仅用于高级调试。</p>';
}
function bemfa_wechat_sec_advanced_desc() {
    echo '<p class="description">1. 本地/硬件设备勾选HTTP，线上HTTPS站点请勿勾选；</p>';
    echo '<p class="description">2. 线上推荐POST方式，本地/硬件推荐GET方式；</p>';
}
function bemfa_wechat_sec_test_desc() {}

// 配置字段渲染 - 核心配置
function bemfa_wechat_field_uid() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="text" name="bemfa_wechat_settings[uid]" value="' . esc_attr($settings['uid']) . '" class="regular-text" placeholder="32位纯字符串" required>';
}
function bemfa_field_device() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="text" name="bemfa_wechat_settings[device]" value="' . esc_attr($settings['device']) . '" class="regular-text" placeholder="如：我的WP站点">';
}
function bemfa_field_group() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="text" name="bemfa_wechat_settings[group]" value="' . esc_attr($settings['group']) . '" class="regular-text" placeholder="仅字母/数字，默认default">';
}

// 配置字段渲染 - 推送事件
function bemfa_wechat_field_event_post() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="checkbox" name="bemfa_wechat_settings[event_post]" ' . checked($settings['event_post'], true, false) . '>';
}
function bemfa_wechat_field_event_comment() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="checkbox" name="bemfa_wechat_settings[event_comment]" ' . checked($settings['event_comment'], true, false) . '>';
}
function bemfa_wechat_field_event_comment_new() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="checkbox" name="bemfa_wechat_settings[event_comment_new]" ' . checked($settings['event_comment_new'], true, false) . '>';
    echo '<p class="description" style="margin-left:20px;">用户/访客提交即推，无需审核，含评论状态</p>';
}
function bemfa_wechat_field_event_user() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="checkbox" name="bemfa_wechat_settings[event_user]" ' . checked($settings['event_user'], true, false) . '>';
}
function bemfa_wechat_field_event_page() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="checkbox" name="bemfa_wechat_settings[event_page]" ' . checked($settings['event_page'], true, false) . '>';
}
function bemfa_wechat_field_event_cpt() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="checkbox" name="bemfa_wechat_settings[event_cpt]" ' . checked($settings['event_cpt'], true, false) . '>';
    echo '<p class="description" style="margin-left:20px;">适配产品、资讯等所有自定义文章类型，排除原生文章/页面</p>';
}
function bemfa_wechat_field_event_woo() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="checkbox" name="bemfa_wechat_settings[event_woo]" ' . checked($settings['event_woo'], true, false) . '>';
}
function bemfa_wechat_field_event_woo_paid() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="checkbox" name="bemfa_wechat_settings[event_woo_paid]" ' . checked($settings['event_woo_paid'], true, false) . '>';
}
function bemfa_wechat_field_event_woo_complete() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="checkbox" name="bemfa_wechat_settings[event_woo_complete]" ' . checked($settings['event_woo_complete'], true, false) . '>';
}

// 配置字段渲染 - 日志配置
function bemfa_wechat_field_enable_log() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="checkbox" name="bemfa_wechat_settings[enable_log]" ' . checked($settings['enable_log'], true, false) . '>';
}
function bemfa_wechat_field_enable_error_log() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="checkbox" name="bemfa_wechat_settings[enable_error_log]" ' . checked($settings['enable_error_log'], true, false) . '>';
}
function bemfa_wechat_field_log_retention() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="number" name="bemfa_wechat_settings[log_retention_days]" value="' . esc_attr($settings['log_retention_days']) . '" min="1" max="365" step="1" style="width:80px;"> 天（1-365天）';
}
function bemfa_wechat_field_auto_clean() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="checkbox" name="bemfa_wechat_settings[auto_clean_logs]" ' . checked($settings['auto_clean_logs'], true, false) . '> 开启后每天自动清理过期日志';
}

// 配置字段渲染 - 高级配置
function bemfa_wechat_field_http() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="checkbox" name="bemfa_wechat_settings[use_http]" ' . checked($settings['use_http'], true, false) . '>';
}
function bemfa_wechat_field_method() {
    $settings = bemfa_wechat_get_settings();
    echo '<select name="bemfa_wechat_settings[request_method]" class="regular-text">';
    echo '<option value="post" ' . selected($settings['request_method'], 'post', false) . '>POST（线上推荐）</option>';
    echo '<option value="get" ' . selected($settings['request_method'], 'get', false) . '>GET（本地推荐）</option>';
    echo '</select>';
}

// ====================== 【模块3：API核心模块】- 融合日志记录+双接口+双请求 ======================
function bemfa_wechat_api_request($api_type, $params = []) {
    $settings = bemfa_wechat_get_settings();
    $protocol = $settings['use_http'] ? 'http' : 'https';
    $method = strtolower($settings['request_method']);
    // 核心修复：严格遵循官方文档 - POST后缀带Json，GET后缀不带
    $base_suffix = $api_type === 'alert' ? 'wechatAlert' : 'wechatWarn';
    $api_suffix = $method === 'post' ? $base_suffix . 'Json' : $base_suffix;
    $api_url = "{$protocol}://apis.bemfa.com/vb/wechat/v1/{$api_suffix}";

    // 构造参数
    $request_params = [
        'uid'       => $settings['uid'],
        'device'    => !empty($params['device']) ? $params['device'] : $settings['device'],
        'message'   => !empty($params['message']) ? $params['message'] : '',
        'group'     => !empty($params['group']) ? $params['group'] : $settings['group'],
    ];
    if (!empty($params['url']) && filter_var($params['url'], FILTER_VALIDATE_URL)) $request_params['url'] = $params['url'];
    if ($method === 'get' && $api_type === 'warn') $request_params['type'] = 2; // GET-warn官方必选

    // 必传参数校验
    if (empty($request_params['uid']) || strlen($request_params['uid']) !== 32) {
        $msg = '私钥错误：必须是32位纯字符串';
        bemfa_wechat_log($msg, ['api_type'=>$api_type,'request_method'=>strtoupper($method),'device'=>$request_params['device'],'status'=>'error']);
        return ['success' => false, 'msg' => $msg, 'data' => []];
    }
    if (empty($request_params['device'])) {
        $msg = '设备名不能为空（官方必传）';
        bemfa_wechat_log($msg, ['api_type'=>$api_type,'request_method'=>strtoupper($method),'status'=>'error']);
        return ['success' => false, 'msg' => $msg, 'data' => []];
    }
    if (empty($request_params['message'])) {
        $msg = '推送消息不能为空（官方必传）';
        bemfa_wechat_log($msg, ['api_type'=>$api_type,'request_method'=>strtoupper($method),'device'=>$request_params['device'],'status'=>'error']);
        return ['success' => false, 'msg' => $msg, 'data' => []];
    }

    // WP请求配置
    $wp_args = ['timeout'=>20,'sslverify'=>false,'redirection'=>3,'headers'=>[]];
    if ($method === 'post') {
        $wp_args['method'] = 'POST';
        $wp_args['headers']['Content-Type'] = 'application/json; charset=utf-8';
        $wp_args['body'] = json_encode($request_params, JSON_UNESCAPED_UNICODE);
        bemfa_wechat_log("POST准备 | 接口：{$api_url} | 参数：" . json_encode($request_params, JSON_UNESCAPED_UNICODE), [
            'api_type'=>$api_type,'request_method'=>'POST','device'=>$request_params['device'],'status'=>'sending'
        ]);
    } else {
        $wp_args['method'] = 'GET';
        $api_url = add_query_arg($request_params, $api_url);
        bemfa_wechat_log("GET准备 | 接口：{$api_url}", [
            'api_type'=>$api_type,'request_method'=>'GET','device'=>$request_params['device'],'status'=>'sending'
        ]);
    }

    // 发起请求
    $response = wp_remote_request($api_url, $wp_args);
    if (is_wp_error($response)) {
        $err = $response->get_error_message();
        $msg = "网络失败：{$err}";
        bemfa_wechat_log($msg, [
            'api_type'=>$api_type,'request_method'=>strtoupper($method),'device'=>$request_params['device'],
            'status'=>'error','response_msg'=>$err
        ]);
        return ['success' => false, 'msg' => $msg, 'data' => []];
    }

    // 解析响应
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true) ?? [];

    if ($code !== 200) {
        $msg = "HTTP错误：{$code}";
        bemfa_wechat_log($msg, [
            'api_type'=>$api_type,'request_method'=>strtoupper($method),'device'=>$request_params['device'],
            'status'=>'error','response_code'=>$code,'response_msg'=>$body
        ]);
        return ['success' => false, 'msg' => $msg, 'data' => $data];
    }

    if (isset($data['code']) && $data['code'] === 0) {
        $msg = "推送成功（{$api_type}-{$method}）";
        bemfa_wechat_log($msg, [
            'api_type'=>$api_type,'request_method'=>strtoupper($method),'device'=>$request_params['device'],
            'status'=>'success','response_code'=>$code,'response_msg'=>$data
        ]);
        return ['success' => true, 'msg' => $msg, 'data' => $data];
    } else {
        $err = $data['msg'] ?? '未知错误';
        $msg = "推送失败：{$err}";
        bemfa_wechat_log($msg, [
            'api_type'=>$api_type,'request_method'=>strtoupper($method),'device'=>$request_params['device'],
            'status'=>'error','response_code'=>$code,'response_msg'=>$data
        ]);
        return ['success' => false, 'msg' => $msg, 'data' => $data];
    }
}

// 快捷推送函数（对外暴露）
function bemfa_wechat_warn($message, $params = []) {
    $params['message'] = $message;
    return bemfa_wechat_api_request('warn', $params);
}
function bemfa_wechat_alert($message, $params = []) {
    $params['message'] = $message;
    return bemfa_wechat_api_request('alert', $params);
}

// ====================== 【模块4：后台主页面】- 融合状态卡片+快捷操作+配置+测试 ======================
function bemfa_wechat_add_admin_menu() {
    add_options_page(
        '自动推送',
        '自动推送',
        'manage_options',
        'bemfa-wechat-page',
        'bemfa_wechat_render_page'
    );
}

// 系统状态检查（配置/日志表/推送统计）+ 状态卡片图标
function bemfa_wechat_system_status() {
    global $wpdb;
    $table_name = $wpdb->prefix . BEMFA_WECHAT_LOG_TABLE;
    $status = [];
    // 检查日志表
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    $status['db_table'] = ['label'=>'日志表状态','value'=>$table_exists ? '正常' : '缺失','good'=>$table_exists,'icon'=>$table_exists ? 'database' : 'error'];
    // 检查配置
    $settings = bemfa_wechat_get_settings();
    $status['config'] = ['label'=>'私钥配置状态','value'=>empty($settings['uid']) ? '未配置（推送失败）' : '已配置','good'=>!empty($settings['uid']),'icon'=>!empty($settings['uid']) ? 'yes' : 'no'];
    // 检查最近推送
    if ($table_exists) {
        $last_success = $wpdb->get_var("SELECT time FROM $table_name WHERE status='success' ORDER BY time DESC LIMIT 1");
        $last_success_text = $last_success ? date('Y-m-d H:i:s', strtotime($last_success)) : '暂无成功记录';
        $last_success_good = $last_success ? (strtotime($last_success) > time() - 86400) : false;
        $status['last_push'] = [
            'label'=>'最近成功推送',
            'value'=>$last_success_text,
            'good'=>$last_success_good,
            'icon'=>$last_success_good ? 'clock' : 'warning'
        ];
        // 今日推送
        $today_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE DATE(time) = %s", date('Y-m-d')));
        $status['today_count'] = ['label'=>'今日推送总数','value'=>$today_count . ' 次','good'=>true,'icon'=>'chart-bar'];
    }
    return $status;
}

// 后台主页面 - 全套美化：居中+原生标签页+状态卡片+样式适配
function bemfa_wechat_render_page() {
    if (!current_user_can('manage_options')) wp_die('无权限访问');
    $status = bemfa_wechat_system_status();
    wp_enqueue_style('wp-tabs');
    ?>
    <div class="wrap bemfa-wechat-wrap" style="max-width:1200px;margin:0 auto;padding:20px 0;">
        <h1 class="wp-heading-inline">巴法云微信推送【终极增强版】v<?php echo BEMFA_WECHAT_VERSION; ?></h1>
        <a href="<?php echo admin_url('options-general.php?page=bemfa-wechat-logs'); ?>" class="page-title-action">
            <span class="dashicons dashicons-list-view" style="margin-right:4px;"></span>查看推送日志
        </a>
        <a href="https://cloud.bemfa.com/" target="_blank" class="page-title-action">
            <span class="dashicons dashicons-external" style="margin-right:4px;"></span>巴法云控制台
        </a>
        <hr class="wp-header-end">

        <!-- 系统状态卡片：居中+美化+图标 -->
        <div class="bemfa-status-cards" style="display:flex;gap:15px;margin:20px 0;flex-wrap:wrap;">
            <?php foreach ($status as $key => $item): ?>
                <div style="flex:1;min-width:220px;padding:20px;background:#fff;border:1px solid #ccd0d4;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                        <div style="font-size:14px;color:#666;font-weight:500;"><?php echo esc_html($item['label']); ?></div>
                        <span class="dashicons dashicons-<?php echo $item['icon']; ?>" style="color:<?php echo $item['good'] ? '#46b450' : '#dc3232'; ?>;font-size:20px;"></span>
                    </div>
                    <div style="font-size:20px;color:<?php echo $item['good'] ? '#46b450' : '#dc3232'; ?>;font-weight:600;line-height:1.4;">
                        <?php echo esc_html($item['value']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- WordPress原生标签页：核心配置/自动推送/日志配置/高级配置/一键测试 -->
        <div class="nav-tab-wrapper wp-clearfix" style="margin:30px 0 20px;">
            <a href="#bemfa-tab-main" class="nav-tab nav-tab-active" data-tab="bemfa-tab-main">核心配置</a>
            <a href="#bemfa-tab-event" class="nav-tab" data-tab="bemfa-tab-event">自动推送事件</a>
            <a href="#bemfa-tab-log" class="nav-tab" data-tab="bemfa-tab-log">日志系统配置</a>
            <a href="#bemfa-tab-advanced" class="nav-tab" data-tab="bemfa-tab-advanced">高级配置</a>
            <a href="#bemfa-tab-test" class="nav-tab" data-tab="bemfa-tab-test">一键测试推送</a>
        </div>

        <!-- 配置表单：标签页容器 -->
        <form method="post" action="options.php" style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:8px;">
            <?php settings_fields('bemfa_wechat_group'); ?>
            <!-- 核心配置标签 -->
            <div id="bemfa-tab-main" class="bemfa-tab-content active" style="display:block;">
                <?php do_settings_sections('bemfa-wechat-page-bemfa_sec_main'); ?>
            </div>
            <!-- 自动推送事件标签 -->
            <div id="bemfa-tab-event" class="bemfa-tab-content" style="display:none;">
                <?php do_settings_sections('bemfa-wechat-page-bemfa_sec_event'); ?>
            </div>
            <!-- 日志系统配置标签 -->
            <div id="bemfa-tab-log" class="bemfa-tab-content" style="display:none;">
                <?php do_settings_sections('bemfa-wechat-page-bemfa_sec_log'); ?>
            </div>
            <!-- 高级配置标签 -->
            <div id="bemfa-tab-advanced" class="bemfa-tab-content" style="display:none;">
                <?php do_settings_sections('bemfa-wechat-page-bemfa_sec_advanced'); ?>
            </div>
            <!-- 一键测试推送标签 -->
            <div id="bemfa-tab-test" class="bemfa-tab-content" style="display:none;padding:10px 0;">
                <?php bemfa_wechat_render_test_push(); ?>
            </div>
            <!-- 保存按钮：居中 -->
            <div style="margin-top:20px;padding-top:20px;border-top:1px solid #eee;text-align:center;">
                <?php submit_button('保存所有配置', 'primary large', 'submit', false); ?>
            </div>
        </form>
    </div>

    <!-- 标签页切换JS + 全局样式 -->
    <style>
        .bemfa-tab-content {margin:10px 0;}
        .nav-tab-active {background:#fff;border-bottom-color:#fff!important;}
        .bemfa-wechat-wrap .form-table {margin:0;border:none;}
        .bemfa-wechat-wrap .form-table th {width:200px;padding:15px 10px;border-bottom:1px solid #f0f0f0;}
        .bemfa-wechat-wrap .form-table td {padding:15px 10px;border-bottom:1px solid #f0f0f0;}
        .bemfa-wechat-wrap .description {margin:8px 0 0!important;padding:0!important;}
    </style>
    <script>
        jQuery(document).ready(function($){
            // 标签页切换
            $('.nav-tab').click(function(e){
                e.preventDefault();
                var tab = $(this).data('tab');
                // 切换标签激活状态
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                // 切换内容显示
                $('.bemfa-tab-content').hide();
                $('#' + tab).show();
            });
        });
    </script>
    <?php
}

// 一键测试推送 - 修复重复触发+防重复点击+超时处理
function bemfa_wechat_render_test_push() {
    $nonce = wp_create_nonce('bemfa_wechat_test_nonce');
    ?>
    <div style="margin-top:20px;padding:15px;background:#f5f5f5;border-left:4px solid #0073aa;">
        <h3>一键测试推送</h3>
        <p>选择测试接口，点击按钮验证配置，结果即时显示</p>
        <div style="margin:10px 0;">
            <label><input type="radio" name="bemfa_test_type" value="warn" checked> 设备提醒（日常使用）</label>
            <label style="margin-left:20px;"><input type="radio" name="bemfa_test_type" value="alert"> 设备预警（异常告警）</label>
        </div>
        <button type="button" id="bemfa-test-push" class="button button-primary">发送测试消息</button>
        <div id="bemfa-test-result" style="margin-top:15px;font-weight:bold;"></div>
    </div>
    <script>
        jQuery(document).ready(function($){
            // 单例绑定，避免重复绑定事件导致多次触发
            $('#bemfa-test-push').off('click').on('click', function(){
                var btn = $(this), 
                    res = $('#bemfa-test-result'), 
                    type = $('input[name=bemfa_test_type]:checked').val();
                
                // 防重复点击：禁用按钮+修改文字+清空结果
                btn.prop('disabled',true).addClass('disabled').text('发送中...');
                res.html('').css({color:'#333', fontWeight:'bold'});
                
                // 发起AJAX请求
                $.ajax({
                    url: ajaxurl, 
                    type: 'POST',
                    data: {action:'bemfa_wechat_test_push',test_type:type,nonce:'<?php echo $nonce; ?>'},
                    timeout: 15000, // 15秒超时兜底
                    success: function(data){
                        // 恢复按钮状态
                        btn.prop('disabled',false).removeClass('disabled').text('发送测试消息');
                        // 显示结果（成功绿色/失败红色）
                        var color = data.success ? '#46b450' : '#dc3232';
                        res.css('color', color).html(data.data.msg || '请求完成，无返回信息');
                    },
                    error: function(xhr, status, err){
                        // 超时/网络错误也恢复按钮
                        btn.prop('disabled',false).removeClass('disabled').text('发送测试消息');
                        var errMsg = status === 'timeout' ? '请求超时（请检查网络/巴法云接口）' : '网络错误';
                        res.css('color','#dc3232').html(errMsg + '，请前往【推送日志】查看详情');
                    }
                });
            });
        });
    </script>
    <?php
}

// AJAX测试处理
function bemfa_wechat_test_push_ajax() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'bemfa_wechat_test_nonce')) {
        wp_send_json_error(['msg' => '权限验证失败，仅管理员可测试']);
    }
    $type = in_array($_POST['test_type'], ['alert', 'warn']) ? $_POST['test_type'] : 'warn';
    $msg = $type === 'warn' ? '【巴法云测试】设备提醒接口测试成功！' : '【巴法云测试】设备预警接口测试成功！';
    $result = $type === 'warn' ? bemfa_wechat_warn($msg) : bemfa_wechat_alert($msg);

    if ($result['success']) {
        $settings = bemfa_wechat_get_settings();
        wp_send_json_success(['msg' => $result['msg'] . ' | 请求方式：' . strtoupper($settings['request_method'])]);
    } else {
        wp_send_json_error(['msg' => $result['msg']]);
    }
    wp_die();
}

// ====================== 【模块5：短代码模块】- 编辑器/前台调用（无修改） ======================
function bemfa_wechat_warn_shortcode($atts) {
    $atts = shortcode_atts(['msg' => '提醒短代码测试', 'device' => ''], $atts, 'bemfa_wechat_warn');
    $result = bemfa_wechat_warn($atts['msg'], ['device' => $atts['device']]);
    if (current_user_can('manage_options')) {
        return $result['success'] ? '<span style="color:green;">' . $result['msg'] . '</span>' : '<span style="color:red;">' . $result['msg'] . '</span>';
    }
    return '';
}
add_shortcode('bemfa_wechat_warn', 'bemfa_wechat_warn_shortcode');

function bemfa_wechat_alert_shortcode($atts) {
    $atts = shortcode_atts(['msg' => '预警短代码测试', 'device' => ''], $atts, 'bemfa_wechat_alert');
    $result = bemfa_wechat_alert($atts['msg'], ['device' => $atts['device']]);
    if (current_user_can('manage_options')) {
        return $result['success'] ? '<span style="color:green;">' . $result['msg'] . '</span>' : '<span style="color:red;">' . $result['msg'] . '</span>';
    }
    return '';
}
add_shortcode('bemfa_wechat_alert', 'bemfa_wechat_alert_shortcode');

// ====================== 【模块6：自动推送】- 融合原生+6大新增事件（核心增强） ======================
function bemfa_wechat_register_auto_hooks() {
    $settings = bemfa_wechat_get_settings();
    // 原生事件钩子
    if ($settings['event_post']) add_action('publish_post', 'bemfa_wechat_auto_push_post', 10, 2);
    if ($settings['event_comment']) add_action('comment_approved', 'bemfa_wechat_auto_push_comment', 10, 2);
    if ($settings['event_woo'] && class_exists('WooCommerce')) add_action('woocommerce_new_order', 'bemfa_wechat_auto_push_woo', 10, 1);
    // 【新增】WP核心事件钩子
    if ($settings['event_comment_new']) add_action('comment_post', 'bemfa_wechat_auto_push_comment_new', 10, 2);
    if ($settings['event_user']) add_action('user_register', 'bemfa_wechat_auto_push_user', 10, 1);
    if ($settings['event_page']) add_action('publish_page', 'bemfa_wechat_auto_push_page', 10, 2);
    if ($settings['event_cpt']) add_action('publish_post', 'bemfa_wechat_auto_push_cpt', 10, 2);
    // 【新增】Woo高级事件钩子
    if ($settings['event_woo_paid'] && class_exists('WooCommerce')) add_action('woocommerce_order_status_paid', 'bemfa_wechat_auto_push_woo_paid', 10, 2);
    if ($settings['event_woo_complete'] && class_exists('WooCommerce')) add_action('woocommerce_order_status_completed', 'bemfa_wechat_auto_push_woo_complete', 10, 2);
}
add_action('init', 'bemfa_wechat_register_auto_hooks', 10);

// 原生推送函数
function bemfa_wechat_auto_push_post($post_ID, $post) {
    if ($post->post_status !== 'publish' || $post->post_type !== 'post') return;
    $msg = "【WP新文章发布】\n标题：{$post->post_title}\n链接：" . get_permalink($post_ID) . "\n发布时间：" . get_the_date('Y-m-d H:i:s', $post_ID);
    bemfa_wechat_warn($msg);
}
function bemfa_wechat_auto_push_comment($comment_ID, $comment) {
    if ($comment->comment_approved !== 1) return;
    $post = get_post($comment->comment_post_ID);
    $msg = "【WP评论审核通过】\n文章：{$post->post_title}\n作者：{$comment->comment_author}\n内容：{$comment->comment_content}";
    bemfa_wechat_warn($msg);
}
function bemfa_wechat_auto_push_woo($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    $user = $order->get_user();
    $msg = "【Woo新订单创建】\n订单号：{$order_id}\n用户：" . ($user ? $user->display_name : '游客') . "\n金额：{$order->get_formatted_order_total()}\n创建时间：" . $order->get_date_created()->format('Y-m-d H:i:s');
    bemfa_wechat_warn($msg);
}

// 【新增】WP核心事件推送函数
function bemfa_wechat_auto_push_comment_new($comment_ID, $commentdata) {
    $comment = get_comment($comment_ID);
    $post = get_post($comment->comment_post_ID);
    $status = $comment->comment_approved == 0 ? '待审核' : ($comment->comment_approved == 'spam' ? '垃圾评论' : '已通过');
    $msg = "【WP新评论即时提交】\n文章：{$post->post_title}\n评论人：{$comment->comment_author}\n内容：{$comment->comment_content}\n状态：{$status}";
    bemfa_wechat_warn($msg);
}
function bemfa_wechat_auto_push_user($user_id) {
    $user = get_user_by('id', $user_id);
    if (!$user) return;
    $msg = "【WP新用户注册成功】\n用户名：{$user->user_login}\n注册邮箱：{$user->user_email}\n用户ID：{$user_id}\n注册时间：" . date('Y-m-d H:i:s', strtotime($user->user_registered));
    bemfa_wechat_warn($msg);
}
function bemfa_wechat_auto_push_page($page_ID, $page) {
    if ($page->post_status !== 'publish' || $page->post_type !== 'page') return;
    $msg = "【WP新页面发布】\n标题：{$page->post_title}\n链接：" . get_permalink($page_ID) . "\n发布时间：" . get_the_date('Y-m-d H:i:s', $page_ID);
    bemfa_wechat_warn($msg);
}
function bemfa_wechat_auto_push_cpt($post_ID, $post) {
    // 排除原生文章/页面，仅推送自定义文章类型
    $exclude_types = ['post', 'page'];
    if ($post->post_status !== 'publish' || in_array($post->post_type, $exclude_types)) return;
    $cpt_name = get_post_type_object($post->post_type)->label;
    $msg = "【WP自定义文章发布】\n类型：{$cpt_name}\n标题：{$post->post_title}\n链接：" . get_permalink($post_ID) . "\n发布时间：" . get_the_date('Y-m-d H:i:s', $post_ID);
    bemfa_wechat_warn($msg);
}

// 【新增】WooCommerce高级事件推送函数
function bemfa_wechat_auto_push_woo_paid($order_id, $order) {
    $user = $order->get_user();
    $msg = "【Woo订单付款成功】\n订单号：{$order_id}\n用户：" . ($user ? $user->display_name : '游客') . "\n付款金额：{$order->get_formatted_order_total()}\n付款时间：" . $order->get_date_paid()->format('Y-m-d H:i:s');
    bemfa_wechat_warn($msg);
}
function bemfa_wechat_auto_push_woo_complete($order_id, $order) {
    $user = $order->get_user();
    $msg = "【Woo订单完成发货】\n订单号：{$order_id}\n用户：" . ($user ? $user->display_name : '游客') . "\n订单金额：{$order->get_formatted_order_total()}\n完成时间：" . $order->get_date_completed()->format('Y-m-d H:i:s');
    bemfa_wechat_warn($msg);
}

// ====================== 【模块7：生命周期】- 激活/卸载（融合日志+配置） ======================
function bemfa_wechat_activate() {
    // 1. 创建日志表
    bemfa_wechat_create_log_table();
    // 2. 设置默认配置
    if (!get_option('bemfa_wechat_settings')) {
        $default = [
            'uid'               => '',
            'device'            => 'WordPress站点',
            'group'             => 'default',
            'use_http'          => false,
            // 原生事件
            'event_post'        => false,
            'event_comment'     => false,
            'event_woo'         => false,
            // 新增事件（默认关闭）
            'event_comment_new'  => false,
            'event_user'         => false,
            'event_page'         => false,
            'event_cpt'          => false,
            'event_woo_paid'     => false,
            'event_woo_complete' => false,
            // 日志配置
            'enable_log'        => true,
            'enable_error_log'  => false,
            'log_retention_days'=> 30,
            'auto_clean_logs'   => false,
            // 请求配置
            'request_method'    => 'post',
        ];
        add_option('bemfa_wechat_settings', $default);
    }
    // 3. 创建定时清理任务
    if (!wp_next_scheduled('bemfa_wechat_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'bemfa_wechat_daily_cleanup');
    }
    // 记录激活日志
    bemfa_wechat_log("插件激活成功 v" . BEMFA_WECHAT_VERSION, ['status' => 'activation']);
}

function bemfa_wechat_uninstall() {
    if (!current_user_can('manage_options')) wp_die('无卸载权限');
    global $wpdb;
    $table_name = $wpdb->prefix . BEMFA_WECHAT_LOG_TABLE;
    // 1. 删除日志表
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    // 2. 删除配置
    delete_option('bemfa_wechat_settings');
    // 3. 清理定时任务
    wp_clear_scheduled_hook('bemfa_wechat_daily_cleanup');
    // 记录卸载日志
    error_log("[Bemfa WeChat] 终极增强版v" . BEMFA_WECHAT_VERSION . " 插件卸载，已清理所有配置、日志表和定时任务");
}

// 定时清理日志任务
add_action('bemfa_wechat_daily_cleanup', function() {
    $settings = bemfa_wechat_get_settings();
    if ($settings['auto_clean_logs']) bemfa_wechat_clean_old_logs();
});

// ====================== 【新增：仪表盘统计小工具】 ======================
function bemfa_wechat_dashboard_widget() {
    if (!current_user_can('manage_options')) return;
    wp_add_dashboard_widget(
        'bemfa_wechat_stats',
        '巴法云推送统计',
        'bemfa_wechat_render_dashboard_widget'
    );
}
function bemfa_wechat_render_dashboard_widget() {
    global $wpdb;
    $table_name = $wpdb->prefix . BEMFA_WECHAT_LOG_TABLE;
    // 今日推送统计
    $today = $wpdb->get_row($wpdb->prepare(
        "SELECT SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) as success,
        SUM(CASE WHEN status='error' THEN 1 ELSE 0 END) as error,COUNT(*) as total
        FROM $table_name WHERE DATE(time) = %s", date('Y-m-d')
    ));
    // 最近5条推送
    $recent = $wpdb->get_results("SELECT device, message, status, time FROM $table_name ORDER BY time DESC LIMIT 5");
    ?>
    <div style="margin-bottom:15px;">
        <h3>今日推送统计</h3>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <div style="flex:1;min-width:80px;text-align:center;padding:10px;background:#46b450;color:white;border-radius:4px;">
                <div style="font-size:24px;"><?php echo $today->success ?? 0; ?></div>
                <div>成功</div>
            </div>
            <div style="flex:1;min-width:80px;text-align:center;padding:10px;background:#dc3232;color:white;border-radius:4px;">
                <div style="font-size:24px;"><?php echo $today->error ?? 0; ?></div>
                <div>失败</div>
            </div>
            <div style="flex:1;min-width:80px;text-align:center;padding:10px;background:#0073aa;color:white;border-radius:4px;">
                <div style="font-size:24px;"><?php echo $today->total ?? 0; ?></div>
                <div>总计</div>
            </div>
        </div>
    </div>
    <?php if ($recent): ?>
        <h3>最近推送记录</h3>
        <table style="width:100%;font-size:12px;">
            <thead><tr><th>设备</th><th>状态</th><th>时间</th></tr></thead>
            <tbody>
                <?php foreach ($recent as $log): ?>
                    <tr>
                        <td><?php echo esc_html(mb_substr($log->device, 0, 12, 'utf-8')); ?></td>
                        <td><span class="dashicons dashicons-<?php echo $log->status === 'success' ? 'yes' : 'no'; ?>"></span> <?php echo $log->status; ?></td>
                        <td><?php echo date('H:i', strtotime($log->time)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <div style="margin-top:15px;padding-top:10px;border-top:1px solid #eee;">
        <a href="<?php echo admin_url('options-general.php?page=bemfa-wechat-logs'); ?>" class="button button-primary">查看详细日志</a>
        <a href="<?php echo admin_url('options-general.php?page=bemfa-wechat-page'); ?>" class="button">插件配置</a>
    </div>
    <?php
}
add_action('wp_dashboard_setup', 'bemfa_wechat_dashboard_widget');

// ====================== 插件核心钩子注册（融合所有功能） ======================
add_action('admin_menu', function() {
    bemfa_wechat_add_admin_menu();
    bemfa_wechat_add_log_menu(); // 新增日志子菜单
}, 10);
add_action('admin_init', 'bemfa_wechat_register_settings', 10);
add_action('wp_ajax_bemfa_wechat_test_push', 'bemfa_wechat_test_push_ajax', 10);
add_action('wp_ajax_bemfa_wechat_clear_logs', 'bemfa_wechat_clear_logs_ajax', 10);
add_action('plugins_loaded', 'bemfa_wechat_load_textdomain', 10);
// 国际化（预留）
function bemfa_wechat_load_textdomain() {
    load_plugin_textdomain('bemfa-wechat-notify', false, dirname(plugin_basename(BEMFA_WECHAT_PLUGIN_FILE)) . '/languages/');
}

// 插件激活/卸载钩子
register_activation_hook(__FILE__, 'bemfa_wechat_activate');
register_uninstall_hook(__FILE__, 'bemfa_wechat_uninstall');
