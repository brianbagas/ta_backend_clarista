<?php
// generate_correct_hash.php
// Upload ke public/ di shared hosting, akses via browser

$password = 'password';

// Generate dengan bcrypt cost 12 (sesuai dengan yang ada di database)
$hash_cost_12 = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Generate dengan bcrypt cost 10 (standar Laravel)
$hash_cost_10 = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

// Test hash yang ada di database
$existing_hash = '$2y$12$mNbCL9u0UuoPYkOFpD8EOckx...'; // Copy full hash dari database

?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Generate Correct Hash</title>
    <style>
        body {
            font-family: Arial;
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
            background: #f5f5f5;
        }

        .box {
            background: white;
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #1976D2;
        }

        .hash {
            background: #f9f9f9;
            padding: 12px;
            border: 2px solid #1976D2;
            border-radius: 5px;
            word-break: break-all;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            margin: 10px 0;
        }

        .sql {
            background: #263238;
            color: #aed581;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
            margin: 10px 0;
        }

        button {
            background: #1976D2;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }

        button:hover {
            background: #1565C0;
        }

        .warning {
            background: #fff3cd;
            border-left: 4px solid #ff9800;
            padding: 15px;
            margin: 15px 0;
        }

        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 15px 0;
        }

        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 15px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #1976D2;
            color: white;
        }
    </style>
</head>

<body>
    <h1>🔐 Password Hash Generator (Production Server)</h1>

    <div class="box success">
        <strong>✅ Server Info:</strong><br>
        PHP Version: <code><?php echo phpversion(); ?></code><br>
        Password to hash: <code><?php echo htmlspecialchars($password); ?></code>
    </div>

    <div class="box">
        <h2>Generated Hashes:</h2>
        <table>
            <tr>
                <th>Cost</th>
                <th>Hash Preview</th>
                <th>Action</th>
            </tr>
            <tr>
                <td><strong>Cost 12</strong><br>(Sesuai DB saat ini)</td>
                <td>
                    <div class="hash" id="hash12">
                        <?php echo $hash_cost_12; ?>
                    </div>
                </td>
                <td><button onclick="copyHash('hash12')">📋 Copy</button></td>
            </tr>
            <tr>
                <td><strong>Cost 10</strong><br>(Standar Laravel)</td>
                <td>
                    <div class="hash" id="hash10">
                        <?php echo $hash_cost_10; ?>
                    </div>
                </td>
                <td><button onclick="copyHash('hash10')">📋 Copy</button></td>
            </tr>
        </table>
    </div>

    <div class="box">
        <h2>📝 SQL Query - GUNAKAN HASH COST 12:</h2>
        <div class="sql" id="sqlQuery">UPDATE users
            SET password = '
            <?php echo $hash_cost_12; ?>'
            WHERE email = 'owner@clarista.com';
        </div>
        <button onclick="copySQL()">📋 Copy SQL Query</button>
    </div>

    <div class="box">
        <h3>🔍 Verification Test:</h3>
        <?php
        // Test apakah hash yang baru di-generate bisa verify password
        $test_password = 'password';
        $verify_12 = password_verify($test_password, $hash_cost_12);
        $verify_10 = password_verify($test_password, $hash_cost_10);
        ?>
        <p>
            Hash Cost 12 verify:
            <?php echo $verify_12 ? '✅ VALID' : '❌ INVALID'; ?><br>
            Hash Cost 10 verify:
            <?php echo $verify_10 ? '✅ VALID' : '❌ INVALID'; ?>
        </p>
    </div>

    <div class="box warning">
        <h3>📋 Langkah-langkah:</h3>
        <ol>
            <li>Klik tombol <strong>"Copy SQL Query"</strong> di atas</li>
            <li>Buka <strong>phpMyAdmin</strong> di shared hosting</li>
            <li>Pilih database Clarista</li>
            <li>Klik tab <strong>"SQL"</strong></li>
            <li>Paste dan <strong>Execute</strong> query</li>
            <li>Verifikasi dengan query: <code>SELECT password FROM users WHERE email = 'owner@clarista.com';</code>
            </li>
            <li>Password hash harus sama persis dengan hash Cost 12 di atas</li>
            <li>Coba login dengan:<br>
                Email: <strong>owner@clarista.com</strong><br>
                Password: <strong>password</strong>
            </li>
            <li><strong>HAPUS file ini setelah selesai!</strong></li>
        </ol>
    </div>

    <div class="box error">
        <strong>⚠️ KEAMANAN:</strong> Segera hapus file ini setelah selesai! File ini bisa diakses publik dan merupakan
        risiko keamanan.
    </div>

    <script>
        function copyHash(elementId) {
            const hash = document.getElementById(elementId).textContent.trim();
            navigator.clipboard.writeText(hash).then(() => {
                alert('✅ Hash copied to clipboard!');
            }).catch(err => {
                alert('❌ Failed to copy: ' + err);
            });
        }

        function copySQL() {
            const sql = document.getElementById('sqlQuery').textContent.trim();
            navigator.clipboard.writeText(sql).then(() => {
                alert('✅ SQL query copied! Sekarang paste di phpMyAdmin dan Execute.');
            }).catch(err => {
                alert('❌ Failed to copy: ' + err);
            });
        }
    </script>
</body>

</html>