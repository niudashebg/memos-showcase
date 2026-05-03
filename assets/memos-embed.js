// 纯 JavaScript 方案 - 直接在 WordPress 主题中使用
// 无需安装插件

class MemosEmbed {
    constructor(config = {}) {
        this.config = {
            serverUrl: config.serverUrl || 'http://你的NAS_IP:5230',
            container: config.container || '#memos-container',
            limit: config.limit || 10,
            creatorId: config.creatorId || '',
            showDate: config.showDate !== false,
            showTags: config.showTags !== false,
            style: config.style || 'card', // card, list, timeline
            loadingText: config.loadingText || '加载中...',
            errorText: config.errorText || '加载失败',
            emptyText: config.emptyText || '暂无公开备忘录',
            cacheKey: config.cacheKey || 'memos_cache',
            cacheTime: config.cacheTime || 3600000, // 1小时
            ...config
        };
        
        this.init();
    }
    
    init() {
        this.container = document.querySelector(this.config.container);
        if (!this.container) {
            console.error(`MemosEmbed: 找不到容器 ${this.config.container}`);
            return;
        }
        
        this.loadMemos();
    }
    
    // 缓存管理
    getCache() {
        try {
            const cached = localStorage.getItem(this.config.cacheKey);
            if (!cached) return null;
            
            const data = JSON.parse(cached);
            if (Date.now() - data.timestamp > this.config.cacheTime) {
                localStorage.removeItem(this.config.cacheKey);
                return null;
            }
            return data.data;
        } catch (e) {
            return null;
        }
    }
    
    setCache(data) {
        try {
            localStorage.setItem(this.config.cacheKey, JSON.stringify({
                data: data,
                timestamp: Date.now()
            }));
        } catch (e) {
            // 忽略存储错误
        }
    }
    
    // 加载备忘录
    async loadMemos() {
        // 显示加载状态
        this.container.innerHTML = `
            <div class="memos-loading">
                <div class="loading-spinner"></div>
                <p>${this.config.loadingText}</p>
            </div>
        `;
        
        try {
            // 检查缓存
            const cached = this.getCache();
            if (cached) {
                this.render(cached);
                return;
            }
            
            // 构建 API URL
            let url = `${this.config.serverUrl}/api/v1/memos?`;
            const params = {
                limit: this.config.limit,
                visibility: 'PUBLIC'
            };
            
            if (this.config.creatorId) {
                params.creatorId = this.config.creatorId;
            }
            
            url += new URLSearchParams(params).toString();
            
            // 获取数据
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            // 缓存数据
            this.setCache(data);
            
            // 渲染
            this.render(data);
            
        } catch (error) {
            console.error('MemosEmbed 错误:', error);
            this.container.innerHTML = `
                <div class="memos-error">
                    <p>${this.config.errorText}</p>
                    <small>${error.message}</small>
                </div>
            `;
        }
    }
    
    // 渲染备忘录
    render(data) {
        // 处理不同的 API 返回格式
        let memos = [];
        if (Array.isArray(data)) {
            memos = data;
        } else if (data && typeof data === 'object') {
            memos = data.memos || data.data || data.result || [];
            if (!Array.isArray(memos)) {
                memos = [];
            }
        }
        
        if (memos.length === 0) {
            this.container.innerHTML = `<div class="memos-empty">${this.config.emptyText}</div>`;
            return;
        }
        
        const html = memos.map(memo => this.renderMemo(memo)).join('');
        this.container.innerHTML = `<div class="memos-widget memos-${this.config.style}">${html}</div>`;
        
        // 添加样式
        this.addStyles();
    }
    
    // 渲染单个备忘录
    renderMemo(memo) {
        const content = this.formatContent(memo.content || '');
        const date = this.formatDate(memo.createTime || memo.updatedTs);
        const tags = memo.payload?.tags || memo.tags || [];
        
        // 构建 Memos 链接 - 从 memo.name 中提取 UID (格式: memos/xxx)
        let uid = '';
        if (memo.name) {
            uid = memo.name.replace(/^memos\//, '');
        } else if (memo.uid) {
            uid = memo.uid;
        } else if (memo.id) {
            uid = memo.id;
        }
        
        const memoUrl = this.config.serverUrl && uid
            ? `${this.config.serverUrl.replace(/\/$/, '')}/memos/${uid}` 
            : '#';
        
        const tagsHtml = tags.length > 0 
            ? `<div class="memo-tags">${tags.map(tag => `<span class="memo-tag">#${this.escapeHtml(tag)}</span>`).join('')}</div>`
            : '';
        
        return `
            <a href="${memoUrl}" target="_blank" rel="noopener" class="memo-link">
                <div class="memo-item" data-id="${memo.id}">
                    <div class="memo-content">${content}</div>
                    <div class="memo-meta">
                        ${this.config.showDate ? `<time class="memo-date">${date}</time>` : ''}
                        ${this.config.showTags ? tagsHtml : ''}
                    </div>
                </div>
            </a>
        `;
    }
    
    // 格式化内容
    formatContent(content) {
        return content
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.+?)\*/g, '<em>$1</em>')
            .replace(/`(.+?)`/g, '<code>$1</code>')
            .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>')
            .replace(/\n/g, '<br>');
    }
    
    // 格式化日期
    formatDate(dateStr) {
        if (!dateStr) return '';
        
        try {
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return dateStr;
            
            return date.toLocaleString('zh-CN', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return dateStr;
        }
    }
    
    // HTML 转义
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // 添加样式
    addStyles() {
        if (document.getElementById('memos-embed-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'memos-embed-styles';
        style.textContent = this.getCSS();
        document.head.appendChild(style);
    }
    
    // CSS 样式
    getCSS() {
        const style = this.config.style;
        
        return `
            .memos-widget {
                max-width: 100%;
                margin: 20px 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            
            .memo-item {
                background: #fff;
                border: 1px solid #e8e8e8;
                border-radius: 8px;
                padding: 16px;
                margin-bottom: 12px;
                transition: all 0.2s ease;
            }
            
            .memo-item:hover {
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                border-color: #0070a8;
            }
            
            .memo-content {
                line-height: 1.6;
                margin-bottom: 8px;
                word-wrap: break-word;
            }
            
            .memo-content a {
                color: #0070a8;
                text-decoration: none;
            }
            
            .memo-content a:hover {
                text-decoration: underline;
            }
            
            .memo-content code {
                background: #f5f5f5;
                padding: 2px 6px;
                border-radius: 3px;
                font-family: monospace;
                font-size: 0.9em;
            }
            
            .memo-meta {
                display: flex;
                align-items: center;
                gap: 12px;
                font-size: 12px;
                color: #888;
            }
            
            .memo-date {
                color: #999;
            }
            
            .memo-tags {
                display: flex;
                gap: 6px;
                flex-wrap: wrap;
            }
            
            .memo-tag {
                background: #f0f7ff;
                color: #0070a8;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 500;
            }
            
            /* 加载状态 */
            .memos-loading {
                text-align: center;
                padding: 40px 20px;
                color: #999;
            }
            
            .loading-spinner {
                display: inline-block;
                width: 24px;
                height: 24px;
                border: 2px solid #e8e8e8;
                border-top-color: #0070a8;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
                margin-bottom: 12px;
            }
            
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            
            /* 错误状态 */
            .memos-error {
                background: #fff3f3;
                border: 1px solid #ffcdd2;
                color: #d32f2f;
                padding: 16px;
                border-radius: 8px;
                text-align: center;
            }
            
            .memos-error small {
                display: block;
                margin-top: 8px;
                opacity: 0.8;
            }
            
            /* 空状态 */
            .memos-empty {
                background: #f9f9f9;
                padding: 30px;
                text-align: center;
                color: #999;
                border-radius: 8px;
            }
            
            /* 时间线样式 */
            .memos-timeline {
                position: relative;
                padding-left: 24px;
            }
            
            .memos-timeline::before {
                content: '';
                position: absolute;
                left: 4px;
                top: 0;
                bottom: 0;
                width: 2px;
                background: linear-gradient(to bottom, #0070a8, #00bcd4);
            }
            
            .memos-timeline .memo-item {
                position: relative;
                margin-left: 12px;
            }
            
            .memos-timeline .memo-item::before {
                content: '';
                position: absolute;
                left: -20px;
                top: 20px;
                width: 10px;
                height: 10px;
                border-radius: 50%;
                background: #0070a8;
                border: 2px solid #fff;
                box-shadow: 0 0 0 2px #0070a8;
            }
            
            /* 列表样式 */
            .memos-list .memo-item {
                border-radius: 0;
                border-left: none;
                border-right: none;
                margin-bottom: 0;
            }
            
            .memos-list .memo-item:first-child {
                border-top: none;
            }
            
            .memos-list .memo-item:last-child {
                border-bottom: none;
            }
            
            /* 响应式 */
            @media (max-width: 600px) {
                .memo-item {
                    padding: 12px;
                }
                
                .memo-meta {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 6px;
                }
            }
        `;
    }
    
    // 刷新数据
    refresh() {
        localStorage.removeItem(this.config.cacheKey);
        this.loadMemos();
    }
}

// 导出
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MemosEmbed;
}
