# Git hooks

Sau mỗi commit, tự động push lên mọi remote (GitHub/GitLab). Push lên `main` sẽ kích hoạt workflow deploy plugin lên VPS.

**Cài đặt (chạy 1 lần trong repo):**

```bash
cp githooks/post-commit .git/hooks/post-commit
chmod +x .git/hooks/post-commit
```

Windows (PowerShell):

```powershell
Copy-Item githooks\post-commit .git\hooks\post-commit -Force
```
