// TrolyWP Chat Widget Loader - Clean version
// Chỉ 1 handler, không xung đột, tối ưu UI

(function() {
        // Tạo toolbar nếu chưa có
        if (!popup.querySelector('.trolywp-chat-toolbar')) {
            let toolbar = document.createElement('div');
            toolbar.className = 'trolywp-chat-toolbar';
            toolbar.style = 'display:flex;align-items:center;gap:8px;padding:8px;background:#f5f5f5;border-bottom:1px solid #eee;border-radius:12px 12px 0 0;';
            // Sidebar button
            let sidebarBtn = document.createElement('button');
            sidebarBtn.textContent = 'Sidebar';
            sidebarBtn.className = 'trolywp-chat-sidebar-btn';
            sidebarBtn.style = 'padding:6px 16px;border-radius:6px;background:#222;color:#fff;border:none;cursor:pointer;font-weight:bold;';
            sidebarBtn.onclick = function() {
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
            toolbar.appendChild(sidebarBtn);
            popup.appendChild(toolbar);
        }
        // Tạo iframe chat nếu chưa có
        if (!popup.querySelector('.trolywp-chat-iframe')) {
            // Lấy info từ config
            const params = new URLSearchParams();
            if (config.siteId) params.append('siteId', config.siteId);
            if (config.authorKey) params.append('authorKey', config.authorKey);
            if (config.authorId) params.append('authorId', config.authorId);
            let chatUrl = n8nUrl;
            if (chatUrl) {
                if (chatUrl.indexOf('?') === -1) {
                    chatUrl += '?' + params.toString();
                } else if (params.toString()) {
                    chatUrl += (chatUrl.endsWith('?') || chatUrl.endsWith('&') ? '' : '&') + params.toString();
                }
            }
            let iframe = document.createElement('iframe');
            iframe.className = 'trolywp-chat-iframe';
            iframe.src = chatUrl;
            iframe.style = 'width:100%;height:calc(100% - 48px);border:none;background:#fff;';
            iframe.allow = 'clipboard-write;';
            popup.appendChild(iframe);
        }
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
                } else {
                    historyDiv.innerHTML += `<div style="margin-bottom:6px;color:red;">Chưa cấu hình webhook n8nUrl!</div>`;
                    historyDiv.scrollTop = historyDiv.scrollHeight;
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
