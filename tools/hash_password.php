<?php
// Simple password hash generator
// DELETE THIS FILE after use for security

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plain = $_POST['password'] ?? '';
    if ($plain !== '') {
        $hash = password_hash($plain, PASSWORD_BCRYPT);
        $result = [
            'plain' => $plain,
            'hash' => $hash,
            'sql' => "UPDATE users SET password = '$hash' WHERE username = 'admin';"
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Hash Generator</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing:border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height:100vh; display:flex; align-items:center; justify-content:center;
            padding:20px; margin:0;
        }
        .container {
            background:white; padding:32px; border-radius:16px;
            box-shadow:0 20px 60px rgba(0,0,0,0.3); max-width:600px; width:100%;
        }
        h1 { margin:0 0 8px; font-size:24px; }
        .warn {
            background:#fef3c7; color:#92400e; padding:12px; border-radius:8px;
            margin-bottom:20px; border:1px solid #fde68a; font-size:14px;
        }
        label { display:block; margin-bottom:6px; font-weight:600; }
        input[type="text"], input[type="password"] {
            width:100%; padding:12px; border:2px solid #e5e7eb; border-radius:8px;
            font-size:16px; font-family:inherit;
        }
        input:focus { outline:none; border-color:#667eea; }
        button {
            width:100%; padding:14px; background:#10b981; color:white; border:none;
            border-radius:8px; font-size:16px; font-weight:700; cursor:pointer;
            margin-top:12px;
        }
        button:hover { background:#059669; }
        .result {
            margin-top:24px; padding:16px; background:#f3f4f6; border-radius:8px;
            border:1px solid #d1d5db;
        }
        .result h3 { margin:0 0 10px; font-size:16px; }
        .code {
            background:#1f2937; color:#10b981; padding:12px; border-radius:6px;
            font-family: 'Courier New', monospace; font-size:13px;
            word-break:break-all; margin:8px 0;
        }
        .copy-btn {
            background:#6366f1; padding:8px 14px; font-size:13px; width:auto;
            display:inline-block; margin-top:6px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Password Hash Generator</h1>
        <p style="color:#6b7280; margin:4px 0 16px;">Generate bcrypt hashes for your admin passwords</p>

        <div class="warn">
            ⚠️ <strong>Security Warning:</strong> DELETE this file immediately after use!
        </div>

        <form method="POST">
            <label for="password">Enter Password:</label>
            <input type="text" id="password" name="password" required 
                   placeholder="e.g., MySecure@Pass123" autocomplete="off">
            <button type="submit">Generate Hash</button>
        </form>

        <?php if (isset($result)): ?>
        <div class="result">
            <h3>✅ Hash Generated</h3>
            
            <p><strong>Plain Password:</strong></p>
            <div class="code"><?php echo htmlspecialchars($result['plain']); ?></div>

            <p><strong>Bcrypt Hash:</strong></p>
            <div class="code" id="hash"><?php echo htmlspecialchars($result['hash']); ?></div>
            <button class="copy-btn" onclick="copy('hash')">Copy Hash</button>

            <p><strong>SQL Query (for phpMyAdmin):</strong></p>
            <div class="code" id="sql"><?php echo htmlspecialchars($result['sql']); ?></div>
            <button class="copy-btn" onclick="copy('sql')">Copy SQL</button>

            <p style="margin-top:16px; font-size:14px; color:#6b7280;">
                Run the SQL in phpMyAdmin or use the hash in your INSERT/UPDATE statement.
            </p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function copy(id) {
            const el = document.getElementById(id);
            const text = el.textContent;
            navigator.clipboard.writeText(text).then(() => {
                alert('Copied to clipboard!');
            });
        }
    </script>
</body>
</html>