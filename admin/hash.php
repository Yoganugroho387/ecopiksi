<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Password Hasher</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .container-sm {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 500px;
        }
    </style>
</head>
<body>
    <div class="container-sm">
        <h2 class="text-center mb-4">PHP Password Hasher</h2>

        <?php
        $plain_password = '';
        $hashed_password = '';

        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['password'])) {
            $plain_password = $_POST['password'];
            $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
        }
        ?>

        <form action="hash.php" method="POST">
            <div class="mb-3">
                <label for="password" class="form-label">Masukkan Password Teks Biasa:</label>
                <input type="text" class="form-control" id="password" name="password" required value="<?php echo htmlspecialchars($plain_password); ?>">
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3">Buat Hash</button>
        </form>

        <?php if (!empty($hashed_password)): ?>
            <div class="mt-4">
                <h5>Password Teks Biasa:</h5>
                <p class="form-control-plaintext"><code><?php echo htmlspecialchars($plain_password); ?></code></p>

                <h5>Hash yang Dihasilkan:</h5>
                <div class="input-group">
                    <input type="text" class="form-control" id="hashedOutput" value="<?php echo htmlspecialchars($hashed_password); ?>" readonly>
                    <button class="btn btn-outline-secondary" type="button" id="copyButton">Salin</button>
                </div>
                <small class="text-muted d-block mt-2">Salin hash ini dan gunakan di database Anda. Jangan simpan password teks biasa di database!</small>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const copyButton = document.getElementById('copyButton');
            if (copyButton) {
                copyButton.addEventListener('click', function() {
                    const hashedOutput = document.getElementById('hashedOutput');
                    hashedOutput.select();
                    hashedOutput.setSelectionRange(0, 99999); // For mobile devices
                    document.execCommand('copy');
                    
                    // Beri feedback visual
                    const originalText = copyButton.textContent;
                    copyButton.textContent = 'Disalin!';
                    setTimeout(() => {
                        copyButton.textContent = originalText;
                    }, 2000);
                });
            }
        });
    </script>
</body>
</html>