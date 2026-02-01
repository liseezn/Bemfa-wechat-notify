<?php
// 防止直接访问文件
if (!defined('ABSPATH')) {
    exit('Access denied');
}

/**
 * 注册自动推送钩子（按后台开关，按需注册，减少性能消耗）
 */
function bemfa_wechat_register_auto_hooks() {
    $settings = bemfa_wechat_get_settings();
    // 文章发布推送（仅发布公开文章时触发）
    if ($settings['event_post']) {
        add_action('publish_post', 'bemfa_wechat_auto_push_post', 10, 2);
    }
    // 评论审核通过推送
    if ($settings['event_comment']) {
        add_action('comment_approved', 'bemfa_wechat_auto_push_comment', 10, 2);
    }
    // WooCommerce新订单推送（仅安装Woo且开启开关时触发）
    if ($settings['event_woo'] && class_exists('WooCommerce')) {
        add_action('woocommerce_new_order', 'bemfa_wechat_auto_push_woo', 10, 1);
    }
}
add_action('init', 'bemfa_wechat_register_auto_hooks', 10);

/**
 * 文章发布自动推送
 * @param int $post_ID 文章ID
 * @param WP_Post $post 文章对象
 */
function bemfa_wechat_auto_push_post($post_ID, $post) {
    // 仅推送公开文章，排除草稿/私密/定时文章
    if ($post->post_status !== 'publish' || $post->post_type !== 'post') {
        return;
    }
    $message = "【WP新文章发布】\n标题：{$post->post_title}\n链接：" . get_permalink($post_ID);
    bemfa_wechat_warn($message);
}

/**
 * 评论审核通过自动推送
 * @param string $comment_ID 评论ID
 * @param WP_Comment $comment 评论对象
 */
function bemfa_wechat_auto_push_comment($comment_ID, $comment) {
    // 仅推送审核通过的评论
    if ($comment->comment_approved !== 1) {
        return;
    }
    $post = get_post($comment->comment_post_ID);
    $message = "【WP新评论审核通过】\n文章：{$post->post_title}\n评论人：{$comment->comment_author}\n评论内容：{$comment->comment_content}";
    bemfa_wechat_warn($message);
}

/**
 * WooCommerce新订单自动推送
 * @param int $order_id 订单ID
 */
function bemfa_wechat_auto_push_woo($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    $user = $order->get_user();
    $message = "【Woo新订单提醒】\n订单号：{$order_id}\n用户：" . ($user ? $user->display_name : '游客') . "\n金额：{$order->get_formatted_order_total()}\n时间：" . $order->get_date_created()->format('Y-m-d H:i:s');
    bemfa_wechat_warn($message);
}
