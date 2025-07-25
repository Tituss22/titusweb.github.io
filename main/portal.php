<?php
set_time_limit(0);
error_reporting(0);
@ini_set('zlib.output_compression', 0);
header("Content-Encoding: none");
ob_start();

$aturan = [
    'eval' => '/\beval\b.*\b(base64_decode|gzinflate|str_rot13)\b/',
    'remote_code' => '/\b(shell_exec|exec|system|passthru|proc_open|popen|curl_exec)\b/',
    'file_mod' => '/\b(file_put_contents|fopen|fwrite|unlink|move_uploaded_file)\b/',
    'global_vars' => '/\b(GLOBALS|_COOKIE|_REQUEST|_SERVER)\b.*\beval\b/',
    'preg_replace' => '/@preg_replace\b|\b(preg_replace)\b.*\b(e\'\'|\"\")\b/',
    'htaccess' => '/<IfModule mod_rewrite.c>/',
    'phpinfo' => '/\bphpinfo\b.*\(/'
];

if (isset($_GET['aksi']) && isset($_GET['berkas'])) {
    $berkas = $_GET['berkas'];
    // Validasi path untuk mencegah directory traversal
    $real_path = realpath($berkas);
    $base_path = realpath(".");
    
    if ($real_path === false || strpos($real_path, $base_path) !== 0) {
        die("Akses Ditolak!");
    }
    
    if ($_GET['aksi'] == 'tinggali') {
        if (file_exists($real_path) && is_readable($real_path)) {
            $content = @file_get_contents($real_path);
            if ($content === false) {
                echo "Gagal membaca file!";
            } else {
                echo '<pre>' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '</pre>';
            }
        } else {
            echo "File tidak ditemukan atau tidak dapat dibaca!";
        }
    } elseif ($_GET['aksi'] == 'hapus') {
        if (file_exists($real_path) && is_writable($real_path)) {
            if (@unlink($real_path)) {
                echo "Berkas berhasil dihapus!";
            } else {
                echo "Gagal menghapus berkas.";
            }
        } else {
            echo "File tidak ditemukan atau tidak dapat dihapus!";
        }
    }
    exit;
}

function daptar_berkas($dir, &$hasil = array()) {
    if (!is_dir($dir) || !is_readable($dir)) {
        return $hasil;
    }
    
    $ext_php = ['php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phps']; // Semua varian ekstensi PHP
    
    try {
        $scan = @scandir($dir);
        if ($scan === false) {
            return $hasil;
        }
        
        foreach ($scan as $nilai) {
            if ($nilai == '.' || $nilai == '..') {
                continue;
            }
            
            $lokasi = $dir . DIRECTORY_SEPARATOR . $nilai;
            
            // Skip if symlink to avoid infinite loop
            if (is_link($lokasi)) {
                continue;
            }
            
            // Skip if not readable
            if (!is_readable($lokasi)) {
                continue;
            }
            
            if (is_file($lokasi)) {
                $ext = pathinfo($lokasi, PATHINFO_EXTENSION);
                if (in_array(strtolower($ext), $ext_php)) { // Hanya ambil file dengan ekstensi PHP
                    $hasil[] = $lokasi;
                }
            } else if (is_dir($lokasi)) {
                // Batasi kedalaman rekursi untuk menghindari stack overflow
                $current_depth = count(explode(DIRECTORY_SEPARATOR, $lokasi));
                if ($current_depth < 20) { // Batasi kedalaman maksimal rekursi
                    daptar_berkas($lokasi, $hasil);
                }
            }
        }
    } catch (Exception $e) {
        // Tangani exception jika ada
    }
    
    return $hasil;
}

function maca($berkas) {
    if (!file_exists($berkas) || !is_readable($berkas)) {
        return false;
    }
    
    $ukuran = @filesize($berkas);
    if ($ukuran === false || $ukuran / 1024 / 1024 > 2) {
        return false;
    }
    
    return @file_get_contents($berkas);
}

function mariosan($konten) {
    if (!is_string($konten) || empty($konten)) {
        return [];
    }
    
    global $aturan;
    $hasil = [];
    
    foreach ($aturan as $nama => $pola) {
        if (@preg_match($pola, $konten)) {
            $hasil[] = $nama;
        }
    }
    
    return $hasil;
}

// Inisialisasi variabel untuk menghindari undefined error
$totalFiles = 0;
$malwareFiles = 0;
$cleanFiles = 0;

// Gunakan direktori saat ini jika DOCUMENT_ROOT tidak tersedia
$root_dir = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '.';

// Batasi jumlah file yang diproses untuk mencegah timeout
$file_limit = 5000;
$daptar = daptar_berkas($root_dir);

// Batasi jumlah file yang diproses
if (count($daptar) > $file_limit) {
    $daptar = array_slice($daptar, 0, $file_limit);
}

// Hitung jumlah file terinfeksi dan aman
foreach ($daptar as $file) {
    if (is_file($file)) {
        $konten = maca($file);
        $cek = $konten !== false ? mariosan($konten) : [];
        
        if (empty($cek)) {
            $cleanFiles++;
        } else {
            $malwareFiles++;
        }
        $totalFiles++;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>SukaBintang01 :: Scanner</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&display=swap');

    :root {
        --primary-color: #0f0;
        --secondary-color: #00ff9d;
        --bg-color: #0a0a0a;
        --card-bg: rgba(12, 12, 12, 0.9);
        --border-color: #0f0;
        --danger-color: #ff3e3e;
        --safe-color: #0f0;
        --header-color: #00ffe1;
        --text-color: #cbcbcb;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body { 
        font-family: 'Share Tech Mono', monospace;
        text-align: center; 
        background-color: var(--bg-color);
        color: var(--text-color);
        margin: 0;
        padding: 0;
        background-image: 
            radial-gradient(rgba(0, 255, 0, 0.1) 2px, transparent 2px),
            radial-gradient(rgba(0, 255, 0, 0.1) 2px, transparent 2px);
        background-size: 50px 50px;
        background-position: 0 0, 25px 25px;
        line-height: 1.6;
    }

    .container {
        max-width: 1200px;
        margin: 20px auto;
        padding: 0 15px;
    }

    .header {
        margin-bottom: 30px;
        position: relative;
        padding: 20px;
        border: 1px solid var(--border-color);
        border-radius: 5px;
        background-color: var(--card-bg);
        box-shadow: 0 0 10px rgba(0, 255, 0, 0.2);
    }

    .header::before {
        content: "";
        position: absolute;
        top: 0px;
        left: 0px;
        right: 0px;
        height: 0px;
        background: linear-gradient(90deg, transparent, var(--primary-color), transparent);
        animation: scanner-line 4s linear infinite;
    }

    @keyframes scanner-line {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }

    .title {
        color: var(--header-color);
        font-family: 'Rajdhani', sans-serif;
        font-weight: 700;
        font-size: 2.5rem;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 5px;
        text-shadow: 0 0 5px var(--header-color);
    }

    .subtitle {
        color: var(--secondary-color);
        font-size: 1rem;
        margin-bottom: 15px;
        opacity: 0.7;
    }

    .pulse {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--primary-color);
        margin-right: 10px;
        animation: pulse 1.5s ease infinite;
    }

    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(0, 255, 0, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(0, 255, 0, 0); }
        100% { box-shadow: 0 0 0 0 rgba(0, 255, 0, 0); }
    }

    .status-bar {
        display: flex;
        justify-content: space-between;
        margin-bottom: 20px;
        padding: 10px;
        border: 1px solid rgba(0, 255, 0, 0.2);
        border-radius: 3px;
        background-color: rgba(10, 10, 10, 0.8);
        font-size: 0.8rem;
    }

    .table-container {
        margin-top: 20px;
        border: 1px solid rgba(0, 255, 0, 0.3);
        border-radius: 5px;
        overflow: hidden;
        background-color: var(--card-bg);
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background-color: transparent;
        color: var(--text-color);
        font-size: 0.85rem;
    }

    th {
        background-color: rgba(0, 255, 0, 0.1);
        color: var(--header-color);
        padding: 12px 15px;
        text-align: left;
        font-weight: bold;
        border-bottom: 1px solid rgba(0, 255, 0, 0.2);
    }

    td {
        padding: 10px 15px;
        text-align: left;
        border-bottom: 1px solid rgba(0, 255, 0, 0.1);
        position: relative;
        max-width: 300px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    tr:hover {
        background-color: rgba(0, 255, 0, 0.05);
    }

    .aman { 
        color: var(--safe-color);
        text-shadow: 0 0 3px rgba(0, 255, 0, 0.5);
    }

    .bahaya { 
        color: var(--danger-color);
        text-shadow: 0 0 3px rgba(255, 0, 0, 0.5);
    }

    .btn {
        display: inline-block;
        padding: 5px 12px;
        margin: 0 5px;
        cursor: pointer;
        border: 1px solid;
        background-color: transparent;
        color: var(--text-color);
        font-family: 'Share Tech Mono', monospace;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        text-transform: uppercase;
        border-radius: 3px;
    }

    .btn:hover {
        box-shadow: 0 0 10px currentColor;
    }

    .btn-view {
        border-color: #0088ff;
        color: #0088ff;
    }

    .btn-delete {
        border-color: var(--danger-color);
        color: var(--danger-color);
    }

    .console {
        margin-top: 20px;
        padding: 15px;
        border: 1px solid rgba(0, 255, 0, 0.3);
        border-radius: 5px;
        background-color: rgba(10, 10, 10, 0.8);
        color: var(--primary-color);
        font-size: 0.85rem;
        text-align: left;
        max-height: 200px;
        overflow-y: auto;
    }

    .console-output {
        white-space: pre-wrap;
        word-break: break-all;
    }

    .console-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        border-bottom: 1px solid rgba(0, 255, 0, 0.2);
        padding-bottom: 5px;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.9);
        z-index: 1000;
        overflow: auto;
    }

    .modal-content {
        background-color: var(--card-bg);
        margin: 30px auto;
        padding: 20px;
        border: 1px solid var(--border-color);
        box-shadow: 0 0 20px rgba(0, 255, 0, 0.3);
        width: 80%;
        max-width: 1000px;
        border-radius: 5px;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(0, 255, 0, 0.2);
        margin-bottom: 15px;
    }

    .modal-title {
        color: var(--header-color);
        font-size: 1.2rem;
        word-break: break-all;
    }

    .close {
        color: var(--danger-color);
        font-size: 1.5rem;
        cursor: pointer;
        margin-left: 10px;
    }

    .modal-body {
        max-height: 70vh;
        overflow-y: auto;
        background-color: rgba(10, 10, 10, 0.8);
        padding: 15px;
        border-radius: 3px;
        border: 1px solid rgba(0, 255, 0, 0.1);
    }

    .modal-body pre {
        color: var(--text-color);
        font-family: 'Share Tech Mono', monospace;
        font-size: 0.85rem;
        white-space: pre-wrap;
        word-break: break-all;
    }

    #stats {
        display: flex;
        justify-content: space-around;
        flex-wrap: wrap;
        margin: 20px 0;
    }

    .stat-box {
        background-color: var(--card-bg);
        border: 1px solid rgba(0, 255, 0, 0.2);
        border-radius: 5px;
        padding: 15px;
        width: 30%;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
        margin-bottom: 10px;
    }

    .stat-title {
        color: var(--secondary-color);
        font-size: 0.9rem;
        margin-bottom: 10px;
    }

    .stat-value {
        color: var(--primary-color);
        font-size: 1.5rem;
        font-weight: bold;
    }

    /* Typewriter effect */
    .typewriter {
        overflow: hidden;
        border-right: 1px solid var(--primary-color);
        white-space: nowrap;
        margin: 0 auto;
        letter-spacing: 1px;
        animation: typing 3.5s steps(40, end), blink-caret 0.75s step-end infinite;
    }

    @keyframes typing {
        from { width: 0 }
        to { width: 100% }
    }

    @keyframes blink-caret {
        from, to { border-color: transparent }
        50% { border-color: var(--primary-color) }
    }

    /* Termination */
    .terminate {
        position: fixed;
        bottom: 10px;
        right: 10px;
        color: var(--text-color);
        font-size: 0.8rem;
        opacity: 0.7;
    }

    .limited-height {
        max-height: 500px;
        overflow-y: auto;
    }

    .path-cell {
        max-width: 400px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .path-cell:hover {
        overflow: visible;
        white-space: normal;
        background-color: var(--card-bg);
        position: relative;
        z-index: 1;
    }

    .loading {
        display: none;
        margin: 20px auto;
        text-align: center;
        color: var(--secondary-color);
    }

    .loading i {
        animation: spin 1s infinite linear;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .error-message {
        color: var(--danger-color);
        padding: 10px;
        margin: 10px 0;
        border: 1px solid rgba(255, 0, 0, 0.3);
        border-radius: 3px;
        background-color: rgba(255, 0, 0, 0.1);
    }

    @media screen and (max-width: 768px) {
        .stat-box {
            width: 100%;
            margin-bottom: 10px;
        }
        
        #stats {
            flex-direction: column;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        td {
            max-width: 150px;
        }
        
        .status-bar {
            flex-direction: column;
        }
        
        .status-bar div {
            margin-bottom: 5px;
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title"><i class="fas fa-radiation"></i> SukaBintang01 Scanner</h1>
            <p class="subtitle">Advanced PHP Malware Detection System</p>
            <div class="status-bar">
                <div><span class="pulse"></span> System: <?php echo function_exists('php_uname') ? php_uname('s') . ' ' . php_uname('r') : 'Unknown'; ?></div>
                <div>Process ID: <?php echo function_exists('getmypid') ? getmypid() : 'N/A'; ?></div>
            </div>
        </div>
        
        <div id="stats">
            <div class="stat-box">
                <div class="stat-title">TOTAL FILES</div>
                <div class="stat-value"><?php echo $totalFiles; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-title">CLEAN FILES</div>
                <div class="stat-value"><?php echo $cleanFiles; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-title">INFECTED FILES</div>
                <div class="stat-value"><?php echo $malwareFiles; ?></div>
            </div>
        </div>
        
        <?php if (count($daptar) > $file_limit): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-triangle"></i> Showing only first <?php echo $file_limit; ?> files to prevent timeout.
        </div>
        <?php endif; ?>
        
        <div class="table-container">
            <div class="limited-height">
                <table>
                    <thead>
                        <tr>
                            <th width="60%">FILE PATH</th>
                            <th width="25%">STATUS</th>
                            <th width="15%">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 0;
                        foreach ($daptar as $nilai): 
                            if (is_file($nilai)): 
                                $konten = maca($nilai); 
                                $cek = $konten !== false ? mariosan($konten) : [];
                        ?>
                        <tr>
                            <td class="path-cell"><?php echo htmlspecialchars($nilai, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class='<?php echo empty($cek) ? "aman" : "bahaya"; ?>'>
                                <?php 
                                if (empty($cek)) {
                                    echo "<i class='fas fa-check-circle'></i> CLEAN";
                                } else {
                                    echo "<i class='fas fa-biohazard'></i> INFECTED (" . htmlspecialchars(implode(", ", $cek), ENT_QUOTES, 'UTF-8') . ")";
                                }
                                ?>
                            </td>
                            <td>
                                <button class='btn btn-view' onclick='viewFile("<?php echo addslashes(htmlspecialchars($nilai, ENT_QUOTES, 'UTF-8')); ?>")'><i class='fas fa-eye'></i> View</button>
                                <?php if (!empty($cek)): ?>
                                <button class='btn btn-delete' onclick='deleteFile("<?php echo addslashes(htmlspecialchars($nilai, ENT_QUOTES, 'UTF-8')); ?>")'><i class='fas fa-trash'></i> Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                            endif; 
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div id="loading" class="loading">
            <i class="fas fa-circle-notch fa-spin"></i> Processing...
        </div>
        
        <div class="console">
            <div class="console-header">
                <span>Console Output</span>
                <span id="console-time"><?php echo date('H:i:s'); ?></span>
            </div>
            <div class="console-output" id="console-output">
                <span class="typewriter">[+] Scan initialized at <?php echo date('Y-m-d H:i:s'); ?></span>
                <br>[+] Root directory: <?php echo htmlspecialchars($root_dir, ENT_QUOTES, 'UTF-8'); ?>
                <br>[+] Found <?php echo $totalFiles; ?> PHP files
                <br>[+] Detected <?php echo $malwareFiles; ?> potentially malicious files
                <br>[+] System ready. Awaiting commands...
            </div>
        </div>
    </div>
    
    <div id="fileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title" id="modal-file-name">File Content</span>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="modal-content">
                <pre>Loading...</pre>
            </div>
        </div>
    </div>
    
    <div class="terminate">
        <span>[<?php echo date('Y-m-d'); ?>] SukaBintang01 Scanner v2.0</span>
    </div>
    
    <script>
        // Modal functions
        function viewFile(file) {
            document.getElementById('fileModal').style.display = 'block';
            document.getElementById('modal-file-name').textContent = 'File: ' + file;
            document.getElementById('modal-content').innerHTML = '<pre>Loading file content...</pre>';
            document.getElementById('loading').style.display = 'block';
            
            logToConsole('Viewing file: ' + file);
            
            fetch('?aksi=tinggali&berkas=' + encodeURIComponent(file))
            .then(response => response.text())
            .then(data => {
                document.getElementById('modal-content').innerHTML = data;
                document.getElementById('loading').style.display = 'none';
            })
            .catch(error => {
                document.getElementById('modal-content').innerHTML = '<pre class="bahaya">Error loading file content!</pre>';
                logToConsole('Error: Failed to load file content');
                document.getElementById('loading').style.display = 'none';
            });
        }
        
        function closeModal() {
            document.getElementById('fileModal').style.display = 'none';
        }
        
        // Click outside modal to close
        window.onclick = function(event) {
            if (event.target == document.getElementById('fileModal')) {
                closeModal();
            }
        }
        
        function deleteFile(file) {
            if (confirm('Are you sure you want to delete: ' + file + '?')) {
                logToConsole('Attempting to delete: ' + file);
                document.getElementById('loading').style.display = 'block';
                
                fetch('?aksi=hapus&berkas=' + encodeURIComponent(file))
                .then(response => response.text())
                .then(data => {
                    alert(data);
                    logToConsole('Delete operation result: ' + data);
                    document.getElementById('loading').style.display = 'none';
                    // Reload page to refresh file list
                    window.location.reload();
                })
                .catch(error => {
                    alert('Failed to delete file!');
                    logToConsole('Error: Delete operation failed');
                    document.getElementById('loading').style.display = 'none';
                });
            }
        }
        
        // Console logger
        function logToConsole(message) {
            const time = new Date().toTimeString().split(' ')[0];
            const console = document.getElementById('console-output');
            console.innerHTML += '<br>[' + time + '] ' + message;
            document.getElementById('console-time').textContent = time;
            
            // Auto-scroll to bottom
            console.scrollTop = console.scrollHeight;
        }
        
        // Update console time
        setInterval(function() {
            document.getElementById('console-time').textContent = new Date().toTimeString().split(' ')[0];
        }, 1000);
        
        // Error handling for fetch
        window.addEventListener('error', function(e) {
            logToConsole('Error occurred: ' + e.message);
        });
        
        // Document loaded
        document.addEventListener('DOMContentLoaded', function() {
            logToConsole('Scanner interface loaded successfully');
        });
    </script>
</body>
</html>
