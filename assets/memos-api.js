// Memos API 集成 - JavaScript 版本
// 用于在 WordPress 主题中直接调用

(function() {
    'use strict';
    
    // 配置
    const MEMOS_CONFIG = {
        serverUrl: 'http://你的NAS_IP:5230', // 修改为你的 Memos 服务器地址
        creatorId: '', // 可选：指定用户 ID
        limit: 10,
        cacheTime: 3600000, // 1 小时（毫秒）
    };
    
    // 缓存管理
    const Cache = {
        key: 'memos_public_memos',
        
        get() {
            const cached = localStorage.getItem(this.key);
            if (!cached) return null;
            
            const data = JSON.parse(cached);
            if (Date.now() - data.timestamp > MEMOS_CONFIG.cacheTime) {
                localStorage.removeItem(this.key);
                return null;
            }
            return data.memos;
        },
        
        set(memos) {
            localStorage.setItem(this.key, JSON.stringify({
                memos: memos,
                timestamp: Date.now()
            }));
        },
        
        clear() {
            localStorage.removeItem(this.key);
        }
    };
    
    // API 调用
    const API = {
        async fetchMemos() {
            // 检查缓存
            const cached = Cache.get();
            if (cached) {
                return cached;
            }
            
            // 构建 URL
            let url = `${MEMOS_CONFIG.serverUrl}/api/v1/memos?`;
            const params = {
                limit: MEMOS_CONFIG.limit,
                visibility: 'PUBLIC'
            };
            
            if (MEMOS_CONFIG.creatorId) {
                params.creatorId = MEMOS_CONFIG.creatorId;
            }
            
            url += new URLSearchParams(params).toString();
            
            try {
                const response = await fetch(url);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                
                const data = await response.json();
                Cache.set(data);
                return data;
            } catch (error) {
                console.error('获取 Memos 失败:', error);
                throw error;
            }
        }
    };
    
    // 渲染器
    const Renderer = {
        renderCard(memo) {
            // 从 memo.name 中提取 UID (格式: memos/xxx)
            let uid = '';
            if (memo.name) {
                uid = memo.name.replace(/^memos\//, '');
            } else if (memo.uid) {
                uid = memo.uid;
            } else if (memo.id) {
                uid = memo.id;
            }
            
            const memoUrl = MEMOS_CONFIG.serverUrl && uid
                ? `${MEMOS_CONFIG.serverUrl.replace(/\/$/, '')}/memos/${uid}` 
                : '#';
            
            return `
                <a href="${memoUrl}" target="_blank" rel="noopener" class="memo-link">
                    <div class="memo-card">
                        <div class="memo-content">${this.formatContent(memo.content)}</div>
                        <div class="memo-meta">
                            <time>${this.formatDate(memo.createTime)}</time>
                            ${memo.tags ? `<div class="memo-tags">${memo.tags.map(tag => `<span class="tag">#${tag}</span>`).join('')}</div>` : ''}
                        </div>
                    </div>
                </a>
            `;
        },
        
        formatContent(content) {
            // 简单的 Markdown 转换
            return content
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>')
                .replace(/\n/g, '<br>');
        },
        
        formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleString('zh-CN');
        }
    };
    
    // 主控制器
    const MemosWidget = {
        async init(containerSelector) {
            const container = document.querySelector(containerSelector);
            if (!container) {
                console.error('找不到容器:', containerSelector);
                return;
            }
            
            try {
                container.innerHTML = '<div class="loading">加载中...</div>';
                
                const data = await API.fetchMemos();
                
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
                    container.innerHTML = '<div class="empty">暂无公开备忘录</div>';
                    return;
                }
                
                container.innerHTML = memos.map(memo => Renderer.renderCard(memo)).join('');
                
            } catch (error) {
                container.innerHTML = `<div class="error">加载失败：${error.message}</div>`;
            }
        },
        
        refresh(containerSelector) {
            Cache.clear();
            this.init(containerSelector);
        }
    };
    
    // 导出到全局
    window.MemosWidget = MemosWidget;
    
    // 自动初始化（当页面加载完成时）
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.querySelector('#memos-widget');
        if (container) {
            MemosWidget.init('#memos-widget');
        }
    });
    
})();
