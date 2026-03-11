// TrolyWP Chat – chỉ dùng widget @n8n/chat (CDN), gửi metadata qua createChat()
(function() {
    function run() {
        var config = window.TrolywpClientChatConfig || {};
        if (!config.n8nUrl) return;
        var root = document.getElementById('trolywp-n8n-chat-root');
        if (!root) return;
        var script = document.createElement('script');
        script.type = 'module';
        script.textContent = [
            "import('https://cdn.jsdelivr.net/npm/@n8n/chat@1.12.0/dist/chat.bundle.es.js').then(function(m){",
            "  var c = window.TrolywpClientChatConfig || {};",
            "  m.createChat({ webhookUrl: c.n8nUrl, metadata: c.firstEntryJson || {}, target: '#trolywp-n8n-chat-root' });",
            "});"
        ].join('\n');
        document.body.appendChild(script);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
