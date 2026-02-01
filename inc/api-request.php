<?php
// 防止直接访问文件
if (!defined('ABSPATH')) {
    exit('Access denied');
}

/**
 * 巴法云API基础请求函数（核心）
 * 支持：POST/GET双方式、alert/warn双接口、HTTP/HTTPS双协议
 * 严格贴合巴法云2026官方API文档
 * @param string $api_type 接口类型：alert（预警）/ warn（提醒）
 * @param array $params 自定义参数：device/message/group/url（可选）
 * @return array 响应结果：['success' => bool, 'msg' => string, 'data' => array]
 */
function bemfa_wechat_api_request($api_type, $params = []) {
    $settings = bemfa_wechat_get_settings();
    // 1. 基础配置：协议/请求方式/接口后缀（按官方文档）
    $protocol = $settings['use_http'] ? 'http' : 'https';
    $method = strtolower($settings['request_method']);
    $api_suffix = $api_type === 'alert' ? 'wechatAlert' : 'wechatWarn';
    $api_url = "{$protocol}://apis.bemfa.com/vb/wechat/v1/{$api_suffix}";

    // 2. 构造请求参数（官方必传+可选，兜底默认配置）
    $request_params = [
        'uid'       => $settings['uid'],
        'device'    => !empty($params['device']) ? $params['device'] : $settings['device'],
        'message'   => !empty($params['message']) ? $params['message'] : '',
        'group'     => !empty($params['group']) ? $params['group'] : $settings['group'],
    ];
    // 官方可选参数：跳转链接（仅验证合法URL才传递）
    if (!empty($params['url']) && filter_var($params['url'], FILTER_VALIDATE_URL)) {
        $request_params['url'] = $params['url'];
    }
    // 🔥 核心：GET方式-提醒接口，强制追加type=2（官方文档必选参数）
    if ($method === 'get' && $api_type === 'warn') {
        $request_params['type'] = 2;
    }

    // 3. 必传参数校验（按官方文档，缺一不可）
    if (empty($request_params['uid']) || strlen($request_params['uid']) !== 32) {
        $msg = 'UID错误：必须是32位纯字符串（巴法云个人中心获取）';
        bemfa_wechat_log($msg);
        return ['success' => false, 'msg' => $msg, 'data' => []];
    }
    if (empty($request_params['device'])) {
        $msg = '设备名不能为空（巴法云官方必传参数）';
        bemfa_wechat_log($msg);
        return ['success' => false, 'msg' => $msg, 'data' => []];
    }
    if (empty($request_params['message'])) {
        $msg = '推送消息不能为空（巴法云官方必传参数）';
        bemfa_wechat_log($msg);
        return ['success' => false, 'msg' => $msg, 'data' => []];
    }

    // 4. 构造WP请求参数（适配本地环境，强制关闭SSL验证）
    $wp_args = [
        'timeout'     => 20, // 延长超时时间，适配本地/局域网网络
        'sslverify'   => false, // 本地环境强制关闭，线上自动适配
        'redirection' => 3,
        'headers'     => [],
    ];

    // 5. POST/GET请求处理（严格按官方文档）
    if ($method === 'post') {
        // POST方式：官方要求Content-Type=application/json，JSON传参
        $wp_args['method'] = 'POST';
        $wp_args['headers']['Content-Type'] = 'application/json; charset=utf-8';
        $wp_args['body'] = json_encode($request_params, JSON_UNESCAPED_UNICODE);
        // 记录POST请求日志
        bemfa_wechat_log("POST请求准备 | 接口：{$api_url}Json | 参数：" . json_encode($request_params));
    } else {
        // GET方式：URL拼接参数（WP原生函数，自动URL编码）
        $wp_args['method'] = 'GET';
        $api_url = add_query_arg($request_params, $api_url);
        // 记录GET请求日志
        bemfa_wechat_log("GET请求准备 | 接口：{$api_url} | 参数：" . json_encode($request_params));
    }

    // 6. 发起请求并处理响应
    $response = wp_remote_request($api_url, $wp_args);
    // 处理WP请求错误（如网络不通、超时）
    if (is_wp_error($response)) {
        $err_msg = $response->get_error_message();
        $msg = "网络请求失败：{$err_msg}";
        bemfa_wechat_log($msg . " | 接口：{$api_url}");
        return ['success' => false, 'msg' => $msg, 'data' => []];
    }

    // 解析响应结果
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true) ?? [];

    // 处理HTTP状态码错误（非200）
    if ($response_code !== 200) {
        $msg = "HTTP请求错误，状态码：{$response_code}";
        bemfa_wechat_log($msg . " | 响应：{$response_body} | 接口：{$api_url}");
        return ['success' => false, 'msg' => $msg, 'data' => $response_data];
    }

    // 处理巴法云业务响应（官方code=0为成功）
    if (isset($response_data['code']) && $response_data['code'] === 0) {
        $msg = "推送成功（{$api_type}接口-{$method}方式）";
        bemfa_wechat_log($msg . " | 接口：{$api_url} | 响应：" . json_encode($response_data));
        return ['success' => true, 'msg' => $msg, 'data' => $response_data];
    } else {
        $err_msg = $response_data['msg'] ?? '未知错误（巴法云接口返回）';
        $msg = "推送失败：{$err_msg}";
        bemfa_wechat_log($msg . " | 接口：{$api_url} | 响应：" . json_encode($response_data));
        return ['success' => false, 'msg' => $msg, 'data' => $response_data];
    }
}

/**
 * 设备提醒推送快捷函数（对外暴露，支持直接调用）
 * @param string $message 推送消息
 * @param array $params 自定义参数：device/group/url（可选）
 * @return array 响应结果
 */
function bemfa_wechat_warn($message, $params = []) {
    $params['message'] = $message;
    return bemfa_wechat_api_request('warn', $params);
}

/**
 * 设备预警推送快捷函数（对外暴露，支持直接调用）
 * @param string $message 推送消息
 * @param array $params 自定义参数：device/group/url（可选）
 * @return array 响应结果
 */
function bemfa_wechat_alert($message, $params = []) {
    $params['message'] = $message;
    return bemfa_wechat_api_request('alert', $params);
}
