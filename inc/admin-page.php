<?php
// 防止直接访问文件
if (!defined('ABSPATH')) {
    exit('Access denied');
}

/**
 * 添加WP后台菜单（设置子菜单）
 */
function bemfa_wechat_add_admin_menu() {
    add_options_page(
        '巴法云微信推送设置', // 页面标题
        '巴法云微信推送',     // 菜单标题
        'manage_options',     // 权限（仅管理员可访问）
        'bemfa-wechat-page',  // 页面slug
        'bemfa_wechat_render_page' // 页面渲染函数
    );
}

/**
 * 渲染插件配置页（WP原生设置表单）
 */
function bemfa_wechat_render_page() {
    // 仅管理员可访问
    if (!current_user_can('manage_options')) {
        wp_die('您没有权限访问此页面');
    }
    ?>
    <div class="wrap">
        <h1>巴法云微信通知推送 v<?php echo BEMFA_WECHAT_VERSION; ?></h1>
        <form method="post" action="options.php">
            <?php
            // WP原生设置表单：配置组+隐藏字段
            settings_fields('bemfa_wechat_group');
            // 渲染所有配置节和字段
            do_settings_sections('bemfa-wechat-page');
            // 保存按钮
            submit_button('保存所有配置', 'primary', 'submit', false);
            ?>
        </form>
        <?php bemfa_wechat_render_test_push(); // 渲染测试推送模块 ?>
    </div>
    <?php
}

/**
 * 渲染测试推送模块（含前端切换接口+AJAX按钮+结果显示）
 */
function bemfa_wechat_render_test_push() {
    $nonce = wp_create_nonce('bemfa_wechat_test_nonce'); // 生成安全验证nonce
    ?>
    <div style="margin-top:20px;padding:15px;background:#f5f5f5;border-left:4px solid #0073aa;">
        <h3>一键测试推送</h3>
        <p>选择测试接口，点击按钮验证配置，结果即时显示，同时生成日志</p>
        <!-- 接口类型切换：提醒/预警 -->
        <div style="margin:10px 0;">
            <label><input type="radio" name="bemfa_test_type" value="warn" checked> 设备提醒接口（日常使用）</label>
            <label style="margin-left:20px;"><input type="radio" name="bemfa_test_type" value="alert"> 设备预警接口（异常告警）</label>
        </div>
        <button type="button" id="bemfa-test-push" class="button button-primary">发送测试消息</button>
        <div id="bemfa-test-result" style="margin-top:15px;font-weight:bold;"></div>
    </div>

    <!-- AJAX测试JS（WP原生jQuery，无需额外引入） -->
    <script>
        jQuery(document).ready(function($) {
            $('#bemfa-test-push').click(function() {
                var btn = $(this);
                var result = $('#bemfa-test-result');
                var testType = $('input[name=bemfa_test_type]:checked').val();
                // 按钮置灰，显示加载
                btn.prop('disabled', true).text('发送中...');
                result.html('').removeClass('bemfa-success bemfa-error');

                // 发起AJAX请求（WP原生ajaxurl）
                $.ajax({
                    url: ajaxurl,
                    type: 'POST', // WP AJAX推荐POST，与巴法云请求方式无关
                    data: {
                        action: 'bemfa_wechat_test_push', // AJAX动作名（对应add_action）
                        test_type: testType,              // 测试接口类型
                        nonce: '<?php echo $nonce; ?>'    // 安全验证nonce
                    },
                    success: function(res) {
                        btn.prop('disabled', false).text('发送测试消息');
                        if (res.success) {
                            result.addClass('bemfa-success').css('color', '#46b450').html(res.data.msg);
                        } else {
                            result.addClass('bemfa-error').css('color', '#dc3232').html(res.data.msg);
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false).text('发送测试消息');
                        result.addClass('bemfa-error').css('color', '#dc3232').html('请求失败：网络错误或AJAX权限不足');
                    }
                });
            });
        });
    </script>
    <?php
}

/**
 * AJAX测试推送处理函数（仅管理员可调用，带nonce验证）
 * 支持alert/warn双接口，复用核心API函数
 */
function bemfa_wechat_test_push_ajax() {
    // 1. 安全验证：管理员权限 + nonce验证（双重防护）
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'bemfa_wechat_test_nonce')) {
        wp_send_json_error(['msg' => '权限验证失败：仅管理员可测试推送']);
    }

    // 2. 获取测试接口类型，兜底为warn
    $test_type = in_array($_POST['test_type'], ['alert', 'warn']) ? $_POST['test_type'] : 'warn';
    // 3. 构造测试消息
    $test_msg = $test_type === 'warn' 
        ? '【巴法云WP测试】设备提醒接口测试成功！' 
        : '【巴法云WP测试】设备预警接口测试成功！';

    // 4. 调用核心推送函数
    $result = $test_type === 'warn' ? bemfa_wechat_warn($test_msg) : bemfa_wechat_alert($test_msg);

    // 5. 返回AJAX结果（WP原生函数）
    if ($result['success']) {
        $settings = bemfa_wechat_get_settings();
        $msg = $result['msg'] . " | 请求方式：" . strtoupper($settings['request_method']);
        wp_send_json_success(['msg' => $msg]);
    } else {
        wp_send_json_error(['msg' => $result['msg']]);
    }

    wp_die(); // WP AJAX函数必须以wp_die()结尾，防止额外输出
}
