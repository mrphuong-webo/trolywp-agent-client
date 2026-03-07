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
    // Open/close logic
    icon.onclick = function(){
        popup.style.display = popup.style.display === 'none' ? 'block' : 'none';
    };
    // Resize logic
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
