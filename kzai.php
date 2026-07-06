<?php
// 防止直接访问，仅允许被 kontkz.php 包含
if (!defined('IN_KONTKZ')) {
    http_response_code(404);
    exit;
}
// ... 原有代码 ...
// ============================================================
// AI 扩展 - 必须与 kontkz.php 配合使用
// 文件位置: 与 kontkz.php 同一目录
// ============================================================

// 直接输出 AI 侧边栏 HTML、CSS、JavaScript
?>
<style>
/* ===== AI 侧边栏样式 ===== */
.ai-sidebar {
    width: 360px;
    flex-shrink: 0;
    display: none;
    flex-direction: column;
    background: rgba(28, 28, 30, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-left: 1px solid rgba(255, 255, 255, 0.06);
    padding: 16px;
    overflow: hidden;
    height: 100%;
}
.ai-sidebar.open { display: flex; }

.ai-drag-zone {
    border: 2px dashed rgba(255,255,255,0.15);
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    color: #8e8e93;
    font-size: 13px;
    margin-bottom: 12px;
    transition: border-color 0.2s, background 0.2s;
    flex-shrink: 0;
    display: none;
}
.ai-drag-zone.drag-over {
    border-color: #0a84ff;
    background: rgba(10, 132, 255, 0.08);
    display: block;
}
.ai-drag-zone .icon { font-size: 24px; display: block; margin-bottom: 6px; }

.ai-toolbar {
    display: none;
    flex-shrink: 0;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 6px;
    padding: 6px 10px;
    margin-bottom: 10px;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    max-height: 80px;
    overflow-y: auto;
}
.ai-toolbar.has-code { display: flex; }
.ai-toolbar .code-item {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 4px;
    padding: 2px 8px;
    font-size: 11px;
    color: #ccc;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}
.ai-toolbar .code-item .apply-btn {
    background: #0a84ff;
    border: none;
    color: #fff;
    padding: 0 8px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 10px;
    line-height: 18px;
    transition: background 0.15s;
}
.ai-toolbar .code-item .apply-btn:hover { background: #2f97ff; }

.ai-file-list {
    flex-shrink: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 8px;
    max-height: 60px;
    overflow-y: auto;
}
.ai-file-tag {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 4px;
    padding: 2px 8px;
    font-size: 11px;
    color: #ccc;
    display: flex;
    align-items: center;
    gap: 4px;
}
.ai-file-tag .remove {
    cursor: pointer;
    color: #ff3b30;
    font-weight: bold;
}

.ai-chat-container {
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 4px 0;
    min-height: 0;
}
.ai-message {
    max-width: 92%;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.5;
    word-break: break-word;
    animation: fadeIn 0.2s ease;
}
.ai-message.user {
    align-self: flex-end;
    background: #0a84ff;
    color: #fff;
    border-bottom-right-radius: 4px;
}
.ai-message.bot {
    align-self: flex-start;
    background: rgba(255,255,255,0.06);
    color: #f5f5f7;
    border-bottom-left-radius: 4px;
}
.ai-message .code-block-wrapper {
    margin: 6px 0;
    border-radius: 4px;
    overflow: hidden;
    background: #1e1e24;
    border: 1px solid rgba(255,255,255,0.06);
}
.ai-message .code-block-wrapper .code-header {
    background: #2d2d34;
    padding: 4px 10px;
    font-size: 11px;
    color: #a0a0aa;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #3d3d44;
}
.ai-message .code-block-wrapper pre {
    margin: 0;
    padding: 8px 12px;
    overflow-x: auto;
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 12px;
    color: #f8f8f2;
    white-space: pre;
}

.ai-chat-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
    margin-bottom: 8px;
    padding: 0 2px;
}
.ai-chat-toolbar .title {
    font-size: 13px;
    color: #8e8e93;
    font-weight: 500;
}
.ai-chat-toolbar .clear-btn {
    background: transparent;
    border: none;
    color: #8e8e93;
    font-size: 12px;
    cursor: pointer;
    padding: 4px 10px;
    border-radius: 4px;
    transition: background 0.15s, color 0.15s;
}
.ai-chat-toolbar .clear-btn:hover {
    background: rgba(255,255,255,0.08);
    color: #fff;
}

.ai-input-area {
    display: flex;
    gap: 8px;
    padding-top: 10px;
    border-top: 1px solid rgba(255,255,255,0.06);
    flex-shrink: 0;
    align-items: flex-end;
}
.ai-input-area textarea {
    flex: 1;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 6px;
    padding: 8px 10px;
    color: #f5f5f7;
    font-size: 13px;
    resize: none;
    min-height: 36px;
    max-height: 120px;
    outline: none;
    font-family: inherit;
    transition: border-color 0.15s;
}
.ai-input-area textarea:focus { border-color: #0a84ff; }
.ai-input-area .send-btn {
    background: #0a84ff;
    border: none;
    color: #fff;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.2s;
    flex-shrink: 0;
    height: 36px;
    display: flex;
    align-items: center;
}
.ai-input-area .send-btn:hover { background: #2f97ff; }
.ai-input-area .send-btn:disabled { opacity: 0.5; cursor: default; }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

/* 移动端适配 */
@media (max-width: 768px) {
    .ai-sidebar { width: 100% !important; height: 50vh; border-left: none; border-top: 1px solid rgba(255,255,255,0.06); }
}
@media (max-width: 600px) {
    .ai-sidebar { width: 100% !important; height: 45vh; }
}
    /* ========== 折叠侧边栏底部添加竖排文字 ========== */
#editorSidebar.collapsed::after {
    content: "K\A-\AO\AN\A!\A が\A一\A番\A好\Aき\Aで\Aす";
    white-space: pre;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    color: #8e8e93;
    font-size: 13px;
    letter-spacing: 4px;
    line-height: 1.6;
    padding-top: 20px;
    width: 100%;
    flex: 1;
}
#editorSidebar.collapsed {
    display: flex !important;
    flex-direction: column !important;
    width: 50px !important;
    min-width: 50px !important;
}
#editorSidebar.collapsed .sidebar-header {
    flex-shrink: 0;
    justify-content: center;
    padding: 10px 0;
    display: flex;
    align-items: flex-start;
}
#editorSidebar.collapsed .sidebar-header span {
    display: none !important;
}
</style>

<!-- AI 侧边栏 HTML 结构 -->
<div class="ai-sidebar" id="aiSidebar">
    <div class="ai-drag-zone" id="aiDragZone">
        <span class="icon">📂</span>
        <span>拖拽文件到此处以附加当前编辑器内容</span>
    </div>
    <div class="ai-toolbar" id="aiToolbar"></div>
    <div class="ai-file-list" id="aiFileList"></div>
    <div class="ai-chat-toolbar">
        <span class="title">💬 对话</span>
        <button class="clear-btn" id="clearChatBtn">🗑️ 清空</button>
    </div>
    <div class="ai-chat-container" id="aiChatContainer"></div>
    <div class="ai-input-area">
        <textarea id="aiInput" rows="1" placeholder="输入消息，Enter发送，Shift+Enter换行"></textarea>
        <button class="send-btn" id="aiSendBtn">发送</button>
    </div>
</div>

<script>
// ============================================================
// AI 扩展 JavaScript
// ============================================================

(function() {
    'use strict';

    // ---- 配置（可在此修改 API 密钥） ----
    const DEEPSEEK_API_KEY = 'sk=海力士';
    const AI_MODEL = 'deepseek-reasoner';
    const AI_API_URL = 'https://api.deepseek.com/chat/completions';
    const AI_SYSTEM_PROMPT = '你是一个代码助手，帮助用户修改和优化代码。当前你正在查看的代码文件内容可以通过拖拽文件到侧边栏附加到对话中。';

    // ---- 状态变量 ----
    let attachedAIFiles = [];
    let aiMessages = [];
    let isAIChatting = false;
    let aiCodeBlocks = [];
    let codeBlockCounter = 0;
    let aiSidebarOpen = false;

    // ---- DOM 引用 ----
    const aiSidebar = document.getElementById('aiSidebar');
    const aiChatContainer = document.getElementById('aiChatContainer');
    const aiInput = document.getElementById('aiInput');
    const aiSendBtn = document.getElementById('aiSendBtn');
    const aiDragZone = document.getElementById('aiDragZone');
    const aiFileList = document.getElementById('aiFileList');
    const aiToolbar = document.getElementById('aiToolbar');
    const clearChatBtn = document.getElementById('clearChatBtn');
    const toggleAISidebarBtn = document.getElementById('toggleAISidebar');

    // ---- 辅助函数（依赖全局函数 showToast, showAlert, escapeHtml 等） ----
    // 这些由 kontkz.php 提供，直接使用

    // ---- 内部函数 ----
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ===== 增强的 Markdown 渲染函数 =====
    function renderMarkdown(text) {
        if (!text) return '';

        // 行内格式处理（加粗、斜体、删除线、行内代码、链接）
        function processInline(str) {
            if (!str) return '';
            let s = str;
            // 行内代码
            s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
            // 删除线
            s = s.replace(/~~([^~]+)~~/g, '<del>$1</del>');
            // 加粗
            s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            // 斜体
            s = s.replace(/\*(.+?)\*/g, '<em>$1</em>');
            // 链接
            s = s.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>');
            return s;
        }

        // 渲染表格（需要至少两行：表头 + 分隔行）
        function renderTable(rows) {
            if (rows.length < 2) return '';
            const header = rows[0];
            const alignRow = rows[1];
            const dataRows = rows.slice(2);

            const headerCells = header.split('|').filter(c => c.trim() !== '');
            const aligns = alignRow.split('|').filter(c => c.trim() !== '').map(c => {
                const cell = c.trim();
                if (cell.startsWith(':') && cell.endsWith(':')) return 'center';
                if (cell.endsWith(':')) return 'right';
                if (cell.startsWith(':')) return 'left';
                return 'left';
            });
            while (aligns.length < headerCells.length) aligns.push('left');

            let html = '<table style="border-collapse:collapse;width:100%;margin:6px 0;">';
            html += '<thead><tr>';
            headerCells.forEach((cell, idx) => {
                html += `<th style="border:1px solid #555;padding:4px 8px;text-align:${aligns[idx]};">${processInline(cell.trim())}</th>`;
            });
            html += '</tr></thead>';
            if (dataRows.length > 0) {
                html += '<tbody>';
                dataRows.forEach(row => {
                    const cells = row.split('|').filter(c => c.trim() !== '');
                    while (cells.length < headerCells.length) cells.push('');
                    html += '<tr>';
                    cells.forEach((cell, idx) => {
                        html += `<td style="border:1px solid #555;padding:4px 8px;text-align:${aligns[idx]};">${processInline(cell.trim())}</td>`;
                    });
                    html += '</tr>';
                });
                html += '</tbody>';
            }
            html += '</table>';
            return html;
        }

        const lines = text.split('\n');
        let result = '';
        let paragraphLines = [];
        let inList = false;
        let listItems = [];
        let inBlockquote = false;
        let blockquoteLines = [];
        let inTable = false;
        let tableRows = [];

        function flushParagraph() {
            if (paragraphLines.length === 0) return;
            const content = paragraphLines.join(' ');
            result += '<p>' + processInline(content) + '</p>';
            paragraphLines = [];
        }
        function flushList() {
            if (listItems.length === 0) return;
            result += '<ul>' + listItems.map(item => '<li>' + item + '</li>').join('') + '</ul>';
            listItems = [];
            inList = false;
        }
        function flushBlockquote() {
            if (blockquoteLines.length === 0) return;
            result += '<blockquote style="border-left:3px solid #666;padding-left:12px;margin:6px 0;">' +
                blockquoteLines.map(l => '<p>' + processInline(l) + '</p>').join('') +
                '</blockquote>';
            blockquoteLines = [];
            inBlockquote = false;
        }
        function flushTable() {
            if (tableRows.length === 0) return;
            result += renderTable(tableRows);
            tableRows = [];
            inTable = false;
        }

        for (let i = 0; i < lines.length; i++) {
            let line = lines[i];
            let trimmed = line.trim();

            // 表格内收集
            if (inTable) {
                if (trimmed.startsWith('|') && trimmed.endsWith('|')) {
                    tableRows.push(trimmed);
                    continue;
                } else {
                    flushTable();
                    // 继续处理当前行
                }
            }

            // 空行 → 刷新所有缓冲区
            if (trimmed === '') {
                flushParagraph();
                flushList();
                flushBlockquote();
                flushTable();
                continue;
            }

            // 检测表格开始（当前行以 | 开头结尾，下一行是分隔行）
            if (!inTable && trimmed.startsWith('|') && trimmed.endsWith('|')) {
                const nextLine = (i + 1 < lines.length) ? lines[i+1].trim() : '';
                if (nextLine.startsWith('|') && /^\|?[\s\-:]+\|$/.test(nextLine.replace(/\s/g,''))) {
                    flushParagraph();
                    flushList();
                    flushBlockquote();
                    inTable = true;
                    tableRows = [trimmed];
                    continue;
                }
            }

            // 引用块
            if (!inBlockquote && trimmed.startsWith('>')) {
                flushParagraph();
                flushList();
                flushTable();
                inBlockquote = true;
                blockquoteLines.push(trimmed.substring(1).trim());
                continue;
            } else if (inBlockquote && trimmed.startsWith('>')) {
                blockquoteLines.push(trimmed.substring(1).trim());
                continue;
            } else if (inBlockquote) {
                flushBlockquote();
                // 不跳过当前行
            }

            // 无序列表
            const listMatch = trimmed.match(/^[-*]\s+(.+)$/);
            if (listMatch) {
                flushParagraph();
                if (!inList) {
                    inList = true;
                    listItems = [];
                }
                listItems.push(processInline(listMatch[1]));
                continue;
            } else {
                if (inList) {
                    flushList();
                }
            }

            // 标题 (# ## ###)
            const headerMatch = trimmed.match(/^(#{1,3})\s+(.+)$/);
            if (headerMatch) {
                flushParagraph();
                flushList();
                flushBlockquote();
                flushTable();
                const level = headerMatch[1].length;
                const content = headerMatch[2];
                result += '<h' + level + '>' + processInline(content) + '</h' + level + '>';
                continue;
            }

            // 普通文本行
            paragraphLines.push(trimmed);
        }

        // 处理剩余内容
        flushParagraph();
        flushList();
        flushBlockquote();
        flushTable();

        return result;
    }

    // 提取代码块
    function extractCodeBlocks(text) {
        const blocks = [];
        const regex = /```(\w+)?\n([\s\S]*?)```/g;
        let match;
        while ((match = regex.exec(text)) !== null) {
            blocks.push({
                language: match[1] || 'text',
                code: match[2]
            });
        }
        return blocks;
    }

    // 渲染带代码块的消息（调用增强的 renderMarkdown）
    function renderMarkdownWithCode(text) {
        const blocks = extractCodeBlocks(text);
        if (blocks.length === 0) return renderMarkdown(text);
        let parts = [];
        let lastIdx = 0;
        const regex = /```(\w+)?\n([\s\S]*?)```/g;
        let match;
        while ((match = regex.exec(text)) !== null) {
            const before = text.substring(lastIdx, match.index);
            if (before.trim()) parts.push({ type: 'text', content: before });
            parts.push({ type: 'code', language: match[1] || 'text', code: match[2] });
            lastIdx = match.index + match[0].length;
        }
        if (lastIdx < text.length) {
            const remaining = text.substring(lastIdx);
            if (remaining.trim()) parts.push({ type: 'text', content: remaining });
        }
        let html = '';
        for (const part of parts) {
            if (part.type === 'text') {
                html += renderMarkdown(part.content);
            } else {
                html += `
                    <div class="code-block-wrapper">
                        <div class="code-header"><span>📄 ${part.language.toUpperCase()}</span></div>
                        <pre>${escapeHtml(part.code)}</pre>
                    </div>
                `;
            }
        }
        return html;
    }

    // 添加消息到聊天区域
    function addAIMessage(role, content) {
        const div = document.createElement('div');
        div.className = 'ai-message ' + role;
        if (role === 'bot') {
            div.innerHTML = renderMarkdownWithCode(content);
        } else {
            div.textContent = content;
        }
        aiChatContainer.appendChild(div);
        aiChatContainer.scrollTop = aiChatContainer.scrollHeight;
        return div;
    }

    // 更新 AI 工具栏
    function updateAIToolbar() {
        aiToolbar.innerHTML = '';
        if (aiCodeBlocks.length === 0) {
            aiToolbar.classList.remove('has-code');
            return;
        }
        aiToolbar.classList.add('has-code');
        aiCodeBlocks.forEach((block, idx) => {
            const item = document.createElement('div');
            item.className = 'code-item';
            const langLabel = block.language || 'text';
            item.innerHTML = `
                <span>📄 ${langLabel.toUpperCase()}</span>
                <button class="apply-btn" data-idx="${idx}">应用</button>
            `;
            item.querySelector('.apply-btn').addEventListener('click', function(e) {
                e.stopPropagation();
                const index = parseInt(this.dataset.idx, 10);
                applyCodeBlock(index);
            });
            aiToolbar.appendChild(item);
        });
    }

    // 应用代码块到编辑器
    function applyCodeBlock(index) {
        if (typeof editor === 'undefined' || !editor) {
            showToast('编辑器未打开，无法应用代码', 'warning');
            return;
        }
        if (index < 0 || index >= aiCodeBlocks.length) return;
        const block = aiCodeBlocks[index];
        if (!block) return;
        editor.setValue(block.code);
        showToast('✅ 代码已应用到编辑器', 'success');
        if (typeof activeTabPath !== 'undefined' && activeTabPath && typeof tabData !== 'undefined' && tabData[activeTabPath]) {
            tabData[activeTabPath].content = block.code;
            tabData[activeTabPath].dirty = true;
            if (typeof renderTabs === 'function') renderTabs();
        }
    }

    // 更新文件列表
    function updateAIFileList() {
        if (attachedAIFiles.length === 0) {
            aiFileList.innerHTML = '';
            return;
        }
        let html = '';
        attachedAIFiles.forEach((f, i) => {
            html += `<span class="ai-file-tag">📎 ${escapeHtml(f.name)} <span class="remove" onclick="removeAIFile(${i})">×</span></span>`;
        });
        aiFileList.innerHTML = html;
    }

    window.removeAIFile = function(index) {
        attachedAIFiles.splice(index, 1);
        updateAIFileList();
    };

    function addAIFile(name, text) {
        if (attachedAIFiles.some(f => f.name === name)) {
            attachedAIFiles = attachedAIFiles.filter(f => f.name !== name);
        }
        attachedAIFiles.push({ name, text });
        updateAIFileList();
        showToast(`📎 已附加文件: ${name}`, 'info', 1200);
    }

    // 清空对话
    function clearAIChat() {
        aiChatContainer.innerHTML = '';
        aiMessages = [];
        aiCodeBlocks = [];
        updateAIToolbar();
        addAIMessage('bot', '👋 你好！我是 AI 代码助手。你可以拖拽文件到此处附加当前编辑器内容，或直接提问。');
        showToast('对话已清空', 'info', 1500);
    }

    // 发送消息
    async function sendAIMessage() {
        const userText = aiInput.value.trim();
        if (!userText && attachedAIFiles.length === 0) {
            showToast('请输入消息或附加文件', 'warning');
            return;
        }
        if (isAIChatting) return;

        let userMsg = userText;
        if (attachedAIFiles.length > 0) {
            let fileBlock = '';
            attachedAIFiles.forEach(f => {
                fileBlock += `[上传文件: ${f.name}]\n----------文件内容----------\n${f.text}\n----------内容结束----------\n`;
            });
            userMsg = fileBlock + (userText ? '\n' + userText : '');
        }

        aiInput.value = '';
        aiInput.style.height = '36px';
        const filesToSend = attachedAIFiles.slice();
        attachedAIFiles = [];
        updateAIFileList();

        addAIMessage('user', userText || '(文件内容已附加)');
        const aiMsgDiv = document.createElement('div');
        aiMsgDiv.className = 'ai-message bot';
        aiMsgDiv.id = 'ai-streaming-msg';
        aiMsgDiv.textContent = '🤔 思考中...';
        aiChatContainer.appendChild(aiMsgDiv);
        aiChatContainer.scrollTop = aiChatContainer.scrollHeight;

        isAIChatting = true;
        aiSendBtn.disabled = true;
        aiSendBtn.textContent = '发送中...';

        const messages = [
            { role: 'system', content: AI_SYSTEM_PROMPT },
            ...aiMessages,
            { role: 'user', content: userMsg }
        ];

        try {
            const response = await fetch(AI_API_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + DEEPSEEK_API_KEY
                },
                body: JSON.stringify({
                    model: AI_MODEL,
                    messages: messages,
                    max_tokens: 200000,
                    temperature: 0.7,
                    stream: true
                })
            });

            if (!response.ok || !response.body) {
                throw new Error('API 请求失败: ' + response.status);
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let done = false;
            let fullReply = '';
            let buffer = '';
            let lastUpdateTime = 0;
            let updatePending = false;

            const updateUI = () => {
                if (updatePending) return;
                updatePending = true;
                requestAnimationFrame(() => {
                    if (aiMsgDiv) {
                        aiMsgDiv.textContent = fullReply;
                        aiChatContainer.scrollTop = aiChatContainer.scrollHeight;
                    }
                    updatePending = false;
                });
            };

            aiMsgDiv.textContent = '';

            while (!done) {
                const { value, done: readerDone } = await reader.read();
                done = readerDone;
                if (value) {
                    buffer += decoder.decode(value, { stream: !done });
                    const lines = buffer.split('\n');
                    buffer = lines.pop();

                    for (const line of lines) {
                        const trimmed = line.trim();
                        if (trimmed.startsWith('data: ')) {
                            const jsonData = trimmed.substring(6);
                            if (jsonData === '[DONE]') break;
                            try {
                                const chunk = JSON.parse(jsonData);
                                const delta = chunk.choices?.[0]?.delta?.content;
                                if (delta) {
                                    fullReply += delta;
                                    const now = Date.now();
                                    if (now - lastUpdateTime > 100 || fullReply.length > 2000) {
                                        lastUpdateTime = now;
                                        updateUI();
                                    }
                                }
                            } catch (e) { /* ignore */ }
                        }
                    }
                }
            }

            if (aiMsgDiv) {
                aiMsgDiv.textContent = '';
                const blocks = extractCodeBlocks(fullReply);
                aiCodeBlocks = blocks;
                updateAIToolbar();
                aiMsgDiv.innerHTML = renderMarkdownWithCode(fullReply);
            }

            aiMessages.push({ role: 'user', content: userMsg });
            aiMessages.push({ role: 'assistant', content: fullReply });
            if (aiMessages.length > 20) {
                aiMessages = aiMessages.slice(-20);
            }

        } catch (err) {
            aiMsgDiv.textContent = '❌ 错误: ' + err.message;
            showToast('AI 请求失败: ' + err.message, 'error');
        }

        isAIChatting = false;
        aiSendBtn.disabled = false;
        aiSendBtn.textContent = '发送';
        if (aiMsgDiv) aiMsgDiv.removeAttribute('id');
        aiChatContainer.scrollTop = aiChatContainer.scrollHeight;
    }

    // ---- 事件绑定 ----
    if (toggleAISidebarBtn) {
        // 注意：不在此处设置 display，由 kontkz.php 的 openEditor 控制显示
        toggleAISidebarBtn.addEventListener('click', function() {
            aiSidebarOpen = !aiSidebarOpen;
            aiSidebar.classList.toggle('open', aiSidebarOpen);
            if (aiSidebarOpen && aiChatContainer.children.length === 0) {
                addAIMessage('bot', '👋 你好！我是 AI 代码助手。你可以拖拽文件到此处附加当前编辑器内容，或直接提问。');
            }
        });
    }

    aiSendBtn.addEventListener('click', sendAIMessage);
    aiInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendAIMessage();
        }
    });
    aiInput.addEventListener('input', function() {
        this.style.height = '36px';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    clearChatBtn.addEventListener('click', clearAIChat);

    // ---- 拖拽功能 ----
    let dragCounter = 0;

    function showDragZone() {
        aiDragZone.style.display = 'block';
        aiDragZone.classList.add('drag-over');
    }
    function hideDragZone() {
        aiDragZone.classList.remove('drag-over');
        aiDragZone.style.display = 'none';
    }

    document.addEventListener('dragenter', function(e) {
        if (e.dataTransfer.types && e.dataTransfer.types.indexOf('Files') !== -1) {
            dragCounter++;
            if (dragCounter === 1 && aiSidebar.classList.contains('open')) {
                showDragZone();
            }
        }
    });
    document.addEventListener('dragleave', function(e) {
        if (e.dataTransfer.types && e.dataTransfer.types.indexOf('Files') !== -1) {
            dragCounter--;
            if (dragCounter === 0) hideDragZone();
        }
    });
    document.addEventListener('drop', function(e) {
        if (e.dataTransfer.types && e.dataTransfer.types.indexOf('Files') !== -1) {
            dragCounter = 0;
            hideDragZone();
        }
    });

    aiDragZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        this.classList.add('drag-over');
    });
    aiDragZone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
    });
    aiDragZone.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        hideDragZone();
        if (typeof editor === 'undefined' || !editor) {
            showToast('编辑器未打开，无法获取内容', 'warning');
            return;
        }
        const code = editor.getValue();
        let fileName = '当前文件';
        if (typeof activeTabPath !== 'undefined' && activeTabPath) {
            fileName = activeTabPath.split('/').pop();
        }
        addAIFile(fileName, code);
        showToast(`✅ 已附加当前编辑器内容 (${fileName})`, 'success', 1500);
    });

    // ---- 标签页右键添加到AI ----
    document.addEventListener('tab-contextmenu', function(e) {
        const path = e.detail.path;
        if (!path) return;
        if (typeof tabData !== 'undefined' && tabData[path]) {
            const content = tabData[path].content || '';
            const name = path.split('/').pop();
            if (attachedAIFiles.some(f => f.name === name)) {
                attachedAIFiles = attachedAIFiles.filter(f => f.name !== name);
            }
            attachedAIFiles.push({ name, text: content });
            updateAIFileList();
            showToast(`📎 已附加文件 "${name}" 到AI引用`, 'success', 1500);
            if (!aiSidebar.classList.contains('open')) {
                aiSidebar.classList.add('open');
                aiSidebarOpen = true;
                if (aiChatContainer.children.length === 0) {
                    addAIMessage('bot', '👋 你好！我是 AI 代码助手。你可以拖拽文件到此处附加当前编辑器内容，或直接提问。');
                }
            }
        } else {
            showToast('文件未加载，请先打开', 'warning');
        }
    });

    // ---- 初始化 ----
    // 若编辑器已经打开（比如页面刷新后），由 kontkz 的 openEditor 负责显示按钮。
    // 这里只处理侧边栏的初始状态。
    console.log('AI 扩展已加载，API Key: ' + DEEPSEEK_API_KEY.slice(0, 10) + '...');
})();
</script>