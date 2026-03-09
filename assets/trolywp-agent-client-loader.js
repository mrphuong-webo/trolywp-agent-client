            // Hàm gửi message lên manager hub
            async function sendChatToManager(payload) {
                // Gọi backend để ký HMAC (giả lập demo)
                // Thực tế: gọi AJAX hoặc REST API WordPress để ký HMAC bằng PHP
                // payload.signature = await getHmacSignature(payload);
                // Demo: signature là chuỗi random
                // Lấy key của author từ config (inject từ PHP)
                const authorKey = window.TrolywpClientChatConfig.authorKey || '';
                const n8nUrl = window.TrolywpClientChatConfig.n8nUrl || '';
                if (!n8nUrl) {
                    let history = [];
                    try { history = JSON.parse(localStorage.getItem('trolywp_chat_history')||'[]'); } catch(e){}
                    history.push('[Lỗi] Chưa cấu hình webhook n8n.');
                    localStorage.setItem('trolywp_chat_history', JSON.stringify(history));
                    renderHistory();
                    return;
                }
                try {
                    const res = await fetch(n8nUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type':'application/json',
                            'X-AUTHOR-KEY': authorKey
                        },
                        body: JSON.stringify(payload)
                    });
                    const result = await res.json();
                    if (result && result.reply) {
                        let history = [];
                        try { history = JSON.parse(localStorage.getItem('trolywp_chat_history')||'[]'); } catch(e){}
                        history.push(`[AI] ${result.reply}`);
                        localStorage.setItem('trolywp_chat_history', JSON.stringify(history));
                        renderHistory();
                    }
                } catch (e) {
                    let history = [];
                    try { history = JSON.parse(localStorage.getItem('trolywp_chat_history')||'[]'); } catch(e){}
                    history.push(`[Lỗi] Không gửi được chat: ${e.message}`);
                    localStorage.setItem('trolywp_chat_history', JSON.stringify(history));
                    renderHistory();
                }
            }
        // Tạo vùng chọn agent
        let agentSelectDiv = popup.querySelector('.trolywp-agent-select');
        if (!agentSelectDiv) {
            agentSelectDiv = document.createElement('div');
            agentSelectDiv.className = 'trolywp-agent-select';
            agentSelectDiv.style = 'padding:8px;background:#f9f9f9;border-bottom:1px solid #eee;display:flex;gap:8px;align-items:center;';
            popup.insertBefore(agentSelectDiv, chatHistoryDiv);
        }

        // Hàm lấy danh sách agent từ manager hub (demo)
        function fetchAgents() {
            // Thay bằng fetch('/wp-json/trolywp-client/v1/agents', ...) nếu có backend
            // Demo: trả về cứng
            return Promise.resolve([
                {id:'agent1', name:'AI Chatbot', desc:'Trợ lý AI chung'},
                {id:'agent2', name:'Content Writer', desc:'Viết nội dung'},
                {id:'agent3', name:'SEO Assistant', desc:'Tối ưu SEO'},
            ]);
        }

        // Hàm render select agent
        let selectedAgentId = '';
        function renderAgentSelect() {
            fetchAgents().then(agents => {
                agentSelectDiv.innerHTML = '<b>Chọn agent:</b>';
                const select = document.createElement('select');
                select.style = 'padding:6px;border-radius:6px;border:1px solid #ccc;';
                agents.forEach(agent => {
                    const option = document.createElement('option');
                    option.value = agent.id;
                    option.textContent = agent.name + ' - ' + agent.desc;
                    select.appendChild(option);
                });
                select.onchange = function() {
                    selectedAgentId = this.value;
                };
                agentSelectDiv.appendChild(select);
                // Chọn agent đầu tiên mặc định
                if (agents.length > 0) {
                    select.value = agents[0].id;
                    selectedAgentId = agents[0].id;
                }
            });
        }

        // Gửi message với metadata đầy đủ
        chatInputDiv.querySelector('.trolywp-chat-send').onclick = function() {
            const input = chatInputDiv.querySelector('.trolywp-chat-msg');
            const msg = input.value.trim();
            if (!msg || !selectedAgentId) return;
            // Lấy metadata (demo)
            const site_id = window.TrolywpClientChatConfig.siteId || 'demo-site';
            const author_id = window.TrolywpClientChatConfig.authorId || 'demo-author';
            const conversation_id = window.TrolywpClientChatConfig.conversationId || 'demo-conv';
            const payload = {
                site_id,
                author_id,
                agent_id: selectedAgentId,
                conversation_id,
                message: msg,
                timestamp: Date.now(),
                nonce: Math.random().toString(36).substring(2),
            };
            // Gửi lên manager hub
            const chatPayload = {
                site_id,
                author_id,
                agent_id: selectedAgentId,
                conversation_id,
                message: msg,
                timestamp: Date.now(),
                nonce: Math.random().toString(36).substring(2),
            };
            // Lưu message gửi
            let history = [];
            try { history = JSON.parse(localStorage.getItem('trolywp_chat_history')||'[]'); } catch(e){}
            history.push(`[${selectedAgentId}] ${msg}`);
            localStorage.setItem('trolywp_chat_history', JSON.stringify(history));
            input.value = '';
            renderHistory();
            // Gửi lên manager hub và nhận phản hồi AI
            sendChatToManager(chatPayload);
        };

        // Đảm bảo popup luôn được render và hiện ra ngay lần đầu tiên
        popup.style.display = 'none'; // Ẩn mặc định
        icon.onclick = function(){
            if (popup.style.display !== 'block') {
                popup.style.display = 'block';
                renderHistory();
                renderSuggestions();
                renderAgentSelect();
            }
        };
    // Module suggestion: hiển thị gợi ý câu hỏi
    let suggestionDiv = popup.querySelector('.trolywp-chat-suggestion');
    if (!suggestionDiv) {
        suggestionDiv = document.createElement('div');
        suggestionDiv.className = 'trolywp-chat-suggestion';
        suggestionDiv.style = 'padding:8px;background:#f5f5f5;border-top:1px solid #eee;display:flex;gap:6px;flex-wrap:wrap;';
        popup.appendChild(suggestionDiv);
    }

    // Hàm lấy gợi ý từ API (demo: trả về cứng)
    function fetchSuggestions(history) {
        // Thay bằng fetch('/wp-json/trolywp/v1/suggestion', ...) nếu có backend
        // Demo: trả về gợi ý cứng
        return Promise.resolve([
            'Xin chào!',
            'Bạn cần hỗ trợ gì?',
            'Tôi có thể giúp gì cho bạn?',
            'Hãy mô tả vấn đề của bạn.',
            'Bạn muốn hỏi về chủ đề nào?'
        ]);
    }

    // Hàm render suggestion
    function renderSuggestions() {
        let history = [];
        try { history = JSON.parse(localStorage.getItem('trolywp_chat_history')||'[]'); } catch(e){}
        fetchSuggestions(history).then(suggestions => {
            suggestionDiv.innerHTML = suggestions.map(s => `<button style="padding:6px 12px;border-radius:6px;border:1px solid #ccc;background:#fff;cursor:pointer;">${s}</button>`).join('');
            // Gửi nhanh khi click gợi ý
            Array.from(suggestionDiv.querySelectorAll('button')).forEach(btn => {
                btn.onclick = function() {
                    chatInputDiv.querySelector('.trolywp-chat-msg').value = btn.textContent;
                    chatInputDiv.querySelector('.trolywp-chat-send').click();
                };
            });
        });
    }

    // Hiển thị suggestion khi mở popup
    icon.onclick = function(){
        popup.style.display = popup.style.display === 'none' ? 'block' : 'none';
        if (popup.style.display === 'block') {
            renderHistory();
            renderSuggestions();
        }
    };
// Main loader for TrolyWP Agent Client
// Handles: open/close popup, resize, mode switch, shared UI logic
import { getEl } from './trolywp-agent-client-utils.js';
// Default UI config (can be injected from PHP)
window.TrolywpClientChatConfig = window.TrolywpClientChatConfig || {
    minWidth: 260,
    maxWidth: 600,
    defaultWidth: 350,
    defaultHeight: 420
};
function setupPopup() {
    const ICON_ID = 'trolywp-chat-icon';
    const POPUP_ID = 'trolywp-chat-popup';
    const HANDLE_ID = 'trolywp-chat-resize-handle';
    const icon = getEl(ICON_ID);
    const popup = getEl(POPUP_ID);
    const handle = getEl(HANDLE_ID);
    if (!icon || !popup) return;
    // Lưu và hiển thị lịch sử chat (demo: chỉ lưu các message gửi từ input giả lập)
    // Tạo vùng hiển thị lịch sử nếu chưa có
    let chatHistoryDiv = popup.querySelector('.trolywp-chat-history');
    if (!chatHistoryDiv) {
        chatHistoryDiv = document.createElement('div');
        chatHistoryDiv.className = 'trolywp-chat-history';
        chatHistoryDiv.style = 'height:70%;overflow:auto;padding:8px;';
        popup.insertBefore(chatHistoryDiv, popup.firstChild.nextSibling);
    }
    // Tạo input gửi tin nhắn demo
    let chatInputDiv = popup.querySelector('.trolywp-chat-input');
    if (!chatInputDiv) {
        chatInputDiv = document.createElement('div');
        chatInputDiv.className = 'trolywp-chat-input';
        chatInputDiv.style = 'padding:8px;border-top:1px solid #eee;background:#fafbfc;display:flex;gap:4px;';
        chatInputDiv.innerHTML = '<input type="text" class="trolywp-chat-msg" style="flex:1;padding:6px;border-radius:6px;border:1px solid #ccc;" placeholder="Nhập tin nhắn..."><button class="trolywp-chat-send" style="padding:6px 16px;border-radius:6px;border:none;background:#222;color:#fff;">Gửi</button>';
        popup.appendChild(chatInputDiv);
    }
    // Hàm render lịch sử
    function renderHistory() {
        let history = [];
        try { history = JSON.parse(localStorage.getItem('trolywp_chat_history')||'[]'); } catch(e){}
        chatHistoryDiv.innerHTML = history.map(msg => `<div style="margin-bottom:6px;"><span style="background:#eee;padding:4px 8px;border-radius:6px;">${msg}</span></div>`).join('');
        chatHistoryDiv.scrollTop = chatHistoryDiv.scrollHeight;
    }
    // Gửi tin nhắn demo
    chatInputDiv.querySelector('.trolywp-chat-send').onclick = function() {
        const input = chatInputDiv.querySelector('.trolywp-chat-msg');
        const msg = input.value.trim();
        if (!msg) return;
        let history = [];
        try { history = JSON.parse(localStorage.getItem('trolywp_chat_history')||'[]'); } catch(e){}
        history.push(msg);
        localStorage.setItem('trolywp_chat_history', JSON.stringify(history));
        input.value = '';
        renderHistory();
    };
    // Hiển thị lại lịch sử khi mở popup
    icon.onclick = function(){
        popup.style.display = popup.style.display === 'none' ? 'block' : 'none';
        if (popup.style.display === 'block') renderHistory();
    };
    // Resize logic giữ nguyên
    if (handle) {
        let isResizing = false, startX = 0, startWidth = 0;
        handle.addEventListener('mousedown', function(e){
            isResizing = true;
            startX = e.clientX;
            startWidth = popup.offsetWidth;
            document.body.style.userSelect = 'none';
        });
        document.addEventListener('mousemove', function(e){
            if (!isResizing) return;
            let dx = startX - e.clientX;
            let newWidth = Math.min(Math.max(startWidth + dx, window.TrolywpClientChatConfig.minWidth), window.TrolywpClientChatConfig.maxWidth);
            popup.style.width = newWidth + 'px';
        });
        document.addEventListener('mouseup', function(){
            if (isResizing) {
                isResizing = false;
                document.body.style.userSelect = '';
            }
        });
        // Touch support
        handle.addEventListener('touchstart', function(e){
            if (e.touches.length !== 1) return;
            isResizing = true;
            startX = e.touches[0].clientX;
            startWidth = popup.offsetWidth;
            document.body.style.userSelect = 'none';
        });
        document.addEventListener('touchmove', function(e){
            if (!isResizing || e.touches.length !== 1) return;
            let dx = startX - e.touches[0].clientX;
            let newWidth = Math.min(Math.max(startWidth + dx, window.TrolywpClientChatConfig.minWidth), window.TrolywpClientChatConfig.maxWidth);
            popup.style.width = newWidth + 'px';
        });
        document.addEventListener('touchend', function(){
            if (isResizing) {
                isResizing = false;
                document.body.style.userSelect = '';
            }
        });
    }
}
// Wait DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupPopup);
} else {
    setupPopup();
}
