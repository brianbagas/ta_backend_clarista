<?php
/**
 * Clear Laravel Cache - Production
 * 
 * Upload file ini ke folder public/ di shared hosting
 * Akses: https://api.claristahomestay.web.id/clear_cache.php
 * HAPUS file ini setelah selesai!
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Clear Cache - Clarista</title>
    <style>
        body {
            font-family: Arial;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }

        .box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 15px 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #1976D2;
        }

        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 10px 0;
        }

        .warning {
            background: #fff3cd;
            border-left: 4px solid #ff9800;
            padding: 15px;
            margin: 10px 0;
        }
    </style>
</head>

<body>
    <h1>🧹 Clear Laravel Cache</h1>

    <div class="box">
        <?php
        try {
            // Clear config cache
            $kernel->call('config:clear');
            echo '<div class="success">✅ Config cache cleared</div>';

            // Clear application cache
            $kernel->call('cache:clear');
            echo '<div class="success">✅ Application cache cleared</div>';

            // Clear route cache
            $kernel->call('route:clear');
            echo '<div class="success">✅ Route cache cleared</div>';

            // Clear view cache
            $kernel->call('view:clear');
            echo '<div class="success">✅ View cache cleared</div>';

            echo '<div class="success"><strong>🎉 All caches cleared successfully!</strong></div>';

        } catch (Exception $e) {
            echo '<div class="warning">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>
    </div>

    <div class="box warning">
        <strong>⚠️ PENTING:</strong> Segera HAPUS file ini setelah selesai! File ini bisa diakses publik dan merupakan
        risiko keamanan.
    </div>

    <div class="box">
        <h3>Next Steps:</h3>
        <ol>
            <li>Delete file <code>clear_cache.php</code> dari server</li>
            <li>Test login dari frontend</li>
            <li>Verify status 200 OK di Developer Console</li>
        </ol>
    </div>
</body>

</html>