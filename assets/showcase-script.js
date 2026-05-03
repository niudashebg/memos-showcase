/**
 * Memos Showcase - 前端 JavaScript
 * 负责数据加载、缓存、渲染和交互
 */

(function(window, document) {
    'use strict';
    
    /**
     * MemosShowcase 类
     */
    class MemosShowcase {
        constructor(container, settings) {
            this.container = container;
            this.contentEl = container.querySelector('.memos-showcase-content');
            this.settings = this.parseSettings(settings);
            this.cacheKey = 'memos_showcase_cache_' + this.getCacheKey();
            
            this.init();
        }
        
        /**
         * 解析设置
         */
        parseSettings(settings) {
            return {
                serverUrl: settings.server_url || '',
                creatorId: settings.creator_id || '',
                visibility: settings.visibility || 'PUBLIC',
                limit: parseInt(settings.limit) || 10,
                showDate: parseInt(settings.show_date) === 1,
                showTags: parseInt(settings.show_tags) === 1,
                showAuthor: parseInt(settings.show_author) === 1,
                dateFormat: settings.date_format || 'Y-m-d H:i',
                contentLength: parseInt(settings.content_length) || 0,
                displayStyle: settings.display_style || settings.style || 'card',
                layout: settings.layout || 'default',
                columns: settings.columns || '1',
                primaryColor: settings.primary_color || '#0070a8',
                secondaryColor: settings.secondary_color || '#00bcd4',
                backgroundColor: settings.background_color || '#ffffff',
                textColor: settings.text_color || '#333333',
                metaColor: settings.meta_color || '#888888',
                borderRadius: parseInt(settings.border_radius) || 8,
                fontFamily: settings.font_family || '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                fontSize: parseInt(settings.font_size) || 15,
                lineHeight: parseFloat(settings.line_height) || 1.6,
                gap: parseInt(settings.gap) || 16,
                padding: parseInt(settings.padding) || 20,
                margin: parseInt(settings.margin) || 20,
                cacheTime: parseInt(settings.cache_time) || 3600,
                animation: settings.animation || 'fade',
                hoverEffect: settings.hover_effect || 'lift',
                enableLazyLoad: parseInt(settings.enable_lazy_load) === 1,
                loadingText: settings.loading_text || '加载中...',
                errorText: settings.error_text || '加载失败，请稍后重试',
                emptyText: settings.empty_text || '暂无公开备忘录'
            };
        }
        
        /**
         * 生成缓存键
         */
        getCacheKey() {
            return btoa(this.settings.serverUrl + this.settings.creatorId + this.settings.limit).replace(/[^a-zA-Z0-9]/g, '');
        }
        
        /**
         * 初始化
         */
        async init() {
            this.applyStyles();
            
            if (this.settings.enableLazyLoad) {
                this.initLazyLoad();
            } else {
                await this.loadMemos();
            }
        }
        
        /**
         * 应用 CSS 变量
         */
        applyStyles() {
            const s = this.settings;
            
            // 设置 CSS 变量
            this.container.style.setProperty('--memos-primary-color', s.primaryColor);
            this.container.style.setProperty('--memos-secondary-color', s.secondaryColor);
            this.container.style.setProperty('--memos-background-color', s.backgroundColor);
            this.container.style.setProperty('--memos-text-color', s.textColor);
            this.container.style.setProperty('--memos-meta-color', s.metaColor);
            this.container.style.setProperty('--memos-border-radius', s.borderRadius + 'px');
            this.container.style.setProperty('--memos-font-family', s.fontFamily);
            this.container.style.setProperty('--memos-font-size', s.fontSize + 'px');
            this.container.style.setProperty('--memos-line-height', s.lineHeight);
            this.container.style.setProperty('--memos-gap', s.gap + 'px');
            this.container.style.setProperty('--memos-padding', s.padding + 'px');
            this.container.style.setProperty('--memos-margin', s.margin + 'px');
            this.container.style.setProperty('--memos-columns', s.columns);
            
            // 添加动画类
            this.container.classList.add('animation-' + s.animation);
            
            // 添加悬停效果类
            this.container.classList.add('hover-' + s.hoverEffect);
        }
        
        /**
         * 懒加载初始化
         */
        initLazyLoad() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadMemos();
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });
            
            observer.observe(this.container);
        }
        
        /**
         * 加载备忘录
         */
        async loadMemos() {
            // 检查缓存
            const cached = this.getCache();
            if (cached) {
                this.render(cached);
                return;
            }
            
            try {
                const data = await this.fetchMemos();
                this.setCache(data);
                this.render(data);
            } catch (error) {
                console.error('Memos Showcase 错误:', error);
                this.showError(error.message);
            }
        }
        
        /**
         * 获取缓存
         */
        getCache() {
            try {
                const cached = localStorage.getItem(this.cacheKey);
                if (!cached) return null;
                
                const data = JSON.parse(cached);
                const now = Date.now();
                const cacheTime = this.settings.cacheTime * 1000;
                
                if (now - data.timestamp > cacheTime) {
                    localStorage.removeItem(this.cacheKey);
                    return null;
                }
                
                return data.data;
            } catch (e) {
                return null;
            }
        }
        
        /**
         * 设置缓存
         */
        setCache(data) {
            try {
                localStorage.setItem(this.cacheKey, JSON.stringify({
                    data: data,
                    timestamp: Date.now()
                }));
            } catch (e) {
                // 忽略存储错误（可能是空间不足）
            }
        }
        
        /**
         * 从 API 获取数据
         */
        async fetchMemos() {
            const s = this.settings;
            
            // 如果启用了 API 代理模式
            if (MEMOS_CONFIG && MEMOS_CONFIG.useApiProxy) {
                return await this.fetchViaProxy();
            }
            
            // 否则使用前端直接调用
            return await this.fetchDirect();
        }
        
        /**
         * 通过 WordPress 后端代理获取数据
         */
        async fetchViaProxy() {
            const formData = new FormData();
            formData.append('action', 'memos_get_memos');
            
            const response = await fetch(MEMOS_CONFIG.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.data?.message || '请求失败');
            }
            
            return result.data;
        }
        
        /**
         * 前端直接调用 Memos API
         */
        async fetchDirect() {
            const s = this.settings;
            let url = `${s.serverUrl}/api/v1/memos?`;
            
            const params = {
                limit: s.limit,
                visibility: s.visibility
            };
            
            if (s.creatorId) {
                params.creatorId = s.creatorId;
            }
            
            url += new URLSearchParams(params).toString();
            
            // 构建请求头
            const headers = {
                'Accept': 'application/json'
            };
            
            // 如果有 Access Token，添加到请求头
            if (MEMOS_CONFIG && MEMOS_CONFIG.accessToken) {
                headers['Authorization'] = 'Bearer ' + MEMOS_CONFIG.accessToken;
            }
            
            const response = await fetch(url, {
                method: 'GET',
                headers: headers
            });
            
            if (!response.ok) {
                if (response.status === 401) {
                    throw new Error('401 未授权 - 请检查 Access Token 是否正确');
                } else if (response.status === 403) {
                    throw new Error('403 禁止访问 - 可能需要配置 CORS 或启用 API 代理');
                }
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return await response.json();
        }
        
        /**
         * 渲染数据
         */
        render(data) {
            // 处理不同的 API 返回格式
            let memos = [];
            if (Array.isArray(data)) {
                memos = data;
            } else if (data && typeof data === 'object') {
                // 如果返回的是对象，尝试获取 memos 属性
                memos = data.memos || data.data || data.result || [];
                if (!Array.isArray(memos)) {
                    memos = [];
                }
            }
            
            if (memos.length === 0) {
                this.showEmpty();
                return;
            }
            
            const html = memos.map((memo, index) => this.renderMemo(memo, index)).join('');
            this.contentEl.innerHTML = html;
        }
        
        /**
         * 渲染单个备忘录
         */
        renderMemo(memo, index) {
            const s = this.settings;
            const content = this.formatContent(memo.content || '');
            const date = this.formatDate(memo.createTime || memo.updatedTs || memo.createdTs);
            const author = memo.creatorName || '';
            const tags = this.getTags(memo);
            
            // 构建 Memos 链接 - 从 memo.name 中提取 UID (格式: memos/xxx)
            let uid = '';
            if (memo.name) {
                uid = memo.name.replace(/^memos\//, '');
            } else if (memo.uid) {
                uid = memo.uid;
            } else if (memo.id) {
                uid = memo.id;
            }
            
            const memoUrl = s.serverUrl && uid
                ? `${s.serverUrl.replace(/\/$/, '')}/memos/${uid}` 
                : '#';
            
            const tagsHtml = tags.length > 0 
                ? `<div class="memo-tags">${tags.slice(0, 5).map(tag => 
                    `<span class="memo-tag">#${this.escapeHtml(tag)}</span>`
                ).join('')}</div>`
                : '';
            
            const authorHtml = author 
                ? `<span class="memo-author">
                    <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    ${this.escapeHtml(author)}
                </span>`
                : '';
            
            const dateHtml = s.showDate 
                ? `<span class="memo-date">
                    <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                    ${date}
                </span>`
                : '';
            
            // 添加动画延迟
            const delay = s.animation !== 'none' ? `style="animation-delay: ${index * 0.1}s"` : '';
            
            return `
                <a href="${memoUrl}" target="_blank" rel="noopener" class="memo-link">
                    <div class="memo-item" ${delay}>
                    <div class="memo-content">${content}</div>
                    <div class="memo-meta">
                        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                            ${dateHtml}
                            ${authorHtml}
                        </div>
                        ${tagsHtml}
                    </div>
                </div>
                </a>
            `;
        }
        
        /**
         * 格式化内容
         */
        formatContent(content) {
            const s = this.settings;
            
            // HTML 转义
            let formatted = content
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            
            // Markdown 转换
            formatted = formatted
                .replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>')
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.+?)\*/g, '<em>$1</em>')
                .replace(/~~(.+?)~~/g, '<del>$1</del>')
                .replace(/`(.+?)`/g, '<code>$1</code>')
                .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>')
                .replace(/^### (.+)$/gm, '<h3>$1</h3>')
                .replace(/^## (.+)$/gm, '<h3>$1</h3>')
                .replace(/^# (.+)$/gm, '<h3>$1</h3>')
                .replace(/^- (.+)$/gm, '<li>$1</li>')
                .replace(/\n/g, '<br>');
            
            // 内容长度限制
            if (s.contentLength > 0 && formatted.length > s.contentLength) {
                formatted = formatted.substring(0, s.contentLength) + '...';
            }
            
            return formatted;
        }
        
        /**
         * 格式化日期
         */
        formatDate(dateStr) {
            if (!dateStr) return '';
            
            const s = this.settings;
            
            // 相对时间
            if (s.dateFormat === 'relative') {
                return this.getRelativeTime(dateStr);
            }
            
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return dateStr;
            
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            
            return s.dateFormat
                .replace('Y', year)
                .replace('m', month)
                .replace('d', day)
                .replace('H', hours)
                .replace('i', minutes);
        }
        
        /**
         * 获取相对时间
         */
        getRelativeTime(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diff = now - date;
            
            const seconds = Math.floor(diff / 1000);
            const minutes = Math.floor(seconds / 60);
            const hours = Math.floor(minutes / 60);
            const days = Math.floor(hours / 24);
            
            if (seconds < 60) return '刚刚';
            if (minutes < 60) return `${minutes} 分钟前`;
            if (hours < 24) return `${hours} 小时前`;
            if (days < 7) return `${days} 天前`;
            
            return date.toLocaleDateString('zh-CN');
        }
        
        /**
         * 获取标签
         */
        getTags(memo) {
            // 尝试从不同位置获取标签
            const tags = memo.payload?.tags || memo.tags || [];
            
            // 从内容中提取标签
            if (tags.length === 0 && memo.content) {
                const matches = memo.content.match(/#[\w\u4e00-\u9fa5]+/g);
                if (matches) {
                    return matches.map(tag => tag.substring(1));
                }
            }
            
            return tags;
        }
        
        /**
         * HTML 转义
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        /**
         * 显示错误
         */
        showError(message) {
            this.contentEl.innerHTML = `
                <div class="memos-error">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    <p>${this.settings.errorText}</p>
                    <small>${message}</small>
                </div>
            `;
        }
        
        /**
         * 显示空状态
         */
        showEmpty() {
            this.contentEl.innerHTML = `
                <div class="memos-empty">
                    <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-7 12h-2v-2h2v2zm0-4h-2V6h2v4z"/></svg>
                    <p>${this.settings.emptyText}</p>
                </div>
            `;
        }
        
        /**
         * 刷新数据
         */
        refresh() {
            localStorage.removeItem(this.cacheKey);
            this.contentEl.innerHTML = `
                <div class="memos-loading">
                    <div class="loading-spinner"></div>
                    <p>${this.settings.loadingText}</p>
                </div>
            `;
            this.loadMemos();
        }
    }
    
    // 导出到全局
    window.MemosShowcase = MemosShowcase;
    
    // 自动初始化
    document.addEventListener('DOMContentLoaded', function() {
        const containers = document.querySelectorAll('.memos-showcase');
        containers.forEach(container => {
            if (!container.dataset.initialized) {
                const settings = JSON.parse(container.dataset.settings || '{}');
                new MemosShowcase(container, settings);
                container.dataset.initialized = 'true';
            }
        });
    });
    
})(window, document);
