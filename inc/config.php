<?php
// 防止直接访问文件
if (!defined('ABSPATH')) {
    exit('Access denied');
}

/**
 * 获取插件所有配置（带默认值，无配置时返回默认值，避免未定义错误）
 * @return array 配置数组
 */
function bemfa_wechat_get_settings() {
    return get_option('bemfa_wechat_settings', [
        'uid'           => '',          // 巴法云32位UID
        'device'        => 'WordPress站点', // 默认设备名
        'group'         => 'default',   // 默认分组（官方规范）
        'use_http'      => false,       // 是否使用HTTP协议（本地/硬件）
        'event_post'    => false,       // 文章发布推送开关
        'event_comment' => false,       // 评论审核通过推送开关
        'event_woo'     => false,       // Woo订单新订单推送开关
        'enable_log'    => true,        // 日志开关
        'request_method'=> 'post'       // 默认请求方式：post（线上推荐）
    ]);
}

/**
 * 注册WP配置项 + 配置节 + 字段（WP原生设置API）
 */
function bemfa_wechat_register_settings() {
    // 注册配置项，指定清洗函数，防止非法参数
    register_setting(
        'bemfa_wechat_group',          // 配置组名
        'bemfa_wechat_settings',       // 配置项名（对应get_option）
        ['sanitize_callback' => 'bemfa_wechat_sanitize_settings'] // 配置清洗
    );

    // 新增3个配置节：核心配置、事件开关、高级配置、测试推送
    add_settings_section(
        'bemfa_sec_main',    // 节ID
        '核心配置（巴法云控制台获取）<span style="color:red;">*</span>', // 节标题
        'bemfa_wechat_sec_main_desc', // 节描述函数
        'bemfa-wechat-page'  // 配置页slug
    );
    add_settings_section(
        'bemfa_sec_event',
        '自动推送事件开关（按需启用）',
        'bemfa_wechat_sec_event_desc',
        'bemfa-wechat-page'
    );
    add_settings_section(
        'bemfa_sec_advanced',
        '高级配置（协议/请求方式/日志）',
        'bemfa_wechat_sec_advanced_desc',
        'bemfa-wechat-page'
    );
    add_settings_section(
        'bemfa_sec_test',
        '一键测试推送（验证配置有效性）',
        'bemfa_wechat_sec_test_desc',
        'bemfa-wechat-page'
    );

    // 核心配置字段：UID/设备名/分组
    add_settings_field(
        'bemfa_field_uid',
        '巴法云用户私钥UID',
        'bemfa_wechat_field_uid',
        'bemfa-wechat-page',
        'bemfa_sec_main'
    );
    add_settings_field(
        'bemfa_field_device',
        '默认设备名称',
        'bemfa_wechat_field_device',
        'bemfa-wechat-page',
        'bemfa_sec_main'
    );
    add_settings_field(
        'bemfa_field_group',
        '默认消息分组',
        'bemfa_wechat_field_group',
        'bemfa-wechat-page',
        'bemfa_sec_main'
    );

    // 高级配置字段：HTTP协议/请求方式/日志开关
    add_settings_field(
        'bemfa_field_http',
        '使用HTTP协议',
        'bemfa_wechat_field_http',
        'bemfa-wechat-page',
        'bemfa_sec_advanced'
    );
    add_settings_field(
        'bemfa_field_method',
        '接口请求方式',
        'bemfa_wechat_field_method',
        'bemfa-wechat-page',
        'bemfa_sec_advanced'
    );
    add_settings_field(
        'bemfa_field_log',
        '开启插件日志',
        'bemfa_wechat_field_log',
        'bemfa-wechat-page',
        'bemfa_sec_advanced'
    );

    // 自动推送事件字段：文章/评论/Woo订单（Woo存在才显示）
    add_settings_field(
        'bemfa_field_event_post',
        '新文章发布时推送',
        'bemfa_wechat_field_event_post',
        'bemfa-wechat-page',
        'bemfa_sec_event'
    );
    add_settings_field(
        'bemfa_field_event_comment',
        '评论审核通过时推送',
        'bemfa_wechat_field_event_comment',
        'bemfa-wechat-page',
        'bemfa_sec_event'
    );
    if (class_exists('WooCommerce')) {
        add_settings_field(
            'bemfa_field_event_woo',
            'WooCommerce新订单时推送',
            'bemfa_wechat_field_event_woo',
            'bemfa-wechat-page',
            'bemfa_sec_event'
        );
    }
}

/**
 * 配置清洗函数（过滤非法参数，保证符合巴法云官方规范）
 * @param array $input 前端提交的原始配置
 * @return array 清洗后的合法配置
 */
function bemfa_wechat_sanitize_settings($input) {
    $output = [];
    $settings = bemfa_wechat_get_settings(); // 获取原有配置，兜底

    // 清洗UID：去空格、验证32位
    $output['uid'] = !empty($input['uid']) ? trim($input['uid']) : $settings['uid'];
    // 清洗设备名：去空格，非空兜底
    $output['device'] = !empty($input['device']) ? trim($input['device']) : $settings['device'];
    // 清洗分组：仅保留字母/数字（官方强制规范），默认default
    $output['group'] = !empty($input['group']) ? preg_replace('/[^a-zA-Z0-9]/', '', $input['group']) : 'default';
    // 布尔值清洗：checkbox提交的是on/off，转为true/false
    $output['use_http'] = isset($input['use_http']) && $input['use_http'] === 'on';
    $output['event_post'] = isset($input['event_post']) && $input['event_post'] === 'on';
    $output['event_comment'] = isset($input['event_comment']) && $input['event_comment'] === 'on';
    $output['event_woo'] = isset($input['event_woo']) && $input['event_woo'] === 'on';
    $output['enable_log'] = isset($input['enable_log']) && $input['enable_log'] === 'on';
    // 清洗请求方式：仅允许post/get
    $output['request_method'] = in_array($input['request_method'], ['post', 'get']) ? $input['request_method'] : 'post';

    return $output;
}

// ====================== 配置节描述函数 ======================
function bemfa_wechat_sec_main_desc() {
    echo '<p class="description">1. 先在<a href="https://cloud.bemfa.com/" target="_blank">巴法云控制台</a>绑定微信（关注「巴法云」公众号）；</p>';
    echo '<p class="description">2. UID在巴法云「个人中心」获取，为32位纯字符串，不可修改/缺失；</p>';
    echo '<p class="description">3. 分组仅支持<b>字母/数字</b>，不存在会自动创建，不填默认「default」。</p>';
}
function bemfa_wechat_sec_event_desc() {
    echo '<p class="description">开启后，对应事件触发时会自动推送微信通知，无需手动调用。</p>';
}
function bemfa_wechat_sec_advanced_desc() {
    echo '<p class="description">1. 本地/硬件设备勾选「使用HTTP协议」，线上站点（带SSL）请勿勾选；</p>';
    echo '<p class="description">2. 线上推荐「POST」方式，本地/硬件推荐「GET」方式（需配合HTTP）；</p>';
    echo '<p class="description">3. 日志开启后，可在WP后台「工具→站点健康→日志」查看请求细节。</p>';
}
function bemfa_wechat_sec_test_desc() {
    // 测试推送的HTML+JS在admin-page.php中实现，此处留空仅作容器
}

// ====================== 配置字段渲染函数（WP后台表单显示） ======================
function bemfa_wechat_field_uid() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="text" name="bemfa_wechat_settings[uid]" value="' . esc_attr($settings['uid']) . '" class="regular-text" placeholder="32位纯字符串，巴法云个人中心获取" required>';
    echo '<p class="description">必填！32位UID错误会导致推送失败</p>';
}
function bemfa_wechat_field_device() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="text" name="bemfa_wechat_settings[device]" value="' . esc_attr($settings['device']) . '" class="regular-text" placeholder="自定义设备名，如「我的WP站点」">';
}
function bemfa_wechat_field_group() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="text" name="bemfa_wechat_settings[group]" value="' . esc_attr($settings['group']) . '" class="regular-text" placeholder="仅字母/数字，默认default">';
}
function bemfa_wechat_field_http() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="checkbox" name="bemfa_wechat_settings[use_http]" ' . checked($settings['use_http'], true, false) . '>';
    echo '<p class="description">本地/硬件设备勾选，线上站点（HTTPS）请勿勾选</p>';
}
function bemfa_wechat_field_method() {
    $settings = bemfa_wechat_get_settings();
    echo '<select name="bemfa_wechat_settings[request_method]" class="regular-text">';
    echo '<option value="post" ' . selected($settings['request_method'], 'post', false) . '>POST（线上推荐）</option>';
    echo '<option value="get" ' . selected($settings['request_method'], 'get', false) . '>GET（本地/硬件推荐）</option>';
    echo '</select>';
}
function bemfa_wechat_field_log() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="checkbox" name="bemfa_wechat_settings[enable_log]" ' . checked($settings['enable_log'], true, false) . '>';
    echo '<p class="description">开启后可在WP后台查看请求日志，建议开启</p>';
}
function bemfa_wechat_field_event_post() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="checkbox" name="bemfa_wechat_settings[event_post]" ' . checked($settings['event_post'], true, false) . '>';
}
function bemfa_wechat_field_event_comment() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="checkbox" name="bemfa_wechat_settings[event_comment]" ' . checked($settings['event_comment'], true, false) . '>';
}
function bemfa_wechat_field_event_woo() {
    $settings = bemfa_wechat_get_settings();
    echo '<input type="checkbox" name="bemfa_wechat_settings[event_woo]" ' . checked($settings['event_woo'], true, false) . '>';
}
