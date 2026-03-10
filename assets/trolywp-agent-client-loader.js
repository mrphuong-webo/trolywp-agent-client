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

        // TrolyWP Chat Widget Loader - Minimal, only popup/sidebar/iframe
        (function() {
            function setupPopup() {
                const config = window.TrolywpClientChatConfig || {};
                const n8nUrl = config.n8nUrl || '';
                // Tạo icon
                let icon = document.getElementById('trolywp-chat-icon');
                if (!icon) {
                    icon = document.createElement('div');
                    icon.id = 'trolywp-chat-icon';
                    icon.className = 'trolywp-chat-icon';
                    icon.title = 'TrolyWP Chat';
                    icon.innerHTML = '<svg width="32" height="32" fill="#fff" viewBox="0 0 24 24"><path d="M12 3C6.48 3 2 7.03 2 12c0 2.4 1.05 4.58 2.83 6.23-.13.49-.51 1.77-.73 2.47-.09.28.19.54.47.45.66-.21 2.02-.66 2.51-.81C8.7 21.66 10.31 22 12 22c5.52 0 10-4.03 10-9s-4.48-10-10-10zm0 17c-1.52 0-3.01-.29-4.37-.85l-.34-.14-.36.11c-.41.13-1.23.39-1.87.59.18-.56.47-1.51.57-1.89l.09-.36-.27-.28C4.13 16.13 3 14.17 3 12c0-4.42 4.03-8 9-8s9 3.58 9 8-4.03 8-9 8zm-1-7h2v2h-2v-2zm0-8h2v6h-2V5z"/></svg>';
                    document.body.appendChild(icon);
                }
                // Tạo popup
                let popup = document.getElementById('trolywp-chat-popup');
                if (!popup) {
                    popup = document.createElement('div');
                    popup.id = 'trolywp-chat-popup';
                    popup.className = 'trolywp-chat-popup';
                    popup.style.position = 'fixed';
                    popup.style.right = '24px';
                    popup.style.bottom = '90px';
                    popup.style.height = '420px';
                    popup.style.width = '350px';
                    popup.style.zIndex = '100000';
                    document.body.appendChild(popup);
                }
                icon.style.display = 'flex';
                popup.style.display = 'none';
                icon.onclick = function() {
                    popup.style.display = (popup.style.display === 'none' || popup.style.display === '') ? 'block' : 'none';
                };
                // Toolbar + Sidebar
                if (!popup.querySelector('.trolywp-chat-toolbar')) {
                    let toolbar = document.createElement('div');
                    toolbar.className = 'trolywp-chat-toolbar';
                    toolbar.style = 'display:flex;align-items:center;gap:8px;padding:8px;background:#f5f5f5;border-bottom:1px solid #eee;border-radius:12px 12px 0 0;';
                    let sidebarBtn = document.createElement('button');
                    sidebarBtn.textContent = 'Sidebar';
                    sidebarBtn.className = 'trolywp-chat-sidebar-btn';
                    sidebarBtn.style = 'padding:6px 16px;border-radius:6px;background:#222;color:#fff;border:none;cursor:pointer;font-weight:bold;';
                    sidebarBtn.onclick = function() {
                        document.body.classList.toggle('trolywp-chat-sidebar-open');
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
                // Iframe chat
                if (!popup.querySelector('.trolywp-chat-iframe')) {
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
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', setupPopup);
            } else {
                setupPopup();
            }
        })();
