<?php
/**
 * Plugin Name: Memos Showcase
 * Plugin URI: https://blog.wcld.top
 * Description: 在 WordPress 中优雅地展示 Memos 公开备忘录，支持自定义样式和布局
 * Version: 1.0.0
 * Author: 牛大圣
 * Author URI: https://blog.wcld.top
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: memos-showcase
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 插件常量
define('MEMOS_SHOWCASE_VERSION', '1.0.0');
define('MEMOS_SHOWCASE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MEMOS_SHOWCASE_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Memos Showcase 主类
 */
class Memos_Showcase {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->init_settings();
    }
    
    private function init_hooks() {
        // 激活、停用和卸载钩子
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('Memos_Showcase', 'uninstall'));
        
        // 后台菜单
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // 前端资源
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // 短代码
        add_shortcode('memos_showcase', array($this, 'shortcode_handler'));
        
        // 自动加载样式（当使用短代码时）
        add_filter('the_content', array($this, 'maybe_enqueue_styles'));
        
        // AJAX API 代理（解决跨域问题）
        add_action('wp_ajax_nopriv_memos_get_memos', array($this, 'ajax_get_memos'));
        add_action('wp_ajax_memos_get_memos', array($this, 'ajax_get_memos'));
    }
    
    /**
     * 初始化默认设置
     */
    private function init_settings() {
        $defaults = array(
            // 服务器设置
            'server_url' => '',
            'creator_id' => '',
            'visibility' => 'PUBLIC',
            'access_token' => '',
            'use_api_proxy' => 1,  // 默认启用 API 代理
            
            // 显示设置
            'limit' => 10,
            'show_date' => 1,
            'show_tags' => 1,
            'show_author' => 0,
            'date_format' => 'Y-m-d H:i',
            'content_length' => 0,
            
            // 样式设置
            'display_style' => 'card',
            'primary_color' => '#0070a8',
            'secondary_color' => '#00bcd4',
            'background_color' => '#ffffff',
            'text_color' => '#333333',
            'meta_color' => '#888888',
            'border_radius' => '8',
            'font_family' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
            'font_size' => '15',
            'line_height' => '1.6',
            
            // 布局设置
            'layout' => 'default',
            'columns' => '1',
            'gap' => '16',
            'padding' => '20',
            'margin' => '20',
            
            // 行为设置
            'cache_time' => 3600,
            'animation' => 'fade',
            'hover_effect' => 'lift',
            'enable_lazy_load' => 0,
            'use_api_proxy' => 1,  // 1=启用后端代理，0=前端直接调用
            'access_token' => '',  // API 访问令牌
            
            // 文本设置
            'loading_text' => '加载中...',
            'error_text' => '加载失败，请稍后重试',
            'empty_text' => '暂无公开备忘录',
            'title_text' => '',
            'subtitle_text' => '',
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option("memos_showcase_{$key}") === false) {
                add_option("memos_showcase_{$key}", $value);
            }
        }
    }
    
    /**
     * 插件激活
     */
    public function activate() {
        $this->init_settings();
        flush_rewrite_rules();
    }
    
    /**
     * 插件停用
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * 插件卸载 - 清理所有数据
     */
    public static function uninstall() {
        // 删除所有插件设置选项
        $options = array(
            'server_url', 'creator_id', 'visibility', 'access_token', 'use_api_proxy',
            'limit', 'show_date', 'show_tags', 'show_author', 'date_format', 'content_length',
            'display_style', 'primary_color', 'secondary_color', 'background_color', 
            'text_color', 'meta_color', 'border_radius', 'font_family', 'font_size', 'line_height',
            'layout', 'columns', 'gap', 'padding', 'margin',
            'cache_time', 'animation', 'hover_effect', 'enable_lazy_load',
            'loading_text', 'error_text', 'empty_text', 'title_text', 'subtitle_text'
        );
        
        foreach ($options as $option) {
            delete_option("memos_showcase_{$option}");
        }
        
        // 清除相关缓存
        delete_transient('memos_showcase_cache');
        
        // 如果需要，可以在这里添加其他清理工作
        // 例如：删除自定义数据库表、清理上传的文件等
    }
    
    /**
     * 添加后台菜单
     */
    public function add_admin_menu() {
        add_menu_page(
            'Memos Showcase',
            'Memos',
            'manage_options',
            'memos-showcase',
            array($this, 'render_settings_page'),
            'dashicons-format-aside',
            100
        );
    }
    
    /**
     * 注册设置
     */
    public function register_settings() {
        $settings = array(
            'server_url', 'creator_id', 'visibility', 'access_token', 'use_api_proxy',
            'limit', 'show_date', 'show_tags', 'show_author', 'date_format', 'content_length',
            'display_style', 'primary_color', 'secondary_color', 'background_color', 
            'text_color', 'meta_color', 'border_radius', 'font_family', 'font_size', 'line_height',
            'layout', 'columns', 'gap', 'padding', 'margin',
            'cache_time', 'animation', 'hover_effect', 'enable_lazy_load',
            'loading_text', 'error_text', 'empty_text', 'title_text', 'subtitle_text'
        );
        
        foreach ($settings as $setting) {
            register_setting('memos_showcase_options', "memos_showcase_{$setting}");
        }
    }
    
    /**
     * AJAX API 代理 - 获取 Memos 数据
     */
    public function ajax_get_memos() {
        // 验证 nonce（可选，提高安全性）
        // check_ajax_referer('memos_nonce', 'nonce');
        
        $server_url = get_option('memos_showcase_server_url', '');
        $creator_id = get_option('memos_showcase_creator_id', '');
        $visibility = get_option('memos_showcase_visibility', 'PUBLIC');
        $limit = intval(get_option('memos_showcase_limit', 10));
        $access_token = get_option('memos_showcase_access_token', '');
        
        if (empty($server_url)) {
            wp_send_json_error(array('message' => '请配置 Memos 服务器地址'));
            return;
        }
        
        // 构建 API URL
        $api_url = rtrim($server_url, '/') . '/api/v1/memos?';
        $params = array(
            'limit' => $limit,
            'visibility' => $visibility,
        );
        
        if (!empty($creator_id)) {
            $params['creatorId'] = $creator_id;
        }
        
        $api_url .= http_build_query($params);
        
        // 构建请求头
        $headers = array(
            'Accept' => 'application/json',
        );
        
        // 如果有 Access Token，添加到请求头
        if (!empty($access_token)) {
            $headers['Authorization'] = 'Bearer ' . $access_token;
        }
        
        // 发送请求
        $response = wp_remote_get($api_url, array(
            'timeout' => 15,
            'headers' => $headers,
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => 'JSON 解析错误'));
            return;
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * 渲染设置页面
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // 保存设置消息
        $saved = false;
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            $saved = true;
        }
        
        ?>
        <div class="wrap memos-showcase-admin">
            <h1><span class="dashicons dashicons-format-aside"></span> Memos Showcase 设置</h1>
            
            <?php if ($saved): ?>
            <div class="notice notice-success is-dismissible">
                <p>设置已保存！</p>
            </div>
            <?php endif; ?>
            
            <div class="memos-showcase-tabs">
                <button class="tab-button active" data-tab="server">服务器设置</button>
                <button class="tab-button" data-tab="display">显示设置</button>
                <button class="tab-button" data-tab="style">样式设置</button>
                <button class="tab-button" data-tab="layout">布局设置</button>
                <button class="tab-button" data-tab="behavior">行为设置</button>
                <button class="tab-button" data-tab="texts">文本设置</button>
                <button class="tab-button" data-tab="preview">预览</button>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('memos_showcase_options'); ?>
                
                <!-- 服务器设置 -->
                <div class="tab-content active" id="tab-server">
                    <h2>服务器设置</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Memos 服务器地址</th>
                            <td>
                                <input type="url" name="memos_showcase_server_url" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_server_url', '')); ?>" 
                                       class="regular-text" required placeholder="http://192.168.1.100:5230">
                                <p class="description">例如：http://192.168.1.100:5230 或 https://memos.example.com</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">创建者 ID（可选）</th>
                            <td>
                                <input type="text" name="memos_showcase_creator_id" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_creator_id', '')); ?>" 
                                       class="regular-text" placeholder="留空显示所有用户">
                                <p class="description">只获取指定用户的备忘录，留空显示所有用户</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">可见性</th>
                            <td>
                                <select name="memos_showcase_visibility">
                                    <option value="PUBLIC" <?php selected(get_option('memos_showcase_visibility', 'PUBLIC'), 'PUBLIC'); ?>>公开 (PUBLIC)</option>
                                    <option value="PROTECTED" <?php selected(get_option('memos_showcase_visibility', 'PUBLIC'), 'PROTECTED'); ?>>保护 (PROTECTED)</option>
                                    <option value="PRIVATE" <?php selected(get_option('memos_showcase_visibility', 'PUBLIC'), 'PRIVATE'); ?>>私有 (PRIVATE)</option>
                                </select>
                                <p class="description">只显示公开备忘录更安全</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Access Token（可选）</th>
                            <td>
                                <input type="text" name="memos_showcase_access_token" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_access_token', '')); ?>" 
                                       class="regular-text" placeholder="留空=只获取公开备忘录">
                                <p class="description">在 Memos 设置 → 账户 → Access Tokens 中创建，用于获取保护/私有备忘录</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">API 代理模式</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="memos_showcase_use_api_proxy" 
                                           value="1" <?php checked(get_option('memos_showcase_use_api_proxy', 1), 1); ?>>
                                    启用后端 API 代理（推荐）
                                </label>
                                <p class="description">
                                    启用：通过 WordPress 后端转发请求，解决跨域问题，更安全<br>
                                    禁用：前端直接调用 Memos API，可能需要配置 CORS
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- 显示设置 -->
                <div class="tab-content" id="tab-display">
                    <h2>显示设置</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">显示数量</th>
                            <td>
                                <input type="number" name="memos_showcase_limit" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_limit', 10)); ?>" 
                                       min="1" max="100" class="small-text">
                                <p class="description">每次加载的备忘录数量</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">显示选项</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="memos_showcase_show_date" 
                                           value="1" <?php checked(get_option('memos_showcase_show_date', 1), 1); ?>>
                                    显示日期
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="memos_showcase_show_tags" 
                                           value="1" <?php checked(get_option('memos_showcase_show_tags', 1), 1); ?>>
                                    显示标签
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="memos_showcase_show_author" 
                                           value="1" <?php checked(get_option('memos_showcase_show_author', 0), 1); ?>>
                                    显示作者
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">日期格式</th>
                            <td>
                                <select name="memos_showcase_date_format">
                                    <option value="Y-m-d H:i" <?php selected(get_option('memos_showcase_date_format', 'Y-m-d H:i'), 'Y-m-d H:i'); ?>>2026-05-02 15:30</option>
                                    <option value="Y/m/d" <?php selected(get_option('memos_showcase_date_format', 'Y-m-d H:i'), 'Y/m/d'); ?>>2026/05/02</option>
                                    <option value="m-d H:i" <?php selected(get_option('memos_showcase_date_format', 'Y-m-d H:i'), 'm-d H:i'); ?>>05-02 15:30</option>
                                    <option value="relative" <?php selected(get_option('memos_showcase_date_format', 'Y-m-d H:i'), 'relative'); ?>>相对时间 (3 小时前)</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">内容长度限制</th>
                            <td>
                                <input type="number" name="memos_showcase_content_length" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_content_length', 0)); ?>" 
                                       min="0" class="small-text" placeholder="0 = 不限制">
                                <p class="description">0 表示不限制，设置数字后超出部分会显示"..."</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- 样式设置 -->
                <div class="tab-content" id="tab-style">
                    <h2>样式设置</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">显示样式</th>
                            <td>
                                <select name="memos_showcase_display_style">
                                    <option value="card" <?php selected(get_option('memos_showcase_display_style', 'card'), 'card'); ?>>卡片式</option>
                                    <option value="list" <?php selected(get_option('memos_showcase_display_style', 'card'), 'list'); ?>>列表式</option>
                                    <option value="timeline" <?php selected(get_option('memos_showcase_display_style', 'card'), 'timeline'); ?>>时间线</option>
                                    <option value="minimal" <?php selected(get_option('memos_showcase_display_style', 'card'), 'minimal'); ?>>极简</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">主色调</th>
                            <td>
                                <input type="color" name="memos_showcase_primary_color" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_primary_color', '#0070a8')); ?>" 
                                       class="color-picker">
                                <input type="text" name="memos_showcase_primary_color_text" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_primary_color', '#0070a8')); ?>" 
                                       class="small-text color-text">
                                <p class="description">用于链接、标签、图标等</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">辅助色</th>
                            <td>
                                <input type="color" name="memos_showcase_secondary_color" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_secondary_color', '#00bcd4')); ?>" 
                                       class="color-picker">
                                <input type="text" name="memos_showcase_secondary_color_text" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_secondary_color', '#00bcd4')); ?>" 
                                       class="small-text color-text">
                                <p class="description">用于渐变、高亮等</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">背景颜色</th>
                            <td>
                                <input type="color" name="memos_showcase_background_color" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_background_color', '#ffffff')); ?>" 
                                       class="color-picker">
                                <input type="text" name="memos_showcase_background_color_text" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_background_color', '#ffffff')); ?>" 
                                       class="small-text color-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">文字颜色</th>
                            <td>
                                <input type="color" name="memos_showcase_text_color" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_text_color', '#333333')); ?>" 
                                       class="color-picker">
                                <input type="text" name="memos_showcase_text_color_text" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_text_color', '#333333')); ?>" 
                                       class="small-text color-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">元信息颜色</th>
                            <td>
                                <input type="color" name="memos_showcase_meta_color" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_meta_color', '#888888')); ?>" 
                                       class="color-picker">
                                <input type="text" name="memos_showcase_meta_color_text" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_meta_color', '#888888')); ?>" 
                                       class="small-text color-text">
                                <p class="description">日期、标签等元信息的颜色</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">圆角大小</th>
                            <td>
                                <input type="number" name="memos_showcase_border_radius" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_border_radius', 8)); ?>" 
                                       min="0" max="50" class="small-text">
                                <span>px</span>
                                <p class="description">卡片圆角大小，0 为直角</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">字体</th>
                            <td>
                                <select name="memos_showcase_font_family">
                                    <option value="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" <?php selected(get_option('memos_showcase_font_family', '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'), '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'); ?>>系统默认</option>
                                    <option value="'Microsoft YaHei', sans-serif" <?php selected(get_option('memos_showcase_font_family', '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'), "'Microsoft YaHei', sans-serif"); ?>>微软雅黑</option>
                                    <option value="'PingFang SC', sans-serif" <?php selected(get_option('memos_showcase_font_family', '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'), "'PingFang SC', sans-serif"); ?>>苹方</option>
                                    <option value="'Noto Sans SC', sans-serif" <?php selected(get_option('memos_showcase_font_family', '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'), "'Noto Sans SC', sans-serif"); ?>>思源黑体</option>
                                    <option value="Georgia, serif" <?php selected(get_option('memos_showcase_font_family', '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'), 'Georgia, serif'); ?>>衬线体</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">字体大小</th>
                            <td>
                                <input type="number" name="memos_showcase_font_size" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_font_size', 15)); ?>" 
                                       min="10" max="24" class="small-text">
                                <span>px</span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">行高</th>
                            <td>
                                <input type="number" name="memos_showcase_line_height" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_line_height', 1.6)); ?>" 
                                       min="1" max="2.5" step="0.1" class="small-text">
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- 布局设置 -->
                <div class="tab-content" id="tab-layout">
                    <h2>布局设置</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">布局方式</th>
                            <td>
                                <select name="memos_showcase_layout">
                                    <option value="default" <?php selected(get_option('memos_showcase_layout', 'default'), 'default'); ?>>默认（单列）</option>
                                    <option value="grid" <?php selected(get_option('memos_showcase_layout', 'default'), 'grid'); ?>>网格</option>
                                    <option value="masonry" <?php selected(get_option('memos_showcase_layout', 'default'), 'masonry'); ?>>瀑布流</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">列数</th>
                            <td>
                                <select name="memos_showcase_columns">
                                    <option value="1" <?php selected(get_option('memos_showcase_columns', '1'), '1'); ?>>1 列</option>
                                    <option value="2" <?php selected(get_option('memos_showcase_columns', '1'), '2'); ?>>2 列</option>
                                    <option value="3" <?php selected(get_option('memos_showcase_columns', '1'), '3'); ?>>3 列</option>
                                </select>
                                <p class="description">网格或瀑布流布局时的列数</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">间距</th>
                            <td>
                                <input type="number" name="memos_showcase_gap" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_gap', 16)); ?>" 
                                       min="0" max="50" class="small-text">
                                <span>px</span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">内边距</th>
                            <td>
                                <input type="number" name="memos_showcase_padding" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_padding', 20)); ?>" 
                                       min="0" max="50" class="small-text">
                                <span>px</span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">外边距</th>
                            <td>
                                <input type="number" name="memos_showcase_margin" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_margin', 20)); ?>" 
                                       min="0" max="50" class="small-text">
                                <span>px</span>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- 行为设置 -->
                <div class="tab-content" id="tab-behavior">
                    <h2>行为设置</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">缓存时间</th>
                            <td>
                                <input type="number" name="memos_showcase_cache_time" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_cache_time', 3600)); ?>" 
                                       min="60" max="86400" class="small-text">
                                <span>秒</span>
                                <p class="description">默认 3600 秒（1 小时），减少 API 请求</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">加载动画</th>
                            <td>
                                <select name="memos_showcase_animation">
                                    <option value="fade" <?php selected(get_option('memos_showcase_animation', 'fade'), 'fade'); ?>>淡入</option>
                                    <option value="slide" <?php selected(get_option('memos_showcase_animation', 'fade'), 'slide'); ?>>滑入</option>
                                    <option value="none" <?php selected(get_option('memos_showcase_animation', 'fade'), 'none'); ?>>无</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">悬停效果</th>
                            <td>
                                <select name="memos_showcase_hover_effect">
                                    <option value="lift" <?php selected(get_option('memos_showcase_hover_effect', 'lift'), 'lift'); ?>>上浮</option>
                                    <option value="shadow" <?php selected(get_option('memos_showcase_hover_effect', 'lift'), 'shadow'); ?>>阴影</option>
                                    <option value="border" <?php selected(get_option('memos_showcase_hover_effect', 'lift'), 'border'); ?>>边框高亮</option>
                                    <option value="none" <?php selected(get_option('memos_showcase_hover_effect', 'lift'), 'none'); ?>>无</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">启用懒加载</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="memos_showcase_enable_lazy_load" 
                                           value="1" <?php checked(get_option('memos_showcase_enable_lazy_load', 0), 1); ?>>
                                    滚动到可视区域时才加载
                                </label>
                                <p class="description">提高页面加载性能</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- 文本设置 -->
                <div class="tab-content" id="tab-texts">
                    <h2>文本设置</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">标题（可选）</th>
                            <td>
                                <input type="text" name="memos_showcase_title_text" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_title_text', '')); ?>" 
                                       class="regular-text" placeholder="留空不显示标题">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">副标题（可选）</th>
                            <td>
                                <input type="text" name="memos_showcase_subtitle_text" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_subtitle_text', '')); ?>" 
                                       class="regular-text" placeholder="留空不显示副标题">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">加载提示文字</th>
                            <td>
                                <input type="text" name="memos_showcase_loading_text" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_loading_text', '加载中...')); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">错误提示文字</th>
                            <td>
                                <input type="text" name="memos_showcase_error_text" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_error_text', '加载失败，请稍后重试')); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">空状态提示文字</th>
                            <td>
                                <input type="text" name="memos_showcase_empty_text" 
                                       value="<?php echo esc_attr(get_option('memos_showcase_empty_text', '暂无公开备忘录')); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- 预览 -->
                <div class="tab-content" id="tab-preview">
                    <h2>实时预览</h2>
                    <div class="memos-showcase-preview">
                        <div class="preview-header">
                            <h3>预览效果</h3>
                            <button type="button" class="button preview-refresh">刷新预览</button>
                        </div>
                        <div id="memos-preview-container" class="preview-container">
                            <div class="preview-loading">
                                <i class="dashicons dashicons-update"></i>
                                正在加载预览...
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="submit-box">
                    <?php submit_button('保存设置', 'primary', 'submit', false); ?>
                    <button type="button" class="button reset-settings">重置为默认</button>
                </div>
            </form>
            
            <!-- 使用说明 -->
            <div class="memos-showcase-usage">
                <h2>使用说明</h2>
                <div class="usage-grid">
                    <div class="usage-card">
                        <h3>在文章中使用</h3>
                        <pre><code>[memos_showcase]</code></pre>
                        <p>在文章中插入短代码即可显示备忘录</p>
                    </div>
                    <div class="usage-card">
                        <h3>自定义参数</h3>
                        <pre><code>[memos_showcase limit="5" style="card"]</code></pre>
                        <p>可以在短代码中覆盖全局设置</p>
                    </div>
                    <div class="usage-card">
                        <h3>可用参数</h3>
                        <ul>
                            <li><code>limit</code> - 显示数量</li>
                            <li><code>style</code> - 显示样式 (card/list/timeline/minimal)</li>
                            <li><code>layout</code> - 布局方式 (default/grid/masonry)</li>
                            <li><code>columns</code> - 列数 (1/2/3)</li>
                            <li><code>show_date</code> - 显示日期 (true/false)</li>
                            <li><code>show_tags</code> - 显示标签 (true/false)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        /* 后台设置页面样式 */
        .memos-showcase-admin {
            max-width: 1200px;
        }
        
        .memos-showcase-admin h1 {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .memos-showcase-admin h1 .dashicons {
            font-size: 28px;
            width: 28px;
            height: 28px;
        }
        
        .memos-showcase-tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
            flex-wrap: wrap;
        }
        
        .memos-showcase-tabs .tab-button {
            padding: 10px 20px;
            border: none;
            background: #f0f0f1;
            cursor: pointer;
            font-size: 14px;
            border-radius: 4px 4px 0 0;
            transition: all 0.2s;
        }
        
        .memos-showcase-tabs .tab-button:hover {
            background: #e0e0e0;
        }
        
        .memos-showcase-tabs .tab-button.active {
            background: #fff;
            border-bottom: 2px solid #2271b1;
            color: #2271b1;
            font-weight: 600;
        }
        
        .tab-content {
            display: none;
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-top: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .tab-content h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .color-picker {
            width: 50px;
            height: 30px;
            padding: 0;
            border: 1px solid #ddd;
            cursor: pointer;
        }
        
        .color-text {
            margin-left: 10px;
            width: 80px;
        }
        
        .submit-box {
            padding: 15px 0;
            border-top: 1px solid #eee;
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        
        .reset-settings {
            background: #f0f0f1;
            border: 1px solid #ccc;
            color: #555;
        }
        
        .reset-settings:hover {
            background: #e0e0e0;
        }
        
        .memos-showcase-usage {
            margin-top: 40px;
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
        }
        
        .usage-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .usage-card {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #eee;
        }
        
        .usage-card h3 {
            margin-top: 0;
            color: #2271b1;
        }
        
        .usage-card pre {
            background: #fff;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
            overflow-x: auto;
        }
        
        .usage-card code {
            color: #d63384;
        }
        
        .usage-card ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .usage-card li {
            margin-bottom: 5px;
        }
        
        /* 预览区域 */
        .memos-showcase-preview {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            min-height: 200px;
        }
        
        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .preview-header h3 {
            margin: 0;
        }
        
        .preview-container {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            min-height: 150px;
        }
        
        .preview-loading {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .preview-loading .dashicons {
            font-size: 24px;
            width: 24px;
            height: 24px;
            animation: rotate 1s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* 响应式 */
        @media (max-width: 782px) {
            .memos-showcase-tabs {
                flex-direction: column;
            }
            
            .memos-showcase-tabs .tab-button {
                border-radius: 4px;
                text-align: left;
            }
            
            .memos-showcase-tabs .tab-button.active {
                border-bottom: none;
                border-left: 3px solid #2271b1;
            }
            
            .usage-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>
        
        <script>
        // 标签切换
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab-button');
            const contents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const target = this.dataset.tab;
                    
                    tabs.forEach(t => t.classList.remove('active'));
                    contents.forEach(c => c.classList.remove('active'));
                    
                    this.classList.add('active');
                    document.getElementById('tab-' + target).classList.add('active');
                    
                    // 如果切换到预览标签，加载预览
                    if (target === 'preview') {
                        loadPreview();
                    }
                });
            });
            
            // 颜色选择器同步
            const colorPickers = document.querySelectorAll('.color-picker');
            colorPickers.forEach(picker => {
                const textInput = picker.nextElementSibling;
                
                picker.addEventListener('input', function() {
                    textInput.value = this.value;
                });
                
                textInput.addEventListener('input', function() {
                    if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                        picker.value = this.value;
                    }
                });
            });
            
            // 重置按钮
            const resetBtn = document.querySelector('.reset-settings');
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    if (confirm('确定要重置所有设置为默认值吗？')) {
                        // 这里可以实现重置逻辑
                        alert('重置功能需要在服务器端实现');
                    }
                });
            }
        });
        
        // 加载预览
        function loadPreview() {
            const container = document.getElementById('memos-preview-container');
            const serverUrl = document.querySelector('[name="memos_showcase_server_url"]').value;
            
            if (!serverUrl) {
                container.innerHTML = '<div class="preview-error">请先配置 Memos 服务器地址</div>';
                return;
            }
            
            container.innerHTML = '<div class="preview-loading"><i class="dashicons dashicons-update"></i> 正在加载预览...</div>';
            
            // 模拟预览（实际应该从服务器获取）
            setTimeout(() => {
                container.innerHTML = `
                    <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #eee; margin-bottom: 16px;">
                        <div style="font-size: 15px; line-height: 1.6; margin-bottom: 12px;">
                            这是一条预览备忘录内容，实际内容将从你的 Memos 服务器加载...
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 12px; color: #888;">
                            <span> 刚刚</span>
                            <span><span style="background: #f0f7ff; color: #0070a8; padding: 2px 8px; border-radius: 12px;">#预览</span></span>
                        </div>
                    </div>
                    <p style="text-align: center; color: #999; font-size: 13px;">保存设置后，这里将显示真实的备忘录内容</p>
                `;
            }, 500);
        }
        </script>
        <?php
    }
    
    /**
     * 加载前端资源
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'memos-showcase-style',
            MEMOS_SHOWCASE_PLUGIN_URL . 'assets/showcase-style.css',
            array(),
            MEMOS_SHOWCASE_VERSION
        );
        
        wp_enqueue_script(
            'memos-showcase-script',
            MEMOS_SHOWCASE_PLUGIN_URL . 'assets/showcase-script.js',
            array(),
            MEMOS_SHOWCASE_VERSION,
            true
        );
        
        // 传递配置到前端
        wp_localize_script('memos-showcase-script', 'MEMOS_CONFIG', array(
            'serverUrl' => get_option('memos_showcase_server_url', ''),
            'creatorId' => get_option('memos_showcase_creator_id', ''),
            'visibility' => get_option('memos_showcase_visibility', 'PUBLIC'),
            'accessToken' => get_option('memos_showcase_access_token', ''),
            'useApiProxy' => (bool)get_option('memos_showcase_use_api_proxy', 1),
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ));
    }
    
    /**
     * 短代码处理器
     */
    public function shortcode_handler($atts) {
        $atts = shortcode_atts(array(
            'limit' => get_option('memos_showcase_limit', 10),
            'style' => get_option('memos_showcase_display_style', 'card'),
            'layout' => get_option('memos_showcase_layout', 'default'),
            'columns' => get_option('memos_showcase_columns', '1'),
            'show_date' => get_option('memos_showcase_show_date', 1),
            'show_tags' => get_option('memos_showcase_show_tags', 1),
        ), $atts, 'memos_showcase');
        
        // 生成唯一 ID
        $unique_id = 'memos-' . uniqid();
        
        // 获取设置
        $settings = $this->get_settings();
        
        // 构建 HTML
        ob_start();
        ?>
        <div id="<?php echo esc_attr($unique_id); ?>" 
             class="memos-showcase memos-showcase-<?php echo esc_attr($atts['style']); ?> memos-layout-<?php echo esc_attr($atts['layout']); ?>"
             data-settings='<?php echo esc_attr(json_encode(array_merge($settings, $atts))); ?>'>
            <?php if ($settings['title_text']): ?>
            <div class="memos-showcase-header">
                <h3 class="memos-showcase-title"><?php echo esc_html($settings['title_text']); ?></h3>
                <?php if ($settings['subtitle_text']): ?>
                <p class="memos-showcase-subtitle"><?php echo esc_html($settings['subtitle_text']); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="memos-showcase-content">
                <div class="memos-loading">
                    <div class="loading-spinner"></div>
                    <p><?php echo esc_html($settings['loading_text']); ?></p>
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('<?php echo $unique_id; ?>');
            if (container) {
                const settings = JSON.parse(container.dataset.settings);
                new MemosShowcase(container, settings);
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * 获取所有设置
     */
    private function get_settings() {
        $settings = array();
        $keys = array(
            'server_url', 'creator_id', 'visibility',
            'limit', 'show_date', 'show_tags', 'show_author', 'date_format', 'content_length',
            'display_style', 'primary_color', 'secondary_color', 'background_color', 
            'text_color', 'meta_color', 'border_radius', 'font_family', 'font_size', 'line_height',
            'layout', 'columns', 'gap', 'padding', 'margin',
            'cache_time', 'animation', 'hover_effect', 'enable_lazy_load',
            'loading_text', 'error_text', 'empty_text', 'title_text', 'subtitle_text'
        );
        
        foreach ($keys as $key) {
            $settings[$key] = get_option("memos_showcase_{$key}");
        }
        
        return $settings;
    }
    
    /**
     * 当使用短代码时加载样式
     */
    public function maybe_enqueue_styles($content) {
        if (has_shortcode($content, 'memos_showcase')) {
            wp_enqueue_style('memos-showcase-style');
            wp_enqueue_script('memos-showcase-script');
        }
        return $content;
    }
}

// 初始化插件
add_action('plugins_loaded', function() {
    Memos_Showcase::get_instance();
});
