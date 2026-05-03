# Memos WordPress 集成完整指南

## 概述

本指南提供两种方案，将 Memos 公开备忘录集成到 WordPress 博客中。

---

## 方案对比

| 特性 | PHP 插件方案 | JavaScript 方案 |
|------|-------------|----------------|
| 安装难度 | 简单（上传插件） | 简单（复制代码） |
| 性能 | 服务端缓存 | 浏览器缓存 |
| 灵活性 | 中等 | 高 |
| 定制性 | 需要 PHP 知识 | 只需 JS/CSS |
| 推荐度 | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |

---

## 方案 1：WordPress 插件（推荐新手）

### 安装步骤

#### 1. 上传插件文件

将 `memos-widget` 文件夹上传到 WordPress：

```
wp-content/
└── plugins/
    └── memos-widget/
        ├── memos-widget.php
        ├── assets/
        │   ├── style.css
        │   └── memos-api.js
        └── README.md
```

#### 2. 激活插件

1. 登录 WordPress 后台
2. 进入 **插件** 页面
3. 找到 "Memos Widget"
4. 点击 **启用**

#### 3. 配置插件

1. 进入 **设置 → Memos Widget**
2. 填写配置：
   ```
   Memos 服务器地址：http://你的NAS_IP:5230
   创建者 ID：（可选，留空显示所有）
   显示数量：10
   缓存时间：3600
   显示样式：卡片式
   ```
3. 点击 **保存设置**

#### 4. 使用短代码

在文章或页面中插入：

```
[memos]
```

或带参数：

```
[memos limit="5" style="card" show_date="true" show_tags="true"]
```

---

## 方案 2：JavaScript 嵌入（推荐高级用户）

### 安装步骤

#### 1. 上传 JS 文件

将 `memos-embed.js` 上传到主题目录：

```
wp-content/
└── themes/
    └── 你的主题/
        └── js/
            └── memos-embed.js
```

#### 2. 在页面中添加容器

在文章或页面中（HTML 模式）添加：

```html
<div id="memos-container"></div>
```

#### 3. 加载并初始化

在主题的 `footer.php` 中添加：

```php
<?php if (is_page('memos') || has_shortcode($post->post_content, 'memos')): ?>
    <script src="<?php echo get_template_directory_uri(); ?>/js/memos-embed.js"></script>
    <script>
        new MemosEmbed({
            serverUrl: 'http://你的NAS_IP:5230',
            container: '#memos-container',
            limit: 10,
            showDate: true,
            showTags: true,
            style: 'card'
        });
    </script>
<?php endif; ?>
```

---

## 配置示例

### 基础配置

```javascript
new MemosEmbed({
    serverUrl: 'http://192.168.1.100:5230',
    container: '#memos-container'
});
```

### 高级配置

```javascript
new MemosEmbed({
    serverUrl: 'http://192.168.1.100:5230',
    container: '#memos-container',
    limit: 15,
    creatorId: '1',
    showDate: true,
    showTags: true,
    style: 'timeline',
    loadingText: '正在加载备忘录...',
    errorText: '加载失败，请稍后重试',
    emptyText: '还没有公开备忘录',
    cacheTime: 7200000 // 2 小时
});
```

### 多实例配置

在页面不同位置显示不同内容：

```javascript
// 主页显示最新 5 条
new MemosEmbed({
    serverUrl: 'http://192.168.1.100:5230',
    container: '#latest-memos',
    limit: 5,
    style: 'card'
});

// 侧边栏显示标签云
new MemosEmbed({
    serverUrl: 'http://192.168.1.100:5230',
    container: '#sidebar-memos',
    limit: 3,
    style: 'list'
});
```

---

## 样式定制

### 使用 CSS 变量

```css
:root {
    --memos-primary-color: #0070a8;
    --memos-bg-color: #ffffff;
    --memos-border-color: #e8e8e8;
    --memos-text-color: #333333;
    --memos-meta-color: #888888;
}
```

### 完全自定义样式

```css
/* 覆盖默认样式 */
.memo-item {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 12px;
    padding: 20px;
}

.memo-content {
    font-size: 16px;
    line-height: 1.8;
}

.memo-tag {
    background: rgba(255, 255, 255, 0.2);
    color: white;
}
```

---

## API 参考

### MemosEmbed 类

#### 构造函数

```javascript
new MemosEmbed(config)
```

**配置参数：**

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| serverUrl | String | 必填 | Memos 服务器地址 |
| container | String | 必填 | 容器选择器 |
| limit | Number | 10 | 显示数量 |
| creatorId | String | '' | 创建者 ID |
| showDate | Boolean | true | 显示日期 |
| showTags | Boolean | true | 显示标签 |
| style | String | 'card' | 显示样式 |
| loadingText | String | '加载中...' | 加载提示文本 |
| errorText | String | '加载失败' | 错误提示文本 |
| emptyText | String | '暂无公开备忘录' | 空状态文本 |
| cacheKey | String | 'memos_cache' | 缓存键名 |
| cacheTime | Number | 3600000 | 缓存时间（毫秒） |

#### 方法

**refresh()**

清除缓存并重新加载数据：

```javascript
const widget = new MemosEmbed(config);

// 手动刷新
document.getElementById('refresh-btn').addEventListener('click', () => {
    widget.refresh();
});
```

---

## 高级用法

### 1. 与其他内容集成

在文章列表旁边显示备忘录：

```php
<!-- WordPress 主题模板 -->
<div class="content-wrapper">
    <main class="posts">
        <?php while (have_posts()): the_post(); ?>
            <article>
                <h2><?php the_title(); ?></h2>
                <?php the_content(); ?>
            </article>
        <?php endwhile; ?>
    </main>
    
    <aside class="sidebar">
        <div id="memos-sidebar"></div>
        <script src="/js/memos-embed.js"></script>
        <script>
            new MemosEmbed({
                serverUrl: 'http://192.168.1.100:5230',
                container: '#memos-sidebar',
                limit: 5,
                style: 'list'
            });
        </script>
    </aside>
</div>
```

### 2. 添加加载动画

```css
.memos-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 200px;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #0070a8;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
```

### 3. 实现懒加载

```javascript
// 当滚动到可视区域时加载
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            new MemosEmbed({
                serverUrl: 'http://192.168.1.100:5230',
                container: '#memos-container',
                limit: 10
            });
            observer.unobserve(entry.target);
        }
    });
});

observer.observe(document.getElementById('memos-container'));
```

---

## 故障排查

### 问题 1：显示"加载失败"

**检查清单：**
- [ ] Memos 服务器是否运行
- [ ] serverUrl 是否正确
- [ ] WordPress 服务器能否访问 Memos
- [ ] 浏览器控制台是否有错误

**调试方法：**

```javascript
// 在浏览器控制台测试
fetch('http://你的IP:5230/api/v1/memos?limit=1&visibility=PUBLIC')
    .then(r => r.json())
    .then(d => console.log(d))
    .catch(e => console.error(e));
```

### 问题 2：样式不正常

**解决方法：**
1. 检查浏览器控制台是否有 CSS 错误
2. 确认没有其他样式冲突
3. 使用浏览器开发者工具检查元素

### 问题 3：数据不更新

**原因：** 缓存时间未到

**解决：**
```javascript
// 手动清除缓存
localStorage.removeItem('memos_cache');
location.reload();
```

---

## 安全建议

1. **只公开 PUBLIC 备忘录**
   - 确保不要泄露私人内容

2. **配置 CORS**
   - 如果跨域访问，需要在 Memos 配置 CORS

3. **HTTPS**
   - 生产环境建议使用 HTTPS

4. **速率限制**
   - 缓存可以减少 API 请求

---

## 性能优化

### 1. 启用缓存

```javascript
new MemosEmbed({
    cacheTime: 7200000, // 2 小时
    cacheKey: 'memos_homepage'
});
```

### 2. 减少请求数量

```javascript
new MemosEmbed({
    limit: 5  // 只加载需要的数量
});
```

### 3. 使用 CDN

将 JS 文件托管到 CDN：

```html
<script src="https://你的CDN/memos-embed.js"></script>
```

---

## 示例页面

### 完整示例 HTML

```html
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的备忘录</title>
    <style>
        body {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            font-family: -apple-system, sans-serif;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .controls {
            margin-bottom: 20px;
            text-align: center;
        }
        
        button {
            background: #0070a8;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
        }
        
        button:hover {
            background: #005f8c;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <h1>公开备忘录</h1>
        <p>实时同步自 Memos</p>
    </div>
    
    <div class="controls">
        <button onclick="widget.refresh()">刷新</button>
    </div>
    
    <div id="memos-container"></div>
    
    <script src="memos-embed.js"></script>
    <script>
        const widget = new MemosEmbed({
            serverUrl: 'http://你的NAS_IP:5230',
            container: '#memos-container',
            limit: 10,
            style: 'card',
            showDate: true,
            showTags: true
        });
    </script>
</body>
</html>
```

---

## 常见问题

### Q: 可以在多个页面使用吗？

A: 可以！每个页面创建不同的实例：

```javascript
// 首页
new MemosEmbed({
    container: '#home-memos',
    limit: 5
});

// 侧边栏
new MemosEmbed({
    container: '#sidebar-memos',
    limit: 3
});
```

### Q: 支持 Markdown 吗？

A: 支持基础 Markdown：
- 粗体：`**文本**`
- 斜体：`*文本*`
- 代码：`` `代码` ``
- 链接：`[文本](URL)`

### Q: 如何显示图片？

A: 备忘录中的图片 URL 会自动显示。确保图片是公开可访问的。

### Q: 支持搜索吗？

A: 当前版本不支持搜索，可以在 Memos Web 界面搜索。

---

## 更新日志

### v1.0.0 (2026-05-02)
- 初始版本
- 支持卡片/列表/时间线样式
- 浏览器缓存
- 响应式设计

---

## 获取帮助

- GitHub Issues
- Memos 官方文档：https://usememos.com/docs
- WordPress 支持论坛

---

## 许可证

MIT License
