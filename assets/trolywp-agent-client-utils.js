// Tiện ích dùng chung cho TrolyWP Agent Client
export function getEl(id) { return document.getElementById(id); }
export function addEvent(el, evt, fn) { if (el) el.addEventListener(evt, fn); }
export function setStyle(el, styles) { if (!el) return; for (const k in styles) el.style[k] = styles[k]; }
// ... có thể bổ sung thêm các hàm tiện ích khác nếu cần
