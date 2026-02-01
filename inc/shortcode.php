<?php
// 防止直接访问文件
if (!defined('ABSPATH')) {
    exit('Access denied');
}

/**
 * 设备提醒短代码：[bemfa_wechat_warn msg="自定义消息" device="自定义设备名"]
 * @param array $atts 短代码参数
 * @return string 执行结果
 */
function bemfa_wechat_warn_shortcode($atts) {
    // 短代码参数默认值
    $atts = shortcode_atts([
        'msg'    => '短代码测试：设备提醒推送',
        'device' => '', // 留空则使用后台默认设备名
    ], $atts, 'bemfa_wechat_warn');

    // 调用核心函数
    $result = bemfa_wechat_warn($atts['msg'], ['device' => $atts['device']]);
    // 前端显示结果（管理员可见，普通游客隐藏）
    if (current_user_can('manage_options')) {
        return $result['success'] 
            ? '<span style="color:green;">' . $result['msg'] . '</span>'
            : '<span style="color:red;">' . $result['msg'] . '</span>';
    }
    return ''; // 普通用户不显示任何内容
}
add_shortcode('bemfa_wechat_warn', 'bemfa_wechat_warn_shortcode');

/**
 * 设备预警短代码：[bemfa_wechat_alert msg="自定义消息" device="自定义设备名"]
 * @param array $atts 短代码参数
 * @return string 执行结果
 */
function bemfa_wechat_alert_shortcode($atts) {
    // 短代码参数默认值
    $atts = shortcode_atts([
        'msg'    => '短代码测试：设备预警推送',
        'device' => '', // 留空则使用后台默认设备名
    ], $atts, 'bemfa_wechat_alert');

    // 调用核心函数
    $result = bemfa_wechat_alert($atts['msg'], ['device' => $atts['device']]);
    // 前端显示结果（管理员可见，普通游客隐藏）
    if (current_user_can('manage_options')) {
        return $result['success'] 
            ? '<span style="color:green;">' . $result['msg'] . '</span>'
            : '<span style="color:red;">' . $result['msg'] . '</span>';
    }
    return ''; // 普通用户不显示任何内容
}
add_shortcode('bemfa_wechat_alert', 'bemfa_wechat_alert_shortcode');
