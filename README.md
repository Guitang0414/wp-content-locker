# WP Content Locker - 使用教程

WP Content Locker 是一个 WordPress 订阅付费墙插件，让你可以将文章内容锁定，只有订阅用户才能查看完整内容。

## 目录

1. [功能特点](#功能特点)
2. [安装插件](#安装插件)
3. [配置 Stripe](#配置-stripe)
4. [插件设置](#插件设置)
5. [启用文章付费墙](#启用文章付费墙)
6. [测试订阅流程](#测试订阅流程)
7. [高级功能：URL 测试模式](#高级功能url-测试模式)
8. [上线生产环境](#上线生产环境)
9. [常见问题](#常见问题)

---

## 功能特点

- **内容预览**: 未订阅用户只能看到文章的 30%（可自定义）
- **渐变遮罩**: 预览内容平滑淡出，视觉效果优雅
- **Stripe 支付**: 安全可靠的支付处理
- **订阅计划**: 支持月付和年付两种方案
- **自动注册**: 订阅时自动创建 WordPress 账户
- **自动登录**: 支付成功后自动登录并返回文章
- **逐篇控制**: 可以选择哪些文章需要付费墙

---

## 安装插件

### 方法一：直接上传

1. 将 `wp-content-locker` 文件夹上传到 WordPress 的 `/wp-content/plugins/` 目录

2. 登录 WordPress 后台，进入 **插件** 页面

3. 找到 "WP Content Locker"，点击 **启用**

### 方法二：命令行

```bash
# 复制插件到 WordPress
cp -r wp-content-locker /path/to/your/wordpress/wp-content/plugins/
```

然后在 WordPress 后台启用插件。

---

## 配置 Stripe

### 第一步：创建 Stripe 账户

1. 访问 [stripe.com](https://stripe.com) 注册账户
2. 完成账户验证（测试模式不需要完整验证）

### 第二步：获取 API 密钥

1. 登录 [Stripe Dashboard](https://dashboard.stripe.com)

2. 确保左上角显示 **"Test mode"**（测试阶段）
   ![Test Mode](https://i.imgur.com/example.png)

3. 进入 **Developers → API keys**

4. 复制以下密钥：
   - **Publishable key**: `pk_test_xxxxxx`
   - **Secret key**: `sk_test_xxxxxx`（点击 "Reveal" 查看）

### 第三步：创建订阅产品

1. 进入 **Products → Add Product**

2. **创建月付计划：**
   - Product name: `月度订阅` 或 `Monthly Subscription`
   - Price: 输入价格，如 `9.99`
   - Currency: `USD`（或你需要的货币）
   - Billing period: `Monthly`
   - 点击 **Save product**
   - 复制生成的 **Price ID**（格式：`price_xxxxxx`）

3. **创建年付计划：**
   - 重复上述步骤
   - Billing period: `Yearly`
   - 价格建议设置优惠，如 `99.99`（相当于省 2 个月）
   - 复制 **Price ID**

### 第四步：配置 Webhook

Webhook 让 Stripe 在支付成功、订阅取消等事件时通知你的网站。

**线上环境：**

1. 进入 **Developers → Webhooks**

2. 点击 **Add endpoint**

3. 填写：
   - **Endpoint URL**: `https://你的网站.com/wp-json/wp-content-locker/v1/webhook`
   - **Events to send**: 选择以下事件
     - `checkout.session.completed`
     - `customer.subscription.updated`
     - `customer.subscription.deleted`
     - `invoice.payment_failed`

4. 点击 **Add endpoint**

5. 复制 **Signing secret**（点击 "Reveal"）: `whsec_xxxxxx`

**本地测试环境：**

如果你在本地开发，Stripe 无法访问 localhost，需要使用 Stripe CLI：

```bash
# macOS 安装
brew install stripe/stripe-cli/stripe

# Windows (使用 scoop)
scoop install stripe

# 登录 Stripe
stripe login

# 启动 webhook 转发
stripe listen --forward-to http://localhost/wp-json/wp-content-locker/v1/webhook
```

终端会显示 webhook signing secret，复制保存。

---

## 插件设置

1. 登录 WordPress 后台

2. 进入 **设置 → Content Locker**

3. 填写配置：

### Stripe Settings

| 设置项 | 说明 | 示例 |
|--------|------|------|
| Stripe Mode | 测试用 Test，上线用 Live | `Test Mode` |
| Test Publishable Key | 测试公钥 | `pk_test_xxx` |
| Test Secret Key | 测试私钥 | `sk_test_xxx` |
| Webhook Secret | Webhook 签名密钥 | `whsec_xxx` |

### Subscription Plans

| 设置项 | 说明 | 示例 |
|--------|------|------|
| Monthly Price ID | 月付的 Stripe Price ID | `price_xxx` |
| Monthly Display Price | 前端显示的月付价格 | `9.99` |
| Yearly Price ID | 年付的 Stripe Price ID | `price_xxx` |
| Yearly Display Price | 前端显示的年付价格 | `99.99` |

### Display Settings

| 设置项 | 说明 | 示例 |
|--------|------|------|
| Preview Percentage | 预览内容百分比 | `30` |
| Paywall Title | 付费墙标题 | `Premium Content` |
| Paywall Description | 付费墙描述文字 | `Subscribe to read...` |
| Subscribe Button Text | 订阅按钮文字 | `Subscribe Now` |

4. 点击 **Save Settings**

---

## 启用文章付费墙

### 对单篇文章启用

1. 编辑一篇文章（**文章 → 所有文章 → 编辑**）

2. 在右侧边栏找到 **Content Locker** 面板

3. 勾选 **Enable paywall for this post**

4. （可选）设置该文章的自定义预览百分比

5. 点击 **更新** 保存文章

### 预览效果

- 未登录用户：看到 30% 内容 + 渐变遮罩 + 订阅框
- 已订阅用户：看到完整内容

---

## 测试订阅流程

### 第一步：确认设置

- Stripe Mode 设置为 **Test Mode**
- 所有 API 密钥和 Price ID 已填写
- 至少有一篇文章启用了付费墙

### 第二步：模拟用户访问

1. 打开一个新的浏览器窗口（或隐私模式）
2. 访问启用了付费墙的文章
3. 你应该看到：
   - 文章的部分内容
   - 渐变遮罩效果
   - 订阅价格选择
   - 邮箱输入框
   - "Subscribe Now" 按钮

### 第三步：完成测试订阅

1. 输入测试邮箱（任意有效格式即可）

2. 选择订阅计划（月付/年付）

3. 点击 **Subscribe Now**

4. 在 Stripe Checkout 页面，使用测试卡号：

   | 卡号 | 结果 |
   |------|------|
   | `4242 4242 4242 4242` | 支付成功 |
   | `4000 0000 0000 9995` | 支付失败 |

   - 有效期：任意未来日期（如 `12/34`）
   - CVC：任意 3 位数（如 `123`）
   - 其他信息随意填写

5. 点击订阅按钮

### 第四步：验证结果

**支付成功后：**
- 自动跳转回文章页面
- 显示 "Thank you for subscribing" 提示
- 可以看到完整文章内容
- 用户已自动登录

**检查 WordPress 后台：**
- **用户 → 所有用户**：应该有新创建的用户
- 用户角色为 **Subscriber**

**检查 Stripe Dashboard：**
- **Customers**：显示新客户
- **Subscriptions**：显示活跃订阅

---

## 高级功能：URL 测试模式

为了方便管理员在生产环境（Live Mode）下测试支付流程，插件提供了一个隐藏的 URL 参数触发器。

### 如何使用

1.  确保你已登录 **管理员账号**。
2.  在任何文章 URL 后面加上 `?wcl_test_mode=1`。
    - 正常链接：`https://yoursite.com/my-post/` -> **Live Mode**
    - 测试链接：`https://yoursite.com/my-post/?wcl_test_mode=1` -> **Test Mode**

### 效果

- 该页面会强制切换到 **Test Mode**。
- 显示的价格为测试环境价格。
- 支付流程连接到 Stripe Test 环境。
- 你可以使用测试卡号（4242...）进行支付。

### 安全性

- **仅限管理员**：普通用户即使添加了该参数，也只会看到 Live Mode。
- **互不干扰**：你的测试操作不会影响真实用户的订阅状态。

---

## 上线生产环境

测试通过后，按以下步骤切换到生产模式：

### 1. 获取 Live API 密钥

1. 在 Stripe Dashboard 关闭 "Test mode"
2. 进入 **Developers → API keys**
3. 复制 Live 密钥：
   - `pk_live_xxx`
   - `sk_live_xxx`

### 2. 创建 Live 产品

在 Live 模式下重新创建产品和价格，获取新的 Price ID。

### 3. 配置 Live Webhook

1. **Developers → Webhooks → Add endpoint**
2. 使用相同的 URL 和事件
3. 复制新的 Signing secret

### 4. 更新插件设置

1. Stripe Mode 改为 **Live Mode**
2. 填写 Live Publishable Key 和 Secret Key
3. 更新 Price ID（Live 模式的）
4. 更新 Webhook Secret
5. 保存设置

### 5. 测试真实支付

建议用小金额测试一次真实支付，确保一切正常。

---

## 常见问题

### Q: 订阅后还是看不到完整内容？

**可能原因：**
1. Webhook 没有正确配置
2. 用户没有被正确登录

**解决方法：**
- 检查 Stripe Webhook 事件是否成功发送
- 检查 WordPress 用户的 `_wcl_subscription_status` meta

### Q: 点击订阅没有反应？

**检查：**
1. 浏览器控制台是否有 JavaScript 错误
2. AJAX URL 是否正确
3. Stripe API 密钥是否正确

### Q: Webhook 返回错误？

**确保：**
1. Webhook URL 可以从外网访问
2. Webhook Secret 配置正确
3. SSL 证书有效（Live 模式必须）

### Q: 如何取消用户订阅？

1. 在 Stripe Dashboard → Subscriptions 中取消
2. Webhook 会自动更新 WordPress 中的订阅状态

### Q: 如何自定义付费墙样式？

编辑 `public/css/public.css` 文件，或在主题中添加自定义 CSS 覆盖样式。

### Q: 如何支持多种货币？

在 Stripe 创建产品时选择不同货币。插件显示的价格由设置中的 "Display Price" 控制。

---

## 技术支持

如有问题，请检查：
1. WordPress 调试日志 (`wp-content/debug.log`)
2. Stripe Dashboard 的 Webhook 日志
3. 浏览器开发者工具的 Network 和 Console

---

## 更新日志

### v1.0.0
- 初始版本
- 支持内容截断和渐变遮罩
- Stripe Checkout 集成
- 月付/年付订阅计划
- 自动用户注册和登录
- 逐篇文章付费墙控制
