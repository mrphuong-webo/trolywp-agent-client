# TrolyWP Agent Client

Plugin WordPress: Chat AI qua n8n (widget @n8n/chat) cho user đăng nhập, gửi metadata (site, user, **sessionId**, **chat_token**) và cung cấp endpoint **mcp-proxy** để n8n gọi MCP theo author mà không cần lưu HMAC secret.

## Yêu cầu

- WordPress 6.0+, PHP 7.4+
- Plugin **webo-hmac-auth** (bắt buộc): quản lý API key HMAC theo user.

## Cài đặt nhanh

1. Cài và kích hoạt **webo-hmac-auth** trước, sau đó cài **trolywp-agent-client**.
2. Settings → TrolyWP Client → Tab **Cài đặt**: Chế độ n8n = **Dùng n8n riêng**, nhập **Chat URL** (từ n8n Chat Trigger).
3. Trong n8n Chat Trigger: Allowed Origin thêm domain site.
4. Tạo HMAC key cho user chat: Users → Edit user → WEBO API Keys (HMAC).

## firstEntryJson gửi sang n8n

- **metadata**: site_url, site_name, user_id, user_email, display_name, user_roles, …
- **siteId**, **authorId**, **authorKey**
- **sessionId**: phiên chat cố định theo user → reload trang vẫn giữ lịch sử (n8n bật Load Previous Session = From Memory).
- **chat_token**: dùng cho mcp-proxy; TTL 24h, sliding expiry mỗi lần gọi proxy.

## Quy trình tích hợp đầy đủ

Xem **[docs/QUY-TRINH-TICH-HOP.md](docs/QUY-TRINH-TICH-HOP.md)** để có hướng dẫn end-to-end: 4 thành phần (webo-wordpress-mcp, webo-hmac-auth, trolywp-agent-client, n8n-nodes-webo-mcp), cấu hình workflow n8n, lịch sử chat, deploy node, xử lý lỗi.

Trong WordPress: **TrolyWP Client** → Tab **Hướng dẫn** cũng tóm tắt chức năng và MCP proxy.
