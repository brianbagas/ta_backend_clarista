<?php
/**
 * Password Hash Generator untuk Clarista Homestay
 * 
 * Cara menggunakan:
 * 1. Upload file ini ke shared hosting (folder public atau root Laravel)
 * 2. Akses via browser: https://api.claristahomestay.web.id/generate_hash.php
 * 3. Copy hash yang dihasilkan
 * 4. Update database manual via phpMyAdmin
 * 
 * PENTING: HAPUS FILE INI setelah selesai digunakan untuk keamanan!
 */

// Password yang ingin di-hash
$password = 'password';

// Generate hash menggunakan bcrypt (sama dengan Laravel)
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Hash Generator - Clarista Homestay</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }

        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #1976D2;
            border-bottom: 3px solid #1976D2;
            padding-bottom: 10px;
        }

        .hash-box {
            background: #f9f9f9;
            border: 2px solid #1976D2;
            padding: 15px;
            border-radius: 5px;
            word-break: break-all;
            font-family: 'Courier New', monospace;
            margin: 20px 0;
        }

        .info {
            background: #e3f2fd;
            padding: 15px;
            border-left: 4px solid #1976D2;
            margin: 20px 0;
        }

        .warning {
            background: #fff3cd;
            padding: 15px;
            border-left: 4px solid #ff9800;
            margin: 20px 0;
        }

        .sql-box {
            background: #263238;
            color: #aed581;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            margin: 20px 0;
            overflow-x: auto;
        }

        button {
            background: #1976D2;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background: #1565C0;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🔐 Password Hash Generator</h1>

        <div class="info">
            <strong>ℹ️ Informasi:</strong><br>
            Password yang di-hash: <strong>
                <?php echo htmlspecialchars($password); ?>
            </strong><br>
            Algoritma: <strong>BCRYPT (Cost: 10)</strong><br>
            Kompatibel dengan: <strong>Laravel Hash::make()</strong>
        </div>

        <h2>Hash yang Dihasilkan:</h2>
        <div class="hash-box" id="hashValue">
            <?php echo $hash; ?>
        </div>

        <button onclick="copyHash()">📋 Copy Hash</button>

        <h2>SQL Query untuk Update Database:</h2>
        <div class="sql-box">
            UPDATE users <br>
            SET password = '
            <?php echo $hash; ?>'<br>
            WHERE email = 'owner@clarista.com';
        </div>

        <h2>Langkah-langkah Penggunaan:</h2>
        <ol>
            <li>Copy hash di atas (klik tombol "Copy Hash")</li>
            <li>Buka phpMyAdmin di shared hosting Anda</li>
            <li>Pilih database Clarista Homestay</li>
            <li>Klik tab "SQL"</li>
            <li>Paste dan jalankan query SQL di atas</li>
            <li>Coba login dengan:
                <ul>
                    <li>Email: <strong>owner@clarista.com</strong></li>
                    <li>Password: <strong>
                            <?php echo htmlspecialchars($password); ?>
                        </strong></li>
                </ul>
            </li>
        </ol>

        <div class="warning">
            <strong>⚠️ PERINGATAN KEAMANAN:</strong><br>
            Segera HAPUS file ini setelah selesai digunakan! File ini bisa diakses publik dan merupakan risiko keamanan.
        </div>

        <h2>Verifikasi Hash:</h2>
        <div class="info">
            <?php
            // Verifikasi bahwa hash bekerja
            if (password_verify($password, $hash)) {
                echo "✅ <strong>Hash Valid!</strong> Password '$password' cocok dengan hash yang dihasilkan.";
            } else {
                echo "❌ <strong>Hash Tidak Valid!</strong> Ada masalah dengan hash generation.";
            }
            ?>
        </div>

        <h2>Informasi Server:</h2>
        <div class="info">
            PHP Version: <strong>
                <?php echo phpversion(); ?>
            </strong><br>
            Password Hashing Available: <strong>
                <?php echo function_exists('password_hash') ? 'Yes ✅' : 'No ❌'; ?>
            </strong><br>
            Bcrypt Available: <strong>
                <?php echo defined('PASSWORD_BCRYPT') ? 'Yes ✅' : 'No ❌'; ?>
            </strong>
        </div>
    </div>

    <script>
        function copyHash() {
            const hashText = document.getElementById('hashValue').textContent.trim();
            navigator.clipboard.writeText(hashText).then(() => {
                alert('✅ Hash berhasil di-copy ke clipboard!');
            }).catch(err => {
                alert('❌ Gagal copy hash: ' + err);
            });
        }
    </script>
</body>

</html>