=== Memos Showcase ===
Contributors: niudashebg
Donate link: https://blog.wcld.top
Tags: memos, notes, showcase, memo, widget,备忘录,碎片想法
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

在 WordPress 中优雅地展示 Memos 公开备忘录，支持自定义样式和布局。

== Description ==

Memos Showcase 是一个功能强大的 WordPress 插件，让您可以在网站中展示 Memos 公开备忘录。通过简单的配置，即可将您的碎片想法、灵感记录优雅地展示给访客。

= 功能特性 =
* **多种显示样式** - 支持卡片式、列表式、时间线、极简四种样式
* **自定义颜色和样式** - 完全自定义主色调、辅助色、背景色、文字颜色等
* **灵活的布局选项** - 支持单列、网格、瀑布流布局
* **API 代理模式** - 通过 WordPress 后端转发请求，解决跨域问题
* **Access Token 支持** - 支持获取保护/私有备忘录
* **完整的卸载清理** - 卸载时自动清理所有设置数据
* **短代码支持** - 使用 `[memos_showcase]` 在任何位置显示
* **响应式设计** - 完美适配桌面和移动设备

= 使用场景 =
* 在首页展示最新的碎片想法
* 在侧边栏显示备忘录小工具
* 在文章中嵌入特定的备忘录列表
* 创建独立的备忘录展示页面

== Installation ==

1. 在 WordPress 后台导航到"插件" → "安装插件"
2. 搜索 "Memos Showcase" 并点击"安装"
3. 激活插件后，进入"后台 → Memos"进行配置
4. 填写您的 Memos 服务器地址（例如：http://192.168.1.100:5230）
5. 根据需要配置其他选项（创建者 ID、可见性、样式等）
6. 在文章或页面中使用短代码 `[memos_showcase]` 显示备忘录

== Frequently Asked Questions ==

= 如何配置 Memos 服务器？ =
进入 WordPress 后台 → Memos → 服务器设置，填写您的 Memos 服务器地址。例如：http://192.168.1.100:5230 或 https://memos.example.com

= 支持哪些 Memos 版本？ =
支持 Memos 0.22.0 及以上版本（API v1）。

= 如何解决跨域问题？ =
在"服务器设置"中启用"API 代理模式"，插件会通过 WordPress 后端转发请求，自动解决跨域问题。

= 如何获取 Access Token？ =
在 Memos 设置 → 账户 → Access Tokens 中创建新的令牌。复制令牌并粘贴到插件的"Access Token"字段中。

= 可以使用哪些短代码参数？ =
`[memos_showcase limit="5" style="card" layout="grid" columns="2"]`
可用参数：
* `limit` - 显示数量
* `style` - 显示样式 (card/list/timeline/minimal)
* `layout` - 布局方式 (default/grid/masonry)
* `columns` - 列数 (1/2/3)
* `show_date` - 显示日期 (true/false)
* `show_tags` - 显示标签 (true/false)

= 插件会收集用户数据吗？ =
不会。插件仅在您的 WordPress 网站和 Memos 服务器之间传输数据，不会收集或发送任何用户信息到第三方。

== Screenshots ==

1. 插件设置页面 - 服务器设置、显示设置、样式设置等
2. 卡片样式展示 - 精美的卡片式备忘录展示
3. 时间线样式展示 - 时间线风格的备忘录列表
4. 网格布局展示 - 多列网格布局效果

== Changelog ==

= 1.0.0 =
* 初始版本发布
* 支持四种显示样式（卡片、列表、时间线、极简）
* 支持三种布局方式（默认、网格、瀑布流）
* 完整的样式自定义功能（颜色、字体、圆角等）
* API 代理模式，解决跨域问题
* Access Token 认证支持
* 短代码支持，可在任何位置显示
* 完整的卸载清理功能
* 响应式设计，完美适配移动端

== Upgrade Notice ==

= 1.0.0 =
初始版本发布，欢迎使用！

== Developer Info ==

* **Author**: 牛大圣
* **Website**: https://blog.wcld.top
* **GitHub**: https://github.com/niudashebg/memos-showcase
* **Support**: https://github.com/niudashebg/memos-showcase/issues
