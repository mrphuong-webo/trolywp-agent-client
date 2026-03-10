// TrolyWP Chat Widget Loader - Clean version
// Chỉ 1 handler, không xung đột, tối ưu UI

(function() {
    function setupPopup() {
        // Lấy config từ PHP
        const config = window.TrolywpClientChatConfig || {};
        const n8nUrl = config.n8nUrl || '';
        // Tạo icon nếu chưa có
        let icon = document.getElementById('trolywp-chat-icon');
        if (!icon) {
            icon = document.createElement('div');
            icon.id = 'trolywp-chat-icon';
            icon.className = 'trolywp-chat-icon';
            icon.title = 'TrolyWP Chat';
            icon.innerHTML = '<svg width="32" height="32" fill="#fff" viewBox="0 0 24 24"><path d="M12 3C6.48 3 2 7.03 2 12c0 2.4 1.05 4.58 2.83 6.23-.13.49-.51 1.77-.73 2.47-.09.28.19.54.47.45.66-.21 2.02-.66 2.51-.81C8.7 21.66 10.31 22 12 22c5.52 0 10-4.03 10-9s-4.48-10-10-10zm0 17c-1.52 0-3.01-.29-4.37-.85l-.34-.14-.36.11c-.41.13-1.23.39-1.87.59.18-.56.47-1.51.57-1.89l.09-.36-.27-.28C4.13 16.13 3 14.17 3 12c0-4.42 4.03-8 9-8s9 3.58 9 8-4.03 8-9 8zm-1-7h2v2h-2v-2zm0-8h2v6h-2V5z"/></svg>';
            document.body.appendChild(icon);
        }
        // Tạo popup nếu chưa có
        let popup = document.getElementById('trolywp-chat-popup');
        if (!popup) {
            popup = document.createElement('div');
            popup.id = 'trolywp-chat-popup';
            popup.className = 'trolywp-chat-popup';
            document.body.appendChild(popup);
        }
        icon.style.display = 'flex';
        popup.style.display = 'none';
        // Handler toggle popup
        icon.onclick = function() {
            if (popup.style.display === 'none' || popup.style.display === '') {
                popup.style.display = 'block';
            } else {
                popup.style.display = 'none';
            }
        };
        // Tạo input chat
        if (!popup.querySelector('.trolywp-chat-input')) {
            let chatInputDiv = document.createElement('div');
            chatInputDiv.className = 'trolywp-chat-input';
            chatInputDiv.style = 'padding:8px;border-top:1px solid #eee;background:#fafbfc;display:flex;gap:4px;';
            chatInputDiv.innerHTML = '<input type="text" class="trolywp-chat-msg" style="flex:1;padding:6px;border-radius:6px;border:1px solid #ccc;" placeholder="Nhập tin nhắn..."><button class="trolywp-chat-send" style="padding:6px 16px;border-radius:6px;border:none;background:#222;color:#fff;">Gửi</button>';
            popup.appendChild(chatInputDiv);
            chatInputDiv.querySelector('.trolywp-chat-send').onclick = async function() {
                const input = chatInputDiv.querySelector('.trolywp-chat-msg');
                const msg = input.value.trim();
                if (!msg) return;
                input.value = '';
                // Hiển thị message trong popup
                let historyDiv = popup.querySelector('.trolywp-chat-history');
                if (!historyDiv) {
                    historyDiv = document.createElement('div');
                    historyDiv.className = 'trolywp-chat-history';
                    historyDiv.style = 'height:70%;overflow:auto;padding:8px;';
                    popup.insertBefore(historyDiv, popup.firstChild);
                }
                historyDiv.innerHTML += `<div style="margin-bottom:6px;"><span style="background:#eee;padding:4px 8px;border-radius:6px;">${msg}</span></div>`;
                historyDiv.scrollTop = historyDiv.scrollHeight;
                // Gửi message tới n8nUrl
                if (n8nUrl) {
                    historyDiv.innerHTML += `<div style="margin-bottom:6px;color:#888;">Đang gửi...</div>`;
                    try {
                        const res = await fetch(n8nUrl, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({message: msg})
                        });
                        const data = await res.json();
                        let reply = data.reply || JSON.stringify(data);
                        historyDiv.innerHTML += `<div style="margin-bottom:6px;text-align:right;"><span style="background:#222;color:#fff;padding:4px 8px;border-radius:6px;">${reply}</span></div>`;
                        historyDiv.scrollTop = historyDiv.scrollHeight;
                    } catch (e) {
                        historyDiv.innerHTML += `<div style="margin-bottom:6px;color:red;">Lỗi gửi hoặc nhận phản hồi!</div>`;
                        historyDiv.scrollTop = historyDiv.scrollHeight;
                    }
                }
            };
        }
        // Sidebar mode toggle
        if (!window.trolywpSidebarBtn) {
            window.trolywpSidebarBtn = document.createElement('button');
            window.trolywpSidebarBtn.textContent = 'Sidebar';
            window.trolywpSidebarBtn.style = 'position:fixed;top:24px;right:24px;z-index:100000;padding:8px 16px;border-radius:8px;background:#222;color:#fff;border:none;cursor:pointer;';
            document.body.appendChild(window.trolywpSidebarBtn);
            window.trolywpSidebarBtn.onclick = function() {
                document.body.classList.toggle('trolywp-chat-sidebar-open');
                let popup = document.getElementById('trolywp-chat-popup');
                if (document.body.classList.contains('trolywp-chat-sidebar-open')) {
                    popup.style.right = '0';
                    popup.style.bottom = '0';
                    popup.style.height = '100vh';
                    popup.style.width = '400px';
                } else {
                    popup.style.right = '24px';
                    popup.style.bottom = '90px';
                    popup.style.height = '420px';
                    popup.style.width = '350px';
                }
            };
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupPopup);
    } else {
        setupPopup();
    }
})();
