# Bemfa-wechat-notify

![WordPress Compatible](https://img.shields.io/badge/WordPress-5.0%2B-blue)
![PHP Compatible](https://img.shields.io/badge/PHP-7.0%2B-green)
![License](https://img.shields.io/badge/License-GPLv2-orange)

基于巴法云微信接口开发的WordPress插件，实现微信消息推送功能，支持设备预警、设备提醒两类推送，提供后台可视化配置、短代码调用、函数调用三种使用方式，轻量无依赖，适配所有主流WP版本。

## 📌 项目简介

本插件是巴法云微信通知接口在WordPress生态的官方适配插件，无需编写复杂代码，即可实现WP站点各类事件（文章发布、评论提交、订单生成等）的微信消息推送，同时支持开发人员二次扩展，满足个性化推送需求。

核心适配巴法云两个核心接口：
- 设备预警接口（wechatAlertJson）：适用于站点告警、异常通知等重要消息
- 设备提醒接口（wechatWarnJson）：适用于日常通知、常规提醒等普通消息

## ✨ 核心特性

- 🖥️ **可视化配置**：WP后台直接设置巴法云UID、默认设备名、默认分组，无需修改代码
- 🔧 **多调用方式**：支持短代码（非开发人员）、函数调用（开发人员）、快捷函数三种方式
- 📱 **完整适配**：完美对接巴法云微信接口，支持自定义设备名、分组、跳转链接
- 🚀 **轻量无依赖**：纯原生WP代码开发，不依赖任何第三方插件，不占用多余资源
- 🔍 **错误排查**：内置错误日志记录，后台可查看推送失败原因，快速定位问题
- 💡 **兼容广泛**：适配WP 5.0+所有版本，PHP 7.0+，兼容各类主题/插件，无冲突
- 🔄 **灵活扩展**：支持二次开发，可快速添加定时推送、多UID推送等自定义功能

## 📋 前置准备

使用插件前，需先完成巴法云微信绑定，获取用户私钥UID：
1. 访问 [巴法云控制台](https://cloud.bemfa.com/)，注册/登录账号
2. 在控制台绑定微信账号，按照提示关注公众号「巴法云」完成自动绑定
3. 绑定成功后，在巴法云控制台「个人中心/密钥管理」处，复制你的 **用户私钥UID**（32位字符串，必填）

## 🔧 安装步骤

提供两种安装方式，任选其一即可：

### 方式1：后台上传安装（推荐，非开发人员）
1. 从 GitHub Releases 页面下载插件ZIP压缩包：[Releases](https://github.com/liseezn/Bemfa-wechat-notify/releases)
2. 登录WordPress后台 → 插件 → 安装插件 → 上传插件
3. 选择下载的ZIP压缩包，点击「安装现在」
4. 安装完成后，点击「启用插件」，即可进入配置页面

### 方式2：手动上传安装（开发人员）
1. 克隆本仓库：
   ```bash
   git clone https://github.com/liseezn/Bemfa-wechat-notify.git
   ```
2. 将插件文件夹 `Bemfa-wechat-notify` 复制到WP插件目录：`wp-content/plugins/`
3. 登录WordPress后台 → 插件 → 已安装插件，找到「巴法云微信通知推送」，点击「启用」

## ⚙️ 基础配置

插件启用后，进入配置页面完成基础设置（必填项：巴法云UID）：
1. 登录WP后台 → 设置 → 巴法云微信通知
2. 填写以下配置项（带*为必填）：
   - 巴法云用户私钥UID*：粘贴你在巴法云控制台获取的UID
   - 默认设备名称：推送消息时显示的设备名，默认「WordPress站点」，可自定义
   - 默认消息分组：消息分组（仅限字母/数字），不填默认「default」，分组不存在会自动创建
3. 点击「保存配置」，完成基础设置，即可开始使用推送功能

## 📖 使用教程

插件提供3种调用方式，适配非开发人员、开发人员不同需求，所有调用方式均支持自定义消息、设备名、分组、跳转链接。

### 方式1：短代码调用（非开发人员推荐）

可直接在「文章/页面/自定义小工具」中使用短代码，推送微信消息，**仅管理员可见推送结果**，普通游客无感知。

#### 基础用法（仅传消息，使用默认配置）
```markdown
# 设备预警推送（适用于告警、异常通知）
[bafayun_alert msg="WP站点检测到异常访问！"]

# 设备提醒推送（适用于日常通知、常规提醒，推荐）
[bafayun_warn msg="您的站点有新评论待审核！"]
```

#### 高级用法（自定义设备名/分组/跳转链接）
```markdown
[bafayun_warn 
  msg="新订单生成：订单号123456，金额99元" 
  device="WP电商站点"  # 自定义设备名（覆盖默认配置）
  group="wp_order"     # 自定义分组（覆盖默认配置）
  url="https://你的站点.com/wp-admin/edit.php?post_type=shop_order"  # 点击消息跳转链接
]
```

### 方式2：函数调用（开发人员推荐）

可在「主题functions.php、其他插件代码」中调用核心函数，实现**触发式自动推送**（如文章发布、评论提交、订单生成时自动推送）。

#### 基础用法（仅传消息，使用默认配置）
```php
// 设备预警推送
bafayun_wechat_alert("WP站点磁盘空间不足，剩余空间不足10%！");

// 设备提醒推送
bafayun_wechat_warn("您的文章《XXX》已成功发布！");

// 全局快捷函数（默认提醒类型，指定type=alert为预警）
bafayun_wechat_push("新评论提醒：用户XXX评论了文章《XXX》");
bafayun_wechat_push("站点异常：数据库连接失败！", 'alert'); // 预警推送
```

#### 高级用法（自定义设备名/分组/跳转链接）
```php
// 推送新订单消息，自定义设备名、分组，点击跳转订单详情页
bafayun_wechat_warn(
    "【新订单提醒】订单号：NO.20260201001，金额：99元，状态：待付款", // 消息内容
    "WP商城站点", // 自定义设备名
    "wp_shop_order", // 自定义分组
    "https://your-site.com/wp-admin/post.php?post=123&action=edit" // 跳转链接（订单详情）
);
```

### 方式3：实用场景示例（开发人员参考）

结合WP钩子，实现触发式自动推送，以下是常用场景示例，可直接复制到主题functions.php中使用。

#### 示例1：文章发布时自动推送
```php
// 文章发布后，自动推送微信提醒（排除草稿、修订版）
add_action('publish_post', 'bafayun_publish_post_push', 10, 2);
function bafayun_publish_post_push($post_ID, $post) {
    // 排除自定义文章类型，仅推送普通文章
    if ($post->post_type != 'post') return;
    // 组装消息内容（包含文章标题、链接）
    $msg = "【新文章发布】\n" . $post->post_title . "\n访问链接：" . get_permalink($post_ID);
    // 执行推送（使用默认配置，设备提醒类型）
    bafayun_wechat_warn($msg);
}
```

#### 示例2：新评论提交时自动推送
```php
// 新评论审核通过后，自动推送微信提醒
add_action('comment_post', 'bafayun_comment_post_push', 10, 2);
function bafayun_comment_post_push($comment_ID, $comment_approved) {
    // 仅推送审核通过的评论（避免垃圾评论推送）
    if ($comment_approved != 1) return;
    $comment = get_comment($comment_ID);
    $post = get_post($comment->comment_post_ID);
    // 组装消息内容（包含评论人、评论内容、所属文章）
    $msg = "【新评论提醒】\n评论人：" . $comment->comment_author . "\n评论内容：" . mb_substr($comment->comment_content, 0, 50, 'utf-8') . "...\n所属文章：" . $post->post_title;
    // 执行推送，点击消息跳转至评论所在文章
    bafayun_wechat_warn($msg, '', '', get_permalink($post->ID) . '#comment-' . $comment_ID);
}
```

#### 示例3：Woocommerce新订单推送（电商站点）
```php
// 适用于安装了Woocommerce插件的电商站点，新订单生成时自动推送
add_action('woocommerce_new_order', 'bafayun_woocommerce_new_order_push');
function bafayun_woocommerce_new_order_push($order_id) {
    $order = wc_get_order($order_id);
    // 组装订单消息（包含订单号、金额、下单人）
    $msg = "【新订单提醒】\n订单号：" . $order->get_order_number() . "\n下单人：" . $order->get_billing_full_name() . "\n订单金额：" . $order->get_formatted_order_total() . "\n下单时间：" . $order->get_date_created()->format('Y-m-d H:i:s');
    // 执行推送，点击消息跳转至后台订单编辑页
    bafayun_wechat_warn($msg, 'WP电商站点', 'wp_order', admin_url('post.php?post=' . $order_id . '&action=edit'));
}
```

## 🔍 错误排查

若推送失败，按以下步骤排查，优先检查基础配置和必填项：
1. 基础配置检查：确认巴法云UID填写正确、微信已绑定巴法云并关注公众号
2. 消息内容检查：确保推送消息不为空，避免特殊字符过多导致解析失败
3. 错误日志查看：WP后台 → 工具 → 站点健康 → 日志 → 查看「PHP错误日志」，插件会记录所有推送相关错误（如接口请求失败、UID错误等）
4. SSL问题：若站点无SSL证书，可修改插件核心代码，将接口URL的`https`改为`http`，并关闭SSL验证（参考下方扩展说明）
5. 分组规则：消息分组仅限「字母/数字」，不可包含中文、符号，否则会自动过滤为空（默认改为default）

## 🔄 扩展与二次开发

本插件支持二次开发，可根据需求扩展功能，以下是常用扩展场景示例：

### 扩展1：适配硬件设备HTTP协议

若需在硬件端（如单片机）使用，可修改插件核心请求函数，切换为HTTP接口：
```php
// 找到插件中 bafayun_wechat_post_request 函数，修改以下两处：
// 1. 接口URL（HTTPS改为HTTP）
$api_url = 'http://apis.bemfa.com/vb/wechat/v1/wechatAlertJson'; // 预警接口
$api_url = 'http://apis.bemfa.com/vb/wechat/v1/wechatWarnJson'; // 提醒接口

// 2. 关闭SSL验证（硬件端无需SSL）
'sslverify' => false,
```

### 扩展2：添加定时推送功能

结合WP定时任务（WP Cron），实现每日/每周站点统计推送，需配合WP Crontrol等定时任务管理插件：
```php
// 注册定时任务，每日固定时间推送站点统计
add_action('bafayun_daily_push', 'bafayun_daily_stat_push');
function bafayun_daily_stat_push() {
    // 获取站点统计数据
    $post_count = wp_count_posts()->publish; // 已发布文章数
    $comment_count = wp_count_comments()->approved; // 已审核评论数
    // 组装消息
    $msg = "【站点每日统计】\n日期：" . date('Y-m-d') . "\n已发布文章：{$post_count} 篇\n已审核评论：{$comment_count} 条";
    // 执行推送
    bafayun_wechat_warn($msg);
}
```

### 扩展3：多UID/多微信账号推送

若需推送到多个微信账号，可在后台添加多UID配置项，修改核心推送函数，循环调用接口推送：
```php
// 示例：多UID循环推送
function bafayun_multi_uid_push($msg) {
    $uids = array('uid1', 'uid2', 'uid3'); // 多个巴法云UID
    $options = bafayun_wechat_get_options();
    foreach ($uids as $uid) {
        $params = array(
            'uid' => $uid,
            'device' => $options['bafayun_device'],
            'message' => $msg,
            'group' => $options['bafayun_group']
        );
        // 调用接口推送（以提醒接口为例）
        $api_url = 'https://apis.bemfa.com/vb/wechat/v1/wechatWarnJson';
        bafayun_wechat_post_request($api_url, $params);
    }
}
```

## 🤝 贡献指南

欢迎各位开发者参与贡献，完善插件功能，提交PR前请遵守以下规范：
1. Fork 本仓库
2. 创建特性分支：`git checkout -b feature/xxx`（xxx为功能名称，如`feature/timed-push`）
3. 提交代码：`git commit -m "feat: 新增定时推送功能"`（提交信息遵循Conventional Commits规范）
4. 推送分支：`git push origin feature/xxx`
5. 打开Pull Request，描述功能变更、测试情况，等待审核

同时，欢迎提交Issue反馈Bug、提出功能建议，反馈时请详细描述问题场景、复现步骤，方便快速定位解决。

## 📄 许可证

本项目采用 [GPLv2 许可证](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html) 开源（符合WordPress插件开源规范），允许自由使用、修改、分发，前提是：
- 保留原作者版权信息
- 修改后的代码同样采用GPLv2许可证开源

## 🙏 致谢

- 感谢 [巴法云](https://bemfa.com/) 提供微信通知API接口支持
- 感谢 WordPress 官方提供的插件开发文档和生态支持

## 📞 联系与反馈

- Bug反馈/功能建议：提交 [Issue](https://github.com/liseezn/Bemfa-wechat-notify/issues)
- 插件使用问题：可参考 [巴法云官方教程](https://bbs.bemfa.com/12)，或在Issue中留言

---

最后更新时间：2026-02-01  
插件版本：v1.0
