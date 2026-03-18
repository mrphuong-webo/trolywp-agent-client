# Quy trình tích hợp: Chat AI từ WordPress qua n8n + MCP

Tài liệu mô tả quy trình end-to-end: cài đặt 4 thành phần (WordPress plugins + node n8n), cấu hình workflow n8n, lịch sử chat bền vững, và deploy node lên server.

---

## 1. Tổng quan luồng

```
[User trên site WordPress]
    → Widget chat (@n8n/chat) gọi thẳng n8n webhook
    → firstEntryJson: metadata, siteId, authorId, authorKey, sessionId, chat_token
[ n8n ]
    → Chat Trigger nhận message + metadata
    → InputChat (Set): domain, message, session_id, site_url, chat_token
    → AI Agent + WEBO MCP (tool) → gọi MCP qua Proxy
[ WordPress mcp-proxy ]
    → POST /wp-json/trolywp-client/v1/mcp-proxy
    → body: { chat_token, body: <JSON-RPC> }
    → WordPress xác thực chat_token, ký HMAC, forward tới /wp-json/mcp/v1/router
[ webo-mcp — WEBO MCP ]
    → MCP router xử lý tools/list, tools/call theo HMAC của user
```

- **Chat**: widget gọi **thẳng n8n** (không proxy). WordPress chỉ inject metadata (firstEntryJson) khi load trang.
- **MCP**: n8n gọi **mcp-proxy** trên WordPress với `chat_token`; WordPress thay mặt user ký HMAC và gọi MCP. n8n không cần lưu API Key/Secret.

---

## 2. Thành phần cần cài

| Thành phần | Vai trò |
|------------|--------|
| **webo-mcp** (WEBO MCP) | MCP router: REST `/mcp/v1/router`, đăng ký tools, trả tools/list & xử lý tools/call. |
| **webo-hmac-auth** | Quản lý API key HMAC theo user; middleware xác thực request tới MCP. User cần có key (User → WEBO API Keys) để chat gọi MCP qua proxy. |
| **trolywp-agent-client** | Inject widget chat lên site; cung cấp firstEntryJson (metadata, sessionId, chat_token); endpoint mcp-proxy. **Phụ thuộc:** webo-hmac-auth. |
| **n8n-nodes-webo-mcp** | Node n8n: WEBO MCP (tool) + credential Proxy. Build & deploy lên server n8n (~/.n8n/custom). |

---

## 3. Cài đặt WordPress

1. Cài lần lượt: **webo-mcp** (WEBO MCP) → **webo-hmac-auth** → **trolywp-agent-client** (kích hoạt đúng thứ tự).
2. **WEBO MCP**: Settings → WEBO MCP Settings (tuỳ chọn API Key / HMAC nếu bật auth).
3. **webo-hmac-auth**: Tạo API key cho user chat: Users → Chỉnh sửa user → WEBO API Keys (HMAC) → Create Key.
4. **trolywp-agent-client**: Settings → TrolyWP Client → Tab Cài đặt:
   - Chế độ n8n: **Dùng n8n riêng**
   - Chat URL: URL từ n8n **Chat Trigger** (vd `https://n8n.example.com/webhook/xxx/chat`).
5. Trong n8n Chat Trigger: **Allowed Origin (CORS)** thêm domain site WordPress (vd `https://toplist.danang.vn`).

---

## 4. firstEntryJson (metadata gửi sang n8n)

Widget nhận từ server và gửi kèm mỗi request:

| Trường | Mô tả |
|--------|--------|
| **metadata** | site_url, site_name, language, user_id, user_email, display_name, user_roles; multisite có thêm blog_id. |
| **siteId** | UUID site (option `trolywp_agent_client_site_id`). |
| **authorId** | User ID WordPress. |
| **authorKey** | Key ID HMAC của user (để hiển thị/đối soát; proxy dùng chat_token). |
| **sessionId** | Phiên chat cố định theo user (user_meta). Dùng để n8n lưu/tải lịch sử — reload trang vẫn cùng sessionId. |
| **chat_token** | Token một lần dùng cho mcp-proxy; TTL **24h**, mỗi lần gọi proxy thành công tự gia hạn (sliding expiry). |

---

## 5. Cấu hình workflow n8n

### 5.1 Chat Trigger

- **Mode:** Embedded Chat (widget từ WordPress).
- **Load Previous Session:** **From Memory** (để giữ lịch sử khi reload).
- **Allowed Origin:** Thêm domain site WordPress.

### 5.2 Memory

- Thêm node **Window Buffer Memory** (hoặc Memory khác).
- Kết nối **Chat Trigger** và **Agent** (hoặc Chain) **cùng** với Memory.
- Session ID lấy từ Chat Trigger (metadata.sessionId từ WordPress).

### 5.3 InputChat (Set)

Map từ output Chat Trigger sang item cho Agent:

| Name | Value (Expression) |
|------|--------------------|
| domain | `={{ ($json.metadata?.metadata?.site_url \|\| $json.metadata?.site_url \|\| 'https://webo.vn').toString().replace(/^https?:\\/\\/([^/]+).*/, '$1') }}` |
| message | `={{ $json.message \|\| $json.chatInput \|\| "No Input" }}` |
| session_id | `={{ $json.sessionId \|\| "" }}` |
| site_url | `={{ $json.metadata?.metadata?.site_url \|\| $json.metadata?.site_url \|\| "" }}` |
| chat_token | `={{ $json.metadata?.chat_token \|\| $json.chat_token \|\| "" }}` |

### 5.4 Credential WEBO MCP API

- Auth: **Proxy (theo author: chat_token)**.
- Base URL: có thể để trống (node lấy site_url từ input).

### 5.5 Node WEBO MCP (tool)

- Khi dùng với AI Agent: thêm **Proxy: Site URL (fallback)** = `={{ $('InputChat').first().json.site_url }}`, **Proxy: Chat Token (fallback)** = `={{ $('InputChat').first().json.chat_token }}`.

### 5.6 System Message (Webo Agent)

- Dùng prompt có hướng dẫn Proxy auth và quy tắc gọi tool (vd `tools/list` dùng `{"include_internal": false}`, `initialize` dùng `{"client": "webo-agent", "version": "1.0"}`). Tham khảo `prompts/webo-agent-system-prompt.txt` trong repo n8n-nodes-webo-mcp.

---

## 6. Lịch sử chat / phiên bền vững

- WordPress gửi **sessionId** cố định theo user (lưu user_meta). Reload trang vẫn cùng sessionId.
- n8n: **Load Previous Session** = **From Memory** và Chat Trigger + Agent cùng kết nối **một** Memory node. Session ID từ metadata → reload vẫn load lại lịch sử.

---

## 7. Lỗi 401 mcp-proxy

- **chat_token hết hạn:** TTL 24h, sliding expiry. Nếu vẫn 401 thì user **refresh trang** để nhận token mới.
- **user_has_no_hmac_key:** User chưa có HMAC key → vào WordPress, Users → Edit user → WEBO API Keys → Create Key.

---

## 8. Deploy node n8n lên server

Trong repo **n8n-nodes-webo-mcp**:

1. **Build & pack**
   - `npm install`
   - `npm run build`
   - `npm pack` → ra file `n8n-nodes-webo-mcp-<version>.tgz`

2. **Deploy bằng script (PowerShell)**
   - `.\deploy.ps1`
   - Script: build → pack → SCP lên server → copy vào container `~/.n8n/custom` → restart n8n.
   - Biến môi trường (tuỳ chọn): `N8N_SSH_KEY`, `N8N_SERVER`, `N8N_REMOTE_PATH`, `N8N_CONTAINER`, `N8N_CUSTOM_PATH`.

3. Sau deploy: reload n8n UI (F5 / xoá cache) để nhận node/credential mới.

Chi tiết sửa lỗi workflow và JSON mẫu InputChat: xem **n8n-nodes-webo-mcp/docs/fix-workflow-when-chat-from-wordpress.md**.
