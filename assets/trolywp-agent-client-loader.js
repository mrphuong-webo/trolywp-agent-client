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
