<?php
// ============ 系统监控核心逻辑 ==========
error_reporting(0);

// ========== 会话启动与登录验证 ==========
session_start();

$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';
$is_api  = isset($_GET['api']) && $_GET['api'] == '1';
$authorized = false;

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $authorized = true;
}

// 未登录时的处理
if (!$authorized) {
    if ($is_ajax || $is_api) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['code' => -1, 'msg' => '未授权访问']);
        exit;
    } else {
        // 显示登录页面
        showLoginPage();
        exit;
    }
}

// ========== 登录页面输出函数 ==========
function showLoginPage() {
    $error = isset($_GET['error']) ? '用户名或密码错误，请重试。' : '';
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>KonT - 登录</title>
        <link rel="icon" type="image/jpeg" href="kontb.jpg">
       <link rel="shortcut icon" type="image/jpeg" href="kontb.jpg">
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                background: #0d0d0d;
                color: #f5f5f7;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .login-container {
                background: rgba(28, 28, 30, 0.85);
                backdrop-filter: blur(16px);
                border: 1px solid rgba(255, 255, 255, 0.06);
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
                padding: 40px 32px;
                max-width: 400px;
                width: 100%;
                border-radius: 12px;
            }
            .login-container h1 {
                font-size: 24px;
                font-weight: 600;
                margin-bottom: 8px;
                letter-spacing: 0.5px;
                color: #f5f5f7;
            }
            .login-container .sub {
                color: #8e8e93;
                font-size: 14px;
                margin-bottom: 28px;
            }
            .login-container .error-msg {
                background: rgba(244, 67, 54, 0.15);
                color: #f44336;
                padding: 10px 14px;
                border-radius: 6px;
                margin-bottom: 20px;
                font-size: 14px;
                display: <?php echo $error ? 'block' : 'none'; ?>;
            }
            .login-container label {
                display: block;
                font-size: 13px;
                color: #8e8e93;
                margin-bottom: 4px;
            }
            .login-container input[type="text"],
            .login-container input[type="password"] {
                width: 100%;
                background: #3a3a3c;
                border: 1px solid rgba(255, 255, 255, 0.08);
                color: #f5f5f7;
                padding: 12px 16px;
                border-radius: 6px;
                font-size: 15px;
                outline: none;
                transition: border-color 0.2s, box-shadow 0.2s;
                margin-bottom: 18px;
            }
            .login-container input:focus {
                border-color: #0a84ff;
                box-shadow: 0 0 0 3px rgba(10, 132, 255, 0.25);
            }
            .login-container button {
                width: 100%;
                background: #0a84ff;
                border: none;
                color: #fff;
                font-size: 16px;
                font-weight: 500;
                padding: 14px 0;
                border-radius: 6px;
                cursor: pointer;
                transition: background 0.2s, transform 0.1s;
            }
            .login-container button:hover {
                background: #2f97ff;
            }
            .login-container button:active {
                transform: scale(0.97);
            }
            .login-container .footer {
                margin-top: 20px;
                text-align: center;
                font-size: 13px;
                color: #636366;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h1>🔐 登录控制面板</h1>
            <div class="sub">请输入您的凭证</div>
            <div class="error-msg" id="loginError"><?php echo htmlspecialchars($error); ?></div>
            <form action="konz.php" method="POST" autocomplete="off">
                <input type="hidden" name="action" value="login">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" placeholder="请输入用户名" required autofocus>
                <label for="password">密码</label>
                <input type="password" id="password" name="password" placeholder="请输入密码" required>
                <button type="submit">登 录</button>
        <script>
            // 如果 URL 带有 error 参数，显示错误信息
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('error')) {
                document.getElementById('loginError').style.display = 'block';
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ========== 以下为原有逻辑（仅当登录后执行） ==========
$root_path = '/var/www/html';

// ========== 图标映射 ==========
function getFileIcon($filename, $is_dir = false) {
    if ($is_dir) return '📁';
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $map = [
        'php' => '📘', 'html' => '📦', 'htm' => '📦',
        'css' => '🎨', 'js' => '📜', 'json' => '📋',
        'md' => '📓', 'py' => '🐍', 'sh' => '⚙️',
        'conf' => '⚙️', 'ini' => '⚙️', 'log' => '📋',
        'txt' => '📝', 'jpg' => '🖼️', 'jpeg' => '🖼️',
        'png' => '🖼️', 'gif' => '🖼️', 'bmp' => '🖼️',
        'webp' => '🖼️', 'svg' => '🖼️',
        'zip' => '🟩', 'rar' => '🟩', '7z' => '🟩', 'tar' => '🟩', 'gz' => '🟩',
        'mp3' => '🎵', 'wav' => '🎵', 'flac' => '🎵',
        'mp4' => '🎬', 'avi' => '🎬', 'mkv' => '🎬',
        'exe' => '⚙️', 'msi' => '⚙️',
        'pdf' => '📕', 'xls' => '📊', 'xlsx' => '📊'
    ];
    return isset($map[$ext]) ? $map[$ext] : '📄';
}

// ========== 构建目录树文本（用于下载结构树） ==========
function buildTreeText($dir, $filters, $prefix = '', $isLast = true) {
    $items = array_diff(scandir($dir), ['.', '..']);
    if (empty($items)) return '';

    $dirs  = [];
    $files = [];
    foreach ($items as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            $dirs[] = $item;
        } else {
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if (!in_array($ext, $filters)) {
                $files[] = $item;
            }
        }
    }
    sort($dirs);
    sort($files);

    $all = array_merge($dirs, $files);
    $count = count($all);
    $output = '';

    foreach ($all as $index => $name) {
        $isLastItem = ($index === $count - 1);
        $connector = $isLastItem ? '└── ' : '├── ';
        $output .= $prefix . $connector . $name . "\n";

        $fullPath = $dir . DIRECTORY_SEPARATOR . $name;
        if (is_dir($fullPath)) {
            $newPrefix = $prefix . ($isLastItem ? '    ' : '│   ');
            $output .= buildTreeText($fullPath, $filters, $newPrefix, $isLastItem);
        }
    }
    return $output;
}

// ========== 下载结构树处理 ==========
if (isset($_GET['downloadTree'])) {
    $filteredExtensions = [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'mp4', 'webm', 'avi',
        'mkv', 'mov', 'flv', 'json', 'exe', 'dmg', 'zip', 'rar', '7z',
        'wav', 'flac', 'mp3', 'aac', 'ogg', 'txt', 'log'
    ];

    $treeText  = "服务器网站整体结构树\n";
    $treeText .= "生成时间：" . date('Y-m-d H:i:s') . "\n";
    $treeText .= "根目录：" . $root_path . "\n";
    $treeText .= "过滤扩展名：" . implode(', ', $filteredExtensions) . "\n\n";
    $treeText .= buildTreeText($root_path, $filteredExtensions);

    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="服务器网站整体结构树.txt"');
    header('Content-Length: ' . strlen($treeText));
    echo $treeText;
    exit;
}

// ---------- 纯PHP ZIP构建类（无压缩） ----------
class SimpleZip {
    private $fp;
    private $centralDir = [];
    private $offset = 0;

    public function __construct() {
        $this->fp = fopen('php://temp', 'r+');
    }

    public function addFile($realPath, $relativePath) {
        $data = file_get_contents($realPath);
        $crc = crc32($data);
        $size = strlen($data);
        $compressed = $data;
        $compSize = $size;

        $localHeader = pack('VvvvvVVVVvv',
            0x04034b50,
            0x000A,
            0x0000,
            0x0000,
            0x0000,
            0x0000,
            $crc,
            $compSize,
            $size,
            strlen($relativePath),
            0
        );
        $localHeader .= $relativePath;

        fwrite($this->fp, $localHeader);
        fwrite($this->fp, $compressed);

        $this->centralDir[] = [
            'header' => pack('VvvvvVVVVvvVVVvv',
                0x02014b50,
                0x000A,
                0x000A,
                0x0000,
                0x0000,
                0x0000,
                0x0000,
                $crc,
                $compSize,
                $size,
                strlen($relativePath),
                0,
                0,
                0,
                0,
                0,
                $this->offset
            ),
            'name' => $relativePath
        ];

        $this->offset += strlen($localHeader) + $compSize;
    }

    public function output() {
        $centralStart = $this->offset;
        $centralData = '';
        foreach ($this->centralDir as $entry) {
            $centralData .= $entry['header'] . $entry['name'];
        }
        fwrite($this->fp, $centralData);

        $centralSize = strlen($centralData);
        $entries = count($this->centralDir);

        $end = pack('VvvvvVVv',
            0x06054b50,
            0x0000,
            0x0000,
            $entries,
            $entries,
            $centralSize,
            $centralStart,
            0
        );
        fwrite($this->fp, $end);

        fseek($this->fp, 0);
        $content = stream_get_contents($this->fp);
        fclose($this->fp);
        return $content;
    }
}

function create_zip_from_dir($dir, $root_path) {
    $dir = realpath($dir);
    $root = realpath($root_path);
    $zip = new SimpleZip();

    if (!is_dir($dir)) {
        return '';
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $relative = substr($file->getPathname(), strlen($dir) + 1);
            if (strpos($relative, '..') !== false) continue;
            $zip->addFile($file->getPathname(), $relative);
        }
    }
    return $zip->output();
}

// ---------- 下载处理 ----------
if (isset($_GET['download']) && $_GET['download'] == '1') {
    $path = isset($_GET['path']) ? $_GET['path'] : '';
    if (empty($path)) {
        http_response_code(400);
        exit('路径为空');
    }

    $real_root = realpath($root_path);
    $real_path = realpath($path);
    if ($real_path === false || strpos($real_path, $real_root) !== 0) {
        http_response_code(403);
        exit('非法路径');
    }

    if (is_file($real_path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($real_path) . '"');
        header('Content-Length: ' . filesize($real_path));
        readfile($real_path);
        exit;
    } elseif (is_dir($real_path)) {
        $zipData = create_zip_from_dir($real_path, $root_path);
        $zipName = basename($real_path) . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . strlen($zipData));
        echo $zipData;
        exit;
    } else {
        http_response_code(404);
        exit('路径不存在');
    }
}

// ---------- 上传处理 ----------
if (isset($_GET['upload']) && $_GET['upload'] == '1') {
    header('Content-Type: application/json');
    $target_dir = isset($_POST['target_path']) ? $_POST['target_path'] : $root_path;
    $real_root = realpath($root_path);
    $real_target = realpath($target_dir);
    if ($real_target === false || strpos($real_target, $real_root) !== 0) {
        echo json_encode(['code' => -1, 'msg' => '非法目标目录']);
        exit;
    }
    if (!is_dir($real_target)) {
        echo json_encode(['code' => -1, 'msg' => '目标目录不存在']);
        exit;
    }

    $uploaded = [];
    $errors = [];
    foreach ($_FILES as $key => $file) {
        if (is_array($file['name'])) {
            foreach ($file['name'] as $idx => $name) {
                if ($file['error'][$idx] === UPLOAD_ERR_OK) {
                    $tmp = $file['tmp_name'][$idx];
                    $dest = $real_target . '/' . basename($name);
                    if (move_uploaded_file($tmp, $dest)) {
                        $uploaded[] = $name;
                    } else {
                        $errors[] = $name . ' 移动失败';
                    }
                } else {
                    $errors[] = $name . ' 上传错误码: ' . $file['error'][$idx];
                }
            }
        } else {
            if ($file['error'] === UPLOAD_ERR_OK) {
                $dest = $real_target . '/' . basename($file['name']);
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $uploaded[] = $file['name'];
                } else {
                    $errors[] = $file['name'] . ' 移动失败';
                }
            } else {
                $errors[] = $file['name'] . ' 上传错误码: ' . $file['error'];
            }
        }
    }
    echo json_encode(['code' => 0, 'msg' => '上传完成', 'uploaded' => $uploaded, 'errors' => $errors]);
    exit;
}

// ---------- 删除处理 ----------
if (isset($_GET['delete']) && $_GET['delete'] == '1') {
    header('Content-Type: application/json');
    $path = isset($_GET['path']) ? $_GET['path'] : '';
    if (empty($path)) {
        echo json_encode(['code' => -1, 'msg' => '路径为空']);
        exit;
    }
    $real_root = realpath($root_path);
    $real_path = realpath($path);
    if ($real_path === false || strpos($real_path, $real_root) !== 0) {
        echo json_encode(['code' => -1, 'msg' => '非法路径']);
        exit;
    }
    if (!file_exists($real_path)) {
        echo json_encode(['code' => -1, 'msg' => '文件不存在']);
        exit;
    }
    if (is_dir($real_path)) {
        echo json_encode(['code' => -1, 'msg' => '暂不支持删除目录']);
        exit;
    }
    if (unlink($real_path)) {
        echo json_encode(['code' => 0, 'msg' => '删除成功']);
    } else {
        @chmod($real_path, 0666);
        if (unlink($real_path)) {
            echo json_encode(['code' => 0, 'msg' => '删除成功（权限已修正）']);
        } else {
            echo json_encode(['code' => -1, 'msg' => '删除失败，请检查文件权限']);
        }
    }
    exit;
}

// ========== 获取文件内容 ==========
if (isset($_GET['getcontent']) && $_GET['getcontent'] == '1') {
    header('Content-Type: application/json');
    $path = isset($_GET['path']) ? $_GET['path'] : '';
    if (empty($path)) {
        echo json_encode(['code' => -1, 'msg' => '路径为空']);
        exit;
    }
    $real_root = realpath($root_path);
    $real_path = realpath($path);
    if ($real_path === false || strpos($real_path, $real_root) !== 0) {
        echo json_encode(['code' => -1, 'msg' => '非法路径']);
        exit;
    }
    if (!is_file($real_path)) {
        echo json_encode(['code' => -1, 'msg' => '不是文件']);
        exit;
    }
    $ext = strtolower(pathinfo($real_path, PATHINFO_EXTENSION));
    $allowed = ['html', 'htm', 'php', 'css', 'js', 'txt', 'json', 'xml', 'md', 'py', 'sh', 'conf', 'ini', 'log'];
    if (!in_array($ext, $allowed)) {
        echo json_encode(['code' => -1, 'msg' => '不允许编辑此类型文件']);
        exit;
    }
    $content = file_get_contents($real_path);
    echo json_encode(['code' => 0, 'content' => $content]);
    exit;
}

// ========== 保存文件内容 ==========
if (isset($_GET['save']) && $_GET['save'] == '1') {
    header('Content-Type: application/json');
    $path = isset($_POST['path']) ? $_POST['path'] : '';
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    if (empty($path)) {
        echo json_encode(['code' => -1, 'msg' => '路径为空']);
        exit;
    }
    $real_root = realpath($root_path);
    $real_path = realpath($path);
    if ($real_path === false || strpos($real_path, $real_root) !== 0) {
        echo json_encode(['code' => -1, 'msg' => '非法路径']);
        exit;
    }
    if (!is_file($real_path)) {
        echo json_encode(['code' => -1, 'msg' => '不是文件']);
        exit;
    }
    $ext = strtolower(pathinfo($real_path, PATHINFO_EXTENSION));
    $allowed = ['html', 'htm', 'php', 'css', 'js', 'txt', 'json', 'xml', 'md', 'py', 'sh', 'conf', 'ini', 'log'];
    if (!in_array($ext, $allowed)) {
        echo json_encode(['code' => -1, 'msg' => '不允许编辑此类型文件']);
        exit;
    }
    if (file_put_contents($real_path, $content) !== false) {
        echo json_encode(['code' => 0, 'msg' => '保存成功']);
    } else {
        @chmod($real_path, 0666);
        if (file_put_contents($real_path, $content) !== false) {
            echo json_encode(['code' => 0, 'msg' => '保存成功（权限已修正）']);
        } else {
            echo json_encode(['code' => -1, 'msg' => '保存失败，请检查文件权限']);
        }
    }
    exit;
}

// ========== 新建文件 ==========
if (isset($_GET['action']) && $_GET['action'] == 'create_file') {
    header('Content-Type: application/json');
    $dir = isset($_POST['dir']) ? $_POST['dir'] : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    if (empty($dir) || empty($name)) {
        echo json_encode(['code' => -1, 'msg' => '目录或文件名为空']);
        exit;
    }
    $real_root = realpath($root_path);
    $real_dir = realpath($dir);
    if ($real_dir === false || strpos($real_dir, $real_root) !== 0) {
        echo json_encode(['code' => -1, 'msg' => '非法目录']);
        exit;
    }
    if (!is_dir($real_dir)) {
        echo json_encode(['code' => -1, 'msg' => '目标目录不存在']);
        exit;
    }
    if (strpos($name, '/') !== false || strpos($name, '..') !== false) {
        echo json_encode(['code' => -1, 'msg' => '文件名包含非法字符']);
        exit;
    }
    $filepath = $real_dir . '/' . $name;
    if (file_exists($filepath)) {
        echo json_encode(['code' => -1, 'msg' => '文件已存在']);
        exit;
    }
    if (file_put_contents($filepath, $content) !== false) {
        echo json_encode(['code' => 0, 'msg' => '创建成功']);
    } else {
        @chmod($real_dir, 0777);
        if (file_put_contents($filepath, $content) !== false) {
            echo json_encode(['code' => 0, 'msg' => '创建成功（权限已修正）']);
        } else {
            echo json_encode(['code' => -1, 'msg' => '创建失败，请检查目录权限']);
        }
    }
    exit;
}

// ========== 新建文件夹 ==========
if (isset($_GET['action']) && $_GET['action'] == 'create_folder') {
    header('Content-Type: application/json');
    $dir = isset($_POST['dir']) ? $_POST['dir'] : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    if (empty($dir) || empty($name)) {
        echo json_encode(['code' => -1, 'msg' => '目录或文件夹名为空']);
        exit;
    }
    $real_root = realpath($root_path);
    $real_dir = realpath($dir);
    if ($real_dir === false || strpos($real_dir, $real_root) !== 0) {
        echo json_encode(['code' => -1, 'msg' => '非法目录']);
        exit;
    }
    if (!is_dir($real_dir)) {
        echo json_encode(['code' => -1, 'msg' => '目标目录不存在']);
        exit;
    }
    if (strpos($name, '/') !== false || strpos($name, '..') !== false) {
        echo json_encode(['code' => -1, 'msg' => '文件夹名包含非法字符']);
        exit;
    }
    $folderpath = $real_dir . '/' . $name;
    if (file_exists($folderpath)) {
        echo json_encode(['code' => -1, 'msg' => '文件夹已存在']);
        exit;
    }
    if (mkdir($folderpath, 0755)) {
        echo json_encode(['code' => 0, 'msg' => '创建成功']);
    } else {
        @chmod($real_dir, 0777);
        if (mkdir($folderpath, 0755)) {
            echo json_encode(['code' => 0, 'msg' => '创建成功（权限已修正）']);
        } else {
            echo json_encode(['code' => -1, 'msg' => '创建失败，请检查目录权限']);
        }
    }
    exit;
}

// ========== 预览文件 ==========
if (isset($_GET['preview']) && $_GET['preview'] == '1') {
    $path = isset($_GET['path']) ? $_GET['path'] : '';
    if (empty($path)) {
        http_response_code(400);
        exit('路径为空');
    }
    $real_root = realpath($root_path);
    $real_path = realpath($path);
    if ($real_path === false || strpos($real_path, $real_root) !== 0) {
        http_response_code(403);
        exit('非法路径');
    }
    if (!is_file($real_path)) {
        http_response_code(404);
        exit('不是文件');
    }
    $ext = strtolower(pathinfo($real_path, PATHINFO_EXTENSION));
    $image_exts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
    if (in_array($ext, $image_exts)) {
        header('Content-Type: image/' . ($ext === 'svg' ? 'svg+xml' : $ext));
        readfile($real_path);
        exit;
    } else {
        $size = filesize($real_path);
        if ($size > 2 * 1024 * 1024) {
            http_response_code(413);
            exit('文件过大，无法预览');
        }
        $content = file_get_contents($real_path);
        if (strpos($content, "\0") !== false) {
            http_response_code(415);
            exit('无法预览二进制文件');
        }
        header('Content-Type: text/plain; charset=utf-8');
        echo $content;
        exit;
    }
}

// ========== 复制文件/文件夹 ==========
if (isset($_GET['action']) && $_GET['action'] == 'copy') {
    header('Content-Type: application/json');
    set_time_limit(0);
    $source = isset($_POST['source']) ? $_POST['source'] : '';
    $target_dir = isset($_POST['target_dir']) ? $_POST['target_dir'] : '';
    if (empty($source) || empty($target_dir)) {
        echo json_encode(['code' => -1, 'msg' => '源路径或目标目录为空']);
        exit;
    }
    $real_root = realpath($root_path);
    $real_source = realpath($source);
    $real_target_dir = realpath($target_dir);
    if ($real_source === false || strpos($real_source, $real_root) !== 0) {
        echo json_encode(['code' => -1, 'msg' => '非法源路径']);
        exit;
    }
    if ($real_target_dir === false || strpos($real_target_dir, $real_root) !== 0) {
        echo json_encode(['code' => -1, 'msg' => '非法目标目录']);
        exit;
    }
    if (!file_exists($real_source)) {
        echo json_encode(['code' => -1, 'msg' => '源路径不存在']);
        exit;
    }
    if (!is_dir($real_target_dir)) {
        echo json_encode(['code' => -1, 'msg' => '目标目录不存在']);
        exit;
    }
    $dest_path = $real_target_dir . '/' . basename($real_source);
    if (file_exists($dest_path)) {
        echo json_encode(['code' => -1, 'msg' => '目标已存在，请重命名或删除']);
        exit;
    }

    function copy_recursive($src, $dst) {
        if (!is_dir($src)) {
            return copy($src, $dst);
        }
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if ($file == '.' || $file == '..') continue;
            $src_file = $src . '/' . $file;
            $dst_file = $dst . '/' . $file;
            if (is_dir($src_file)) {
                copy_recursive($src_file, $dst_file);
            } else {
                copy($src_file, $dst_file);
            }
        }
        closedir($dir);
        return true;
    }

    if (copy_recursive($real_source, $dest_path)) {
        echo json_encode(['code' => 0, 'msg' => '复制成功']);
    } else {
        echo json_encode(['code' => -1, 'msg' => '复制失败']);
    }
    exit;
}

// ========== 移动文件/文件夹 ==========
if (isset($_GET['action']) && $_GET['action'] == 'move') {
    header('Content-Type: application/json');
    set_time_limit(0);
    $source = isset($_POST['source']) ? $_POST['source'] : '';
    $target_dir = isset($_POST['target_dir']) ? $_POST['target_dir'] : '';
    if (empty($source) || empty($target_dir)) {
        echo json_encode(['code' => -1, 'msg' => '源路径或目标目录为空']);
        exit;
    }
    $real_root = realpath($root_path);
    $real_source = realpath($source);
    $real_target_dir = realpath($target_dir);
    if ($real_source === false || strpos($real_source, $real_root) !== 0) {
        echo json_encode(['code' => -1, 'msg' => '非法源路径']);
        exit;
    }
    if ($real_target_dir === false || strpos($real_target_dir, $real_root) !== 0) {
        echo json_encode(['code' => -1, 'msg' => '非法目标目录']);
        exit;
    }
    if (!file_exists($real_source)) {
        echo json_encode(['code' => -1, 'msg' => '源路径不存在']);
        exit;
    }
    if (!is_dir($real_target_dir)) {
        echo json_encode(['code' => -1, 'msg' => '目标目录不存在']);
        exit;
    }
    $dest_path = $real_target_dir . '/' . basename($real_source);
    if (file_exists($dest_path)) {
        echo json_encode(['code' => -1, 'msg' => '目标已存在，请重命名或删除']);
        exit;
    }

    function deltree($dir) {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) deltree($path);
            else unlink($path);
        }
        return rmdir($dir);
    }

    function copy_recursive2($src, $dst) {
        if (!is_dir($src)) {
            return copy($src, $dst);
        }
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if ($file == '.' || $file == '..') continue;
            $src_file = $src . '/' . $file;
            $dst_file = $dst . '/' . $file;
            if (is_dir($src_file)) {
                copy_recursive2($src_file, $dst_file);
            } else {
                copy($src_file, $dst_file);
            }
        }
        closedir($dir);
        return true;
    }

    if (copy_recursive2($real_source, $dest_path)) {
        if (is_dir($real_source)) {
            deltree($real_source);
        } else {
            unlink($real_source);
        }
        echo json_encode(['code' => 0, 'msg' => '移动成功']);
    } else {
        echo json_encode(['code' => -1, 'msg' => '移动失败']);
    }
    exit;
}

// ========== 重命名 ==========
if (isset($_GET['action']) && $_GET['action'] == 'rename') {
    header('Content-Type: application/json');
    $path = isset($_POST['path']) ? $_POST['path'] : '';
    $new_name = isset($_POST['new_name']) ? trim($_POST['new_name']) : '';
    if (empty($path) || empty($new_name)) {
        echo json_encode(['code' => -1, 'msg' => '路径或新名称为空']);
        exit;
    }
    $real_root = realpath($root_path);
    $real_path = realpath($path);
    if ($real_path === false || strpos($real_path, $real_root) !== 0) {
        echo json_encode(['code' => -1, 'msg' => '非法路径']);
        exit;
    }
    if (!file_exists($real_path)) {
        echo json_encode(['code' => -1, 'msg' => '文件/文件夹不存在']);
        exit;
    }
    if (strpos($new_name, '/') !== false || strpos($new_name, '..') !== false) {
        echo json_encode(['code' => -1, 'msg' => '名称包含非法字符']);
        exit;
    }
    $dir = dirname($real_path);
    $new_path = $dir . '/' . $new_name;
    if (file_exists($new_path)) {
        echo json_encode(['code' => -1, 'msg' => '目标已存在']);
        exit;
    }
    if (rename($real_path, $new_path)) {
        echo json_encode(['code' => 0, 'msg' => '重命名成功']);
    } else {
        @chmod($real_path, 0777);
        if (rename($real_path, $new_path)) {
            echo json_encode(['code' => 0, 'msg' => '重命名成功（权限已修正）']);
        } else {
            echo json_encode(['code' => -1, 'msg' => '重命名失败，请检查权限']);
        }
    }
    exit;
}

// ========== PHP 语法检查 ==========
if (isset($_GET['check']) && $_GET['check'] == '1') {
    header('Content-Type: application/json');
    $path = isset($_GET['path']) ? $_GET['path'] : '';
    if (empty($path)) {
        echo json_encode(['code' => -1, 'msg' => '路径为空']);
        exit;
    }
    $real_root = realpath($root_path);
    $real_path = realpath($path);
    if ($real_path === false || strpos($real_path, $real_root) !== 0) {
        echo json_encode(['code' => -1, 'msg' => '非法路径']);
        exit;
    }
    if (!is_file($real_path)) {
        echo json_encode(['code' => -1, 'msg' => '不是文件']);
        exit;
    }
    $ext = strtolower(pathinfo($real_path, PATHINFO_EXTENSION));
    if ($ext !== 'php') {
        echo json_encode(['code' => 0, 'errors' => [], 'warnings' => []]);
        exit;
    }

    $output = shell_exec('php -l ' . escapeshellarg($real_path) . ' 2>&1');
    $errors = [];
    $warnings = [];

    if (strpos($output, 'No syntax errors') === false) {
        preg_match_all('/Parse error:\s*syntax error,\s*unexpected\s+[^\n]+ in .+ on line (\d+)/i', $output, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $line = (int)$m[1];
            $msg = trim($m[0]);
            $errors[] = ['line' => $line, 'message' => $msg];
        }
        if (empty($errors)) {
            preg_match_all('/ on line (\d+)/i', $output, $lineMatches, PREG_SET_ORDER);
            if (!empty($lineMatches)) {
                foreach ($lineMatches as $lm) {
                    $line = (int)$lm[1];
                    $errors[] = ['line' => $line, 'message' => trim($output)];
                }
            }
        }
    }

    echo json_encode(['code' => 0, 'errors' => $errors, 'warnings' => $warnings]);
    exit;
}

// ---------- 监控数据函数 ----------
function getCpuUsage() {
    if (function_exists('shell_exec')) {
        $cmd = "top -bn1 | grep 'Cpu(s)' | awk '{print $2}' | cut -d'%' -f1";
        $cpu = shell_exec($cmd);
        if ($cpu !== null && is_numeric(trim($cpu))) return round(floatval(trim($cpu)), 2);
        $cmd2 = "top -bn1 | grep '%Cpu' | awk '{print $2}'";
        $cpu2 = shell_exec($cmd2);
        if ($cpu2 !== null && is_numeric(trim($cpu2))) return round(floatval(trim($cpu2)), 2);
    }
    return 0;
}

function getMemoryUsage() {
    $result = ['total' => 'N/A', 'used' => 'N/A', 'percent' => 0];
    if (function_exists('shell_exec')) {
        $output = shell_exec("free -b | grep 'Mem:'");
        if ($output) {
            $parts = preg_split('/\s+/', trim($output));
            if (count($parts) >= 4) {
                $total = (float)$parts[1];
                $available = isset($parts[6]) ? (float)$parts[6] : (float)$parts[3];
                $used = $total - $available;
                $percent = $total > 0 ? round($used / $total * 100, 2) : 0;
                return ['total' => formatBytes($total), 'used' => formatBytes($used), 'percent' => $percent];
            }
        }
    }
    return $result;
}

function getStorageUsage() {
    $result = ['total' => 'N/A', 'used' => 'N/A', 'percent' => 0];
    if (function_exists('shell_exec')) {
        $output = shell_exec("df -B1 / | tail -1");
        if ($output) {
            $parts = preg_split('/\s+/', trim($output));
            if (count($parts) >= 6) {
                $total = (float)$parts[1];
                $used = (float)$parts[2];
                $percent = (float)str_replace('%', '', $parts[4]);
                return ['total' => formatBytes($total), 'used' => formatBytes($used), 'percent' => $percent];
            }
        }
    }
    return $result;
}

function formatBytes($bytes, $precision = 2) {
    if (!is_numeric($bytes) || $bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// AJAX 刷新监控数据
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    $load = sys_getloadavg();
    echo json_encode([
        'load' => round($load[0], 2),
        'cpu' => getCpuUsage(),
        'memory' => getMemoryUsage(),
        'storage' => getStorageUsage()
    ]);
    exit;
}

// ---------- 文件列表 API ----------
if (isset($_GET['api']) && $_GET['api'] == '1') {
    header('Content-Type: application/json');
    $path = isset($_GET['path']) ? $_GET['path'] : $root_path;
    $real_root = realpath($root_path);
    $real_path = realpath($path);
    if ($real_path === false || strpos($real_path, $real_root) !== 0) {
        $real_path = $real_root;
    }
    $breadcrumbs = [];
    if ($real_path != $real_root) {
        $relative = str_replace($real_root . '/', '', $real_path);
        $parts = explode('/', $relative);
        $tmp = $real_root;
        $breadcrumbs[] = ['name' => '根目录', 'path' => $real_root];
        foreach ($parts as $part) {
            $tmp .= '/' . $part;
            $breadcrumbs[] = ['name' => $part, 'path' => $tmp];
        }
    } else {
        $breadcrumbs[] = ['name' => '根目录', 'path' => $real_root];
    }
    $items = [];
    if (is_dir($real_path)) {
        $scandir = scandir($real_path);
        $dirs = $files = [];
        foreach ($scandir as $item) {
            if ($item == '.' || $item == '..') continue;
            $full = $real_path . '/' . $item;
            if (is_dir($full)) {
                $dirs[] = ['name' => $item, 'path' => $full, 'type' => 'dir', 'icon' => '📁'];
            } else {
                $size = filesize($full);
                $files[] = ['name' => $item, 'path' => $full, 'type' => 'file', 'size' => formatBytes($size), 'icon' => getFileIcon($item, false)];
            }
        }
        usort($dirs, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        $items = array_merge($dirs, $files);
    }
    echo json_encode([
        'breadcrumbs' => $breadcrumbs,
        'items'       => $items,
        'currentPath' => $real_path,
        'total'       => count($items),
    ]);
    exit;
}

// ---------- 搜索处理 ----------
if (isset($_GET['search']) && $_GET['search'] == '1') {
    header('Content-Type: application/json');
    $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
    if (strlen($keyword) < 2) {
        echo json_encode(['code' => 0, 'items' => []]);
        exit;
    }
    $real_root = realpath($root_path);
    $cmd = "find " . escapeshellarg($real_root) . " -type f -name '*" . escapeshellarg($keyword) . "*' 2>/dev/null | head -100";
    $output = shell_exec($cmd);
    $items = [];
    if ($output) {
        $paths = explode("\n", trim($output));
        foreach ($paths as $p) {
            if (empty($p)) continue;
            $real_p = realpath($p);
            if ($real_p && strpos($real_p, $real_root) === 0) {
                $items[] = [
                    'name' => basename($real_p),
                    'path' => $real_p,
                    'type' => 'file',
                    'icon' => getFileIcon(basename($real_p), false)
                ];
            }
        }
    }
    echo json_encode(['code' => 0, 'items' => $items]);
    exit;
}

// ============ 前端展示 ============
$current_path = isset($_GET['path']) ? $_GET['path'] : $root_path;
$real_root = realpath($root_path);
$real_path = realpath($current_path);
if ($real_path === false || strpos($real_path, $real_root) !== 0) {
    $real_path = $real_root;
    $current_path = $real_root;
}
$breadcrumbs = [];
if ($real_path != $real_root) {
    $relative = str_replace($real_root . '/', '', $real_path);
    $parts = explode('/', $relative);
    $tmp = $real_root;
    $breadcrumbs[] = ['name' => '根目录', 'path' => $real_root];
    foreach ($parts as $part) {
        $tmp .= '/' . $part;
        $breadcrumbs[] = ['name' => $part, 'path' => $tmp];
    }
} else {
    $breadcrumbs[] = ['name' => '根目录', 'path' => $real_root];
}
$dirs = [];
$files = [];
if (is_dir($real_path)) {
    $items = scandir($real_path);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $full_item = $real_path . '/' . $item;
        if (is_dir($full_item)) {
            $dirs[] = ['name' => $item, 'path' => $full_item];
        } else {
            $files[] = ['name' => $item, 'path' => $full_item];
        }
    }
}
usort($dirs, fn($a, $b) => strcasecmp($a['name'], $b['name']));
usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));
$all_items = array_merge($dirs, $files);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>KonT - 控制面板</title>
    <link rel="icon" type="image/jpeg" href="php/kontt.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="php/kontt.jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/dracula.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/clike/clike.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/search.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/searchcursor.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/jump-to-line.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.js"></script>

    <style>
    /* ===== 全局样式 ===== */
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        background: #0d0d0d;
        color: #f5f5f7;
        padding: 24px;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .main-wrapper {
        max-width: 1400px;
        width: 100%;
        transition: padding-left 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        padding-left: 0;
    }
    body.editor-minimized .main-wrapper {
        padding-left: 52px;
    }
    ::-webkit-scrollbar { width: 8px; height: 8px; }
    ::-webkit-scrollbar-track { background: #1c1c1e; }
    ::-webkit-scrollbar-thumb { background: #3a3a3c; border-radius: 0; }
    .dash-card, .file-explorer, .file-item, .breadcrumb-nav a { border-radius: 0 !important; }

    /* -------- 仪表盘 -------- */
    .dash-wrapper {
        position: relative;
        margin-bottom: 28px;
    }
    .dash-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    .dash-header-title {
        color: #8e8e93;
        font-size: 14px;
        font-weight: 500;
        letter-spacing: 1px;
    }
    .dash-toggle-btn-exp {
        background: transparent;
        border: none;
        color: #8e8e93;
        font-size: 13px;
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 4px;
        transition: color 0.2s, background 0.2s;
    }
    .dash-toggle-btn-exp:hover { background: rgba(255,255,255,0.08); color: #fff; }

    .dashboard-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
    }
    .dash-card {
        background: rgba(28, 28, 30, 0.85);
        backdrop-filter: blur(16px);
        padding: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid rgba(255, 255, 255, 0.06);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        will-change: transform;
        contain: layout style;
    }
    .dash-card:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(0,0,0,0.6); }
    .dash-info h4 { color: #8e8e93; font-size: 13px; font-weight: 500; margin-bottom: 8px; }
    .dash-info .value { font-size: 26px; font-weight: 700; color: #ffffff; display: flex; align-items: baseline; }
    .dash-info .unit { font-size: 13px; color: #8e8e93; margin-left: 2px; }
    .dash-info .sub { font-size: 12px; color: #636366; margin-top: 6px; }
    .circle-chart { position: relative; width: 72px; height: 72px; flex-shrink: 0; }
    .circle-chart svg { transform: rotate(-90deg); width: 100%; height: 100%; will-change: transform; }
    .circle-chart .bg { fill: none; stroke: rgba(255, 255, 255, 0.05); stroke-width: 4.5; }
    .circle-chart .progress { fill: none; stroke-width: 4.5; stroke-linecap: round; transition: stroke-dashoffset 0.2s cubic-bezier(0.34, 1.56, 0.64, 1); }
    .circle-text { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 600; color: #f5f5f7; }

    /* 折叠后的长条 */
    .dash-collapsed {
        display: none;
        height: 38px;
        background: rgba(28, 28, 30, 0.85);
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 0;
        padding: 4px 10px;
        align-items: center;
        margin-bottom: 28px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        contain: layout style;
    }
    .dash-collapsed-inner {
        display: flex;
        align-items: center;
        width: 100%;
        height: 100%;
        gap: 8px;
    }
    .dash-inner-btn {
        background: transparent;
        border: none;
        color: #8e8e93;
        font-size: 12px;
        cursor: pointer;
        padding: 0 8px;
        white-space: nowrap;
        flex-shrink: 0;
        min-width: 50px;
        text-align: center;
        transition: color 0.2s;
    }
    .dash-inner-btn:hover { color: #fff; }
    .dash-bar-container {
        flex: 1;
        height: 16px;
        display: flex;
        border-radius: 0;
        overflow: hidden;
        background: #1c1c1e;
        border: 1px solid rgba(255, 255, 255, 0.06);
        contain: layout style;
    }
    .dash-bar-seg {
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: 500;
        color: #fff;
        text-shadow: 0 1px 2px rgba(0,0,0,0.8);
        transition: flex-grow 0.3s ease;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-width: 1px;
    }

    /* 文件浏览器 */
    .file-explorer {
        background: rgba(28, 28, 30, 0.7);
        backdrop-filter: blur(16px);
        padding: 24px;
        border: 1px solid rgba(255, 255, 255, 0.06);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        margin-top: 8px;
        position: relative;
        transition: border-color 0.2s ease, background 0.2s ease;
        contain: layout style;
    }
    .file-explorer.drag-over {
        border-color: #0a84ff;
        background: rgba(10, 132, 255, 0.08);
    }
    .breadcrumb-nav { display: flex; gap: 8px; font-size: 14px; padding-bottom: 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); margin-bottom: 18px; flex-wrap: wrap; align-items: center; contain: layout style; }
    .breadcrumb-nav a { color: #0a84ff; text-decoration: none; padding: 4px 8px; border: 1px solid transparent; transition: all 0.15s; cursor: pointer; }
    .breadcrumb-nav a:hover { color: #2f97ff; background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.08); }
    .breadcrumb-nav span { color: #636366; }
    .breadcrumb-nav .count { margin-left: auto; color: #8e8e93; font-size: 13px; }
    .breadcrumb-nav .btn-tree {
        margin-left: 12px;
        background: #27ae60;
        color: #fff;
        padding: 4px 14px;
        border-radius: 30px;
        text-decoration: none;
        font-size: 13px;
        display: inline-block;
        transition: background 0.2s, transform 0.1s;
    }
    .breadcrumb-nav .btn-tree:hover {
        background: #1e8449;
        transform: translateY(-1px);
    }

    .file-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 10px;
        transition: opacity 0.2s ease, transform 0.2s ease;
        contain: layout style;
    }
    .file-grid.loading {
        opacity: 0.4;
        transform: scale(0.98);
    }
    .file-grid .placeholder {
        grid-column: 1 / -1;
        text-align: center;
        color: #636366;
        padding: 40px 0;
    }

    .file-item {
        display: flex;
        align-items: center;
        padding: 14px 18px;
        background: rgba(255, 255, 255, 0.03);
        text-decoration: none;
        color: #f5f5f7;
        transition: all 0.15s ease;
        border: 1px solid rgba(255, 255, 255, 0.02);
        cursor: pointer;
        will-change: transform;
        contain: layout style;
    }
    .file-item:hover { background: rgba(255, 255, 255, 0.06); border-color: rgba(255, 255, 255, 0.12); transform: translateX(2px); }
    .file-icon { font-size: 24px; margin-right: 14px; width: 32px; text-align: center; }
    .file-info { display: flex; flex-direction: column; overflow: hidden; }
    .file-name { font-size: 14px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .file-sub { font-size: 12px; color: #8e8e93; margin-top: 4px; }

    /* ===== 全局右键菜单（带平滑动画） ===== */
    #contextMenu {
        position: fixed;
        display: block;
        visibility: hidden;
        opacity: 0;
        transform: scale(0.96) translateY(-6px);
        transform-origin: top right;
        transition: opacity 0.18s cubic-bezier(0.34, 1.56, 0.64, 1),
                    transform 0.18s cubic-bezier(0.34, 1.56, 0.64, 1),
                    visibility 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
        background: rgba(28, 28, 30, 0.92);
        backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.6);
        min-width: 180px;
        max-width: 280px;
        max-height: 90vh;
        overflow-y: auto;
        padding: 6px 0;
        z-index: 9999;
        border-radius: 8px;
        pointer-events: none;
    }
    #contextMenu.show {
        visibility: visible;
        opacity: 1;
        transform: scale(1) translateY(0);
        pointer-events: auto;
    }
    #contextMenu .menu-item {
        padding: 10px 20px;
        color: #f5f5f7;
        font-size: 14px;
        cursor: pointer;
        transition: background 0.15s;
        display: flex;
        align-items: center;
        gap: 10px;
        border-radius: 4px;
        margin: 2px 6px;
    }
    #contextMenu .menu-item:hover { background: rgba(255, 255, 255, 0.08); }
    #contextMenu .menu-separator { height: 1px; background: rgba(255,255,255,0.1); margin: 4px 8px; }

    /* 模态框 */
    .modal-overlay {
        display: flex;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.6);
        z-index: 30000;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity 0.25s ease, visibility 0.25s ease;
    }
    .modal-overlay.show {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }
    .modal-box {
        background: #2c2c2e;
        max-width: 500px;
        width: 90%;
        border-radius: 8px;
        padding: 24px;
        color: #fff;
        transform: scale(0.95);
        transition: transform 0.25s ease;
        box-shadow: 0 16px 48px rgba(0,0,0,0.8);
    }
    .modal-overlay.show .modal-box {
        transform: scale(1);
    }
    .modal-box .modal-title { font-size: 18px; font-weight: 600; margin-bottom: 12px; }
    .modal-box .modal-body { font-size: 15px; line-height: 1.6; margin-bottom: 20px; }
    .modal-box .modal-body input[type="text"],
    .modal-box .modal-body textarea {
        width: 100%;
        background: #3a3a3c;
        border: 1px solid #555;
        color: #fff;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 14px;
        outline: none;
        transition: border-color 0.15s;
        margin-top: 4px;
    }
    .modal-box .modal-body input[type="text"]:focus,
    .modal-box .modal-body textarea:focus { border-color: #0a84ff; }
    .modal-box .modal-body textarea { min-height: 100px; resize: vertical; }
    .modal-box .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }
    .modal-box .modal-actions button {
        padding: 8px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        color: #fff;
        transition: background 0.15s, transform 0.15s;
        font-size: 14px;
    }
    .modal-box .modal-actions .btn-cancel { background: #3a3a3c; }
    .modal-box .modal-actions .btn-cancel:hover { background: #4a4a4c; }
    .modal-box .modal-actions .btn-confirm { background: #0a84ff; }
    .modal-box .modal-actions .btn-confirm:hover { background: #2f97ff; }
    .modal-box .modal-actions button:active { transform: scale(0.96); }

    /* Toast */
    #toastContainer {
        position: fixed;
        top: 30px;
        right: 20px;
        z-index: 99999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-width: 360px;
        width: 100%;
    }
    .toast {
        background: rgba(28, 28, 30, 0.92);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 8px;
        padding: 14px 18px;
        color: #f5f5f7;
        font-size: 14px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.5);
        transform: translateX(100%);
        opacity: 0;
        transition: transform 0.3s ease, opacity 0.3s ease;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .toast.show {
        transform: translateX(0);
        opacity: 1;
    }
    .toast.success { border-left: 4px solid #4caf50; }
    .toast.error { border-left: 4px solid #f44336; }
    .toast.warning { border-left: 4px solid #ff9800; }
    .toast.info { border-left: 4px solid #0a84ff; }
    .toast .toast-icon { font-size: 18px; }
    .toast .toast-close { margin-left: auto; cursor: pointer; color: #858585; font-size: 18px; }

    /* 编辑器 */
    .editor-overlay {
        position: fixed;
        top: 0;
        left: 0;
        height: 100%;
        z-index: 10000;
        background: rgba(30, 30, 30, 0.98);
        display: flex;
        flex-direction: row;
        width: 0;
        overflow: hidden;
        transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1),
                    background 0.35s ease,
                    opacity 0.35s ease;
        opacity: 0;
        pointer-events: none;
        box-shadow: 2px 0 20px rgba(0,0,0,0.5);
    }
    .editor-overlay.active {
        width: 100%;
        opacity: 1;
        pointer-events: auto;
    }
    .editor-overlay.minimized {
        width: 48px !important;
        background: rgba(28, 28, 30, 0.92) !important;
        opacity: 1 !important;
        pointer-events: auto;
        box-shadow: 2px 0 20px rgba(0,0,0,0.4);
    }
    .editor-overlay.minimized .editor-sidebar,
    .editor-overlay.minimized .editor-main,
    .editor-overlay.minimized .editor-minibar {
        display: none;
    }
    .editor-overlay.minimized .editor-minibar {
        display: flex !important;
    }
    .editor-overlay:not(.minimized) .editor-minibar {
        display: none !important;
    }
    .editor-minibar {
        width: 48px;
        height: 100%;
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: #aaa;
        font-size: 14px;
        background: rgba(28, 28, 30, 0.92);
        border-right: 1px solid rgba(255,255,255,0.06);
        user-select: none;
        transition: background 0.2s;
    }
    .editor-minibar:hover {
        background: rgba(255,255,255,0.08);
        color: #fff;
    }
    .editor-minibar .minibar-icon {
        font-size: 28px;
        margin-bottom: 4px;
    }
    .editor-minibar .minibar-label {
        writing-mode: vertical-lr;
        letter-spacing: 6px;
        font-weight: 300;
    }

    .editor-sidebar {
        width: 280px;
        background: #252526;
        display: flex;
        flex-direction: column;
        border-right: 1px solid #3e3e42;
        flex-shrink: 0;
        transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease;
        overflow: hidden;
        position: relative;
    }
    .editor-sidebar.collapsed {
        width: 50px !important;
        min-width: 50px !important;
    }
    .editor-sidebar.collapsed .sidebar-header span,
    .editor-sidebar.collapsed .sidebar-actions,
    .editor-sidebar.collapsed .sidebar-search,
    .editor-sidebar.collapsed .sidebar-tree {
        display: none;
    }
    .editor-sidebar.collapsed .sidebar-header .header-actions {
        display: flex;
        justify-content: center;
        width: 100%;
    }
    .editor-sidebar.collapsed .sidebar-header .header-actions button {
        transform: rotate(180deg);
    }
    .editor-sidebar.collapsed .sidebar-header {
        justify-content: center;
    }
    .editor-sidebar.hidden { display: none; }

    .sidebar-header {
        padding: 10px 12px;
        font-size: 14px;
        font-weight: 500;
        color: #cccccc;
        background: #252526;
        border-bottom: 1px solid #3e3e42;
        flex-shrink: 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .sidebar-header .header-actions {
        display: flex;
        gap: 6px;
        align-items: center;
    }
    .header-actions button {
        background: transparent;
        border: none;
        color: #aaa;
        font-size: 14px;
        padding: 4px 6px;
        cursor: pointer;
        border-radius: 4px;
        line-height: 1;
        transition: background 0.15s, color 0.15s;
    }
    .header-actions button:hover {
        background: rgba(255,255,255,0.08);
        color: #fff;
    }

    .sidebar-actions {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 2px;
        padding: 4px 6px;
        background: #1e1e1e;
        border-bottom: 1px solid #333;
    }
    .sidebar-actions button {
        background: transparent;
        border: none;
        color: #cccccc;
        font-size: 12px;
        padding: 6px 0;
        cursor: pointer;
        border-radius: 4px;
        transition: background 0.15s, color 0.15s;
        white-space: nowrap;
        text-align: center;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sidebar-actions button:hover {
        background: rgba(255,255,255,0.08);
        color: #fff;
    }
    .sidebar-actions button .icon {
        margin-right: 2px;
    }

    .sidebar-search {
        padding: 8px 10px;
        display: flex;
        background: #1e1e1e;
        border-bottom: 1px solid #333;
        flex-shrink: 0;
    }
    .sidebar-search input {
        flex: 1;
        background: #3c3c3c;
        border: none;
        padding: 4px 8px;
        color: #ccc;
        outline: none;
        font-size: 12px;
        transition: background 0.15s;
        border-radius: 4px;
    }
    .sidebar-search input:focus { background: #4a4a4c; }
    .sidebar-tree {
        flex: 1;
        overflow-y: auto;
        padding: 8px 0;
        contain: layout style;
    }
    .tree-node {
        color: #d4d4d4;
    }
    .tree-label {
        display: flex;
        align-items: center;
        gap: 6px;
        min-height: 30px;
        padding: 4px 12px;
        cursor: pointer;
        user-select: none;
        transition: background 0.18s ease, color 0.18s ease;
    }
    .tree-label:hover {
        background: rgba(255,255,255,0.06);
        color: #fff;
    }
    .tree-label .icon,
    .tree-label .toggle-icon {
        flex-shrink: 0;
    }
    .tree-label .name {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .toggle-icon {
        width: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        color: #9aa0a6;
        transform-origin: center;
        transition: transform 0.15s cubic-bezier(0.34, 1.56, 0.64, 1), color 0.18s ease;
    }
    .toggle-icon.open {
        transform: rotate(90deg);
        color: #fff;
    }
    .tree-children {
        margin-left: 14px;
        overflow: hidden;
        max-height: 0;
        opacity: 0;
        transform: translateY(-4px);
        transition: max-height 0.15s ease, opacity 0.15s ease, transform 0.15s ease;
    }
    .tree-children.open {
        opacity: 1;
        transform: translateY(0);
    }
    .tree-children.instant {
        transition: none !important;
    }
    .tree-item.active {
        background: #37373d !important;
        color: #fff !important;
    }

    .editor-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: #1e1e1e;
        min-width: 0;
    }
    .editor-toolbar {
        height: 44px;
        background: #2d2d30;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 10px;
        border-bottom: 1px solid #3e3e42;
        flex-shrink: 0;
        flex-wrap: wrap;
    }
    .toolbar-left, .toolbar-right { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .editor-toolbar button {
        background: transparent;
        border: none;
        color: #cccccc;
        font-size: 13px;
        padding: 6px 10px;
        cursor: pointer;
        border-radius: 2px;
        display: flex;
        align-items: center;
        transition: background 0.15s, color 0.15s;
        min-height: 32px;
    }
    .editor-toolbar button:hover { background: rgba(255,255,255,0.08); }
    .toolbar-left button.save-btn { background: #0a84ff; color: #fff; padding: 6px 14px; border-radius: 2px; }
    .toolbar-left button.save-btn:hover { background: #2f97ff; }
    .toolbar-left button.save-all-btn {
        background: rgba(255,255,255,0.06);
        color: #d4d4d4;
        cursor: pointer;
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 2px;
    }
    .toolbar-left button.save-all-btn:hover {
        background: rgba(255,255,255,0.1);
        color: #fff;
    }
    .editor-tabs {
        height: 35px;
        background: #252526;
        display: flex;
        align-items: flex-end;
        border-bottom: 1px solid #1e1e1e;
        flex-shrink: 0;
        overflow-x: auto;
        gap: 1px;
        contain: layout style;
    }
    .editor-tabs .tab {
        background: #1e1e1e;
        padding: 0 14px;
        height: 100%;
        display: flex;
        align-items: center;
        font-size: 13px;
        color: #aaa;
        border-top: 2px solid transparent;
        border-left: 1px solid transparent;
        border-right: 1px solid transparent;
        cursor: default;
        position: relative;
        transition: background 0.2s, border-color 0.2s, color 0.2s;
        flex-shrink: 0;
        min-width: 80px;
        max-width: 200px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .editor-tabs .tab:hover {
        background: #2a2a2a;
        color: #eee;
    }
    .editor-tabs .tab.active {
        background: #1e1e1e;
        color: #fff;
        border-top-color: #0a84ff;
        border-left-color: #3e3e42;
        border-right-color: #3e3e42;
        border-bottom: 1px solid #1e1e1e;
    }
    .editor-tabs .tab .close-tab {
        margin-left: 10px;
        color: #858585;
        cursor: pointer;
        font-size: 14px;
        transition: color 0.15s;
        padding: 0 4px;
    }
    .editor-tabs .tab .close-tab:hover { color: #fff; }
    .editor-body-wrapper {
        display: flex;
        flex: 1;
        overflow: hidden;
        position: relative;
    }
    .editor-body {
        flex: 1;
        overflow: hidden;
        background: #1e1e1e;
    }
    .editor-body .CodeMirror {
        height: 100% !important;
        font-size: 14px;
        line-height: 1.6;
        background: #1e1e1e;
    }
    .CodeMirror { font-family: 'Consolas', 'Monaco', monospace !important; }
    .CodeMirror-activeline-background { background: #2a2a2a !important; }
    .cm-search-match { background: #f1c40f !important; color: #000 !important; border-radius: 2px; padding: 0 2px; }
    .editor-statusbar {
        height: 28px;
        background: #1c1c1e;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 12px;
        font-size: 12px;
        color: #858585;
        border-top: 1px solid #3e3e42;
        flex-shrink: 0;
    }
    .editor-statusbar .status-left { display: flex; align-items: center; gap: 10px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
    .editor-statusbar .status-right { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
    .editor-statusbar .status-metric { color: #a7a7a7; flex-shrink: 0; }
    .editor-statusbar .status-path {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        min-width: 0;
    }
    .status-error { color: #f44336; margin-left: 10px; }
    .status-warning { color: #ff9800; margin-left: 10px; }

    /* 编辑器浮动面板（搜索/替换/跳转） */
    .editor-pop-panel {
        display: none;
        position: absolute;
        top: 10px;
        right: 12px;
        background: #2d2d30;
        border: 1px solid #3e3e42;
        border-radius: 6px;
        padding: 12px 16px;
        min-width: 280px;
        max-width: 400px;
        z-index: 10001;
        box-shadow: 0 8px 24px rgba(0,0,0,0.6);
        flex-direction: column;
        gap: 8px;
    }
    .editor-pop-panel.active { display: flex; }
    .editor-pop-panel .panel-row {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .editor-pop-panel input {
        flex: 1;
        background: #1e1e1e;
        border: 1px solid #3e3e42;
        color: #fff;
        padding: 6px 10px;
        outline: none;
        font-size: 13px;
        border-radius: 4px;
        transition: border-color 0.15s;
    }
    .editor-pop-panel input:focus { border-color: #0a84ff; }
    .editor-pop-panel .search-info {
        font-size: 12px;
        color: #858585;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .editor-pop-panel .search-info .match-count { color: #0a84ff; }
    .editor-pop-panel .close-search-btn {
        background: none;
        border: none;
        color: #ccc;
        font-size: 18px;
        cursor: pointer;
        padding: 0 4px;
        line-height: 1;
    }
    .editor-pop-panel .close-search-btn:hover { color: #fff; }
    .editor-pop-panel .panel-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }
    .editor-pop-panel .action-btn {
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.08);
        color: #f5f5f7;
        padding: 6px 12px;
        font-size: 12px;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.15s, border-color 0.15s;
    }
    .editor-pop-panel .action-btn:hover {
        background: rgba(10,132,255,0.18);
        border-color: rgba(10,132,255,0.36);
    }

    /* 上传对话框 */
    #uploadModal {
        display: flex;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.6);
        z-index: 20000;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity 0.25s ease, visibility 0.25s ease;
    }
    #uploadModal.show {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }
    .upload-modal-content {
        background: #2c2c2e;
        max-width: 600px;
        width: 90%;
        border-radius: 8px;
        padding: 20px;
        color: #fff;
        max-height: 80vh;
        display: flex;
        flex-direction: column;
        transform: scale(0.95);
        transition: transform 0.25s ease;
    }
    #uploadModal.show .upload-modal-content { transform: scale(1); }
    .upload-modal-content h3 { margin: 0 0 12px 0; }
    .upload-modal-content .file-list {
        flex: 1;
        overflow-y: auto;
        margin-bottom: 12px;
    }
    .upload-modal-content .file-list .file-entry {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        background: #3a3a3c;
        margin-bottom: 6px;
        border-radius: 4px;
    }
    .upload-modal-content .file-list .file-entry .remove-btn {
        background: none;
        border: none;
        color: #ff3b30;
        cursor: pointer;
        font-size: 18px;
    }
    .upload-modal-content .actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }
    .upload-modal-content .actions button {
        padding: 8px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        color: #fff;
        transition: background 0.15s, transform 0.15s;
    }
    .upload-modal-content .actions .cancel-btn { background: #3a3a3c; }
    .upload-modal-content .actions .confirm-btn { background: #0a84ff; }
    .upload-modal-content .actions .confirm-btn:hover { background: #2f97ff; }
    .upload-modal-content .actions button:active { transform: scale(0.96); }

    /* ===== 移动端适配 ===== */
    @media (max-width: 768px) {
        .dashboard-row { grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .dash-card { padding: 16px; }
        .dash-info .value { font-size: 20px; }
        .circle-chart { width: 56px; height: 56px; }
        .circle-chart .circle-text { font-size: 12px; }
        .file-explorer { padding: 16px; }
        .file-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 8px; }
        .file-item { padding: 10px 12px; }
        .file-icon { font-size: 20px; width: 28px; }
        .file-name { font-size: 13px; }
        .breadcrumb-nav { font-size: 13px; gap: 4px; }
        .breadcrumb-nav a { padding: 2px 6px; }
        .breadcrumb-nav .count { font-size: 12px; }
        .dash-collapsed { height: 32px; padding: 4px 6px; }
        .dash-bar-container { height: 12px; }
        .dash-bar-seg { font-size: 9px; }
        .dash-inner-btn { font-size: 11px; }
        .editor-sidebar { width: 240px; }
        .editor-overlay.minimized { width: 40px !important; }
        body.editor-minimized .main-wrapper { padding-left: 44px; }
        .editor-minibar { width: 40px; }
        .sidebar-actions button { font-size: 11px; padding: 4px 0; }
        .editor-body-wrapper { flex-direction: column; }
    }

    @media (max-width: 600px) {
        body { padding: 12px; }
        .dashboard-row { grid-template-columns: 1fr 1fr; gap: 8px; }
        .dash-card { padding: 12px; flex-direction: column; align-items: flex-start; }
        .dash-info .value { font-size: 18px; }
        .circle-chart { width: 48px; height: 48px; align-self: center; margin-top: 6px; }
        .file-grid { grid-template-columns: 1fr; }
        .file-item { padding: 12px 16px; }
        .editor-overlay { flex-direction: column; }
        .editor-sidebar {
            width: 100% !important;
            height: 40vh;
            border-right: none;
            border-bottom: 1px solid #3e3e42;
            flex-shrink: 0;
            display: none;
        }
        .editor-sidebar.show { display: flex; }
        .editor-main { height: 60vh; }
        .editor-toolbar {
            height: auto;
            padding: 6px 8px;
            flex-wrap: wrap;
            gap: 4px;
        }
        .editor-toolbar button {
            font-size: 12px;
            padding: 4px 8px;
            min-height: 28px;
        }
        .toolbar-left, .toolbar-right { gap: 4px; }
        .editor-tabs .tab { font-size: 12px; padding: 0 10px; min-width: 60px; max-width: 140px; }
        .editor-body .CodeMirror { font-size: 13px; }
        .editor-statusbar { font-size: 11px; padding: 0 8px; }
        .editor-pop-panel { min-width: 220px; right: 6px; top: 48px; padding: 8px 12px; }
        .modal-box { padding: 16px; max-width: 95%; }
        .upload-modal-content { max-width: 95%; padding: 16px; }
        #contextMenu { min-width: 140px; border-radius: 6px; }
        #contextMenu .menu-item { font-size: 13px; padding: 8px 14px; }
        .sidebar-header .toggle-sidebar { display: inline-block; }
        .dash-collapsed { height: 28px; }
        .dash-bar-container { height: 10px; }
        .dash-bar-seg { font-size: 8px; }
        .dash-inner-btn { font-size: 10px; }
        .editor-overlay.minimized { width: 36px !important; }
        body.editor-minimized .main-wrapper { padding-left: 40px; }
        .editor-minibar { width: 36px; }
    }

    @media (max-width: 400px) {
        .dashboard-row { grid-template-columns: 1fr; }
        .circle-chart { width: 60px; height: 60px; }
        .dash-card { flex-direction: row; align-items: center; } /* fix: mobile: 阻止卡片过度拉伸 */
        .file-item { padding: 10px 12px; }
        .breadcrumb-nav .btn-tree { margin-left: auto; display: block; } /* fix: mobile: 按钮独占一行 */
        .breadcrumb-nav .count { display: none; } /* fix: mobile: 隐藏数量节省空间 */
    }

    /* ========== 折叠侧边栏底部添加竖排文字 ========== */
    #editorSidebar.collapsed::after {
        content: "K\A-\Ao\An\A!\A が\A一\A番\A好\Aき\Aで\Aす";
        white-space: pre;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        color: #8e8e93;
        font-size: 13px;
        letter-spacing: 4px;
        line-height: 1.6;
        padding-top: 20px;
        width: 100%;
        flex: 1;
    }
    #editorSidebar.collapsed {
        display: flex !important;
        flex-direction: column !important;
        width: 50px !important;
        min-width: 50px !important;
    }
    #editorSidebar.collapsed .sidebar-header {
        flex-shrink: 0;
        justify-content: center;
        padding: 10px 0;
        display: flex;
        align-items: flex-start;
    }
    #editorSidebar.collapsed .sidebar-header span {
        display: none !important;
    }
    </style>

    <?php if (file_exists(__DIR__ . '/kzai.php')): ?>
    <script>
        // AI 扩展已加载标记
        window.AI_EXTENSION_LOADED = true;
    </script>
    <?php endif; ?>
</head>
<body>
<div class="main-wrapper" id="mainWrapper">
    <!-- 仪表盘 -->
    <div class="dash-wrapper">
        <div class="dash-header">
            <span class="dash-header-title">📊 系统监控</span>
            <button class="dash-toggle-btn-exp" id="dashToggleBtnExp">⬇ 收起</button>
        </div>

        <div class="dashboard-row" id="dashRow">
            <!-- 负载 -->
            <div class="dash-card">
                <div class="dash-info"><h4>系统负载</h4><div class="value" id="loadVal">0.00</div><div class="sub">1分钟 / 5分钟 / 15分钟</div></div>
                <div class="circle-chart"><svg viewBox="0 0 36 36"><circle cx="18" cy="18" r="15.9155" class="bg" /><circle cx="18" cy="18" r="15.9155" class="progress" id="loadCircle" stroke="#4caf50" stroke-dasharray="100" stroke-dashoffset="100" /></svg><div class="circle-text" id="loadPercent">0%</div></div>
            </div>
            <!-- CPU -->
            <div class="dash-card">
                <div class="dash-info"><h4>CPU 使用率</h4><div class="value" id="cpuVal">0<span class="unit">%</span></div><div class="sub">1 核心</div></div>
                <div class="circle-chart"><svg viewBox="0 0 36 36"><circle cx="18" cy="18" r="15.9155" class="bg" /><circle cx="18" cy="18" r="15.9155" class="progress" id="cpuCircle" stroke="#2196f3" stroke-dasharray="100" stroke-dashoffset="100" /></svg><div class="circle-text" id="cpuPercent">0%</div></div>
            </div>
            <!-- 内存 -->
            <div class="dash-card">
                <div class="dash-info"><h4>内存使用</h4><div class="value"><span id="memUsed">0</span><span class="unit"> MB</span></div><div class="sub">总计: <span id="memTotal">0</span> </div></div>
                <div class="circle-chart"><svg viewBox="0 0 36 36"><circle cx="18" cy="18" r="15.9155" class="bg" /><circle cx="18" cy="18" r="15.9155" class="progress" id="memCircle" stroke="#e91e63" stroke-dasharray="100" stroke-dashoffset="100" /></svg><div class="circle-text" id="memPercent">0%</div></div>
            </div>
            <!-- 磁盘 -->
            <div class="dash-card">
                <div class="dash-info"><h4>磁盘使用</h4><div class="value"><span id="diskUsed">0</span><span class="unit"> GB</span></div><div class="sub">总计: <span id="diskTotal">0</span> GB</div></div>
                <div class="circle-chart"><svg viewBox="0 0 36 36"><circle cx="18" cy="18" r="15.9155" class="bg" /><circle cx="18" cy="18" r="15.9155" class="progress" id="diskCircle" stroke="#ff9800" stroke-dasharray="100" stroke-dashoffset="100" /></svg><div class="circle-text" id="diskPercent">0%</div></div>
            </div>
        </div>

        <!-- 折叠长条 -->
        <div class="dash-collapsed" id="dashCollapsed">
            <div class="dash-collapsed-inner">
                <button class="dash-inner-btn" id="dashInnerToggleBtn">[ + ] 展开</button>
                <div class="dash-bar-container">
                    <div class="dash-bar-seg" id="cLoad" style="background: #4caf50;">负载 0%</div>
                    <div class="dash-bar-seg" id="cCpu" style="background: #2196f3;">CPU 0%</div>
                    <div class="dash-bar-seg" id="cMem" style="background: #e91e63;">内存 0%</div>
                    <div class="dash-bar-seg" id="cDisk" style="background: #ff9800;">磁盘 0%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 文件浏览器 -->
    <div class="file-explorer" id="fileExplorer">
        <div class="breadcrumb-nav" id="breadcrumbNav">
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <?php if ($index > 0): ?><span>›</span><?php endif; ?>
                <a href="javascript:void(0)" data-path="<?php echo urlencode($crumb['path']); ?>"><?php echo htmlspecialchars($crumb['name']); ?></a>
            <?php endforeach; ?>
            <span class="count">共 <?php echo count($all_items); ?> 个项目</span>
            <a href="?downloadTree=1" class="btn-tree">📄 下载结构树</a>
        </div>

        <div class="file-grid" id="fileGrid">
            <?php if (empty($all_items)): ?>
                <div class="placeholder">此目录为空</div>
            <?php else: ?>
                <?php foreach ($all_items as $item):
                    $is_dir = is_dir($item['path']);
                    $icon = $is_dir ? '📁' : getFileIcon($item['name'], false);
                    $sub = $is_dir ? '文件夹' : (file_exists($item['path']) ? round(filesize($item['path']) / 1024, 1) . ' KB' : '');
                    $type = $is_dir ? 'dir' : 'file';
                ?>
                <a href="javascript:void(0)" class="file-item" data-path="<?php echo urlencode($item['path']); ?>" data-type="<?php echo $type; ?>">
                    <div class="file-icon"><?php echo $icon; ?></div>
                    <div class="file-info">
                        <div class="file-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="file-sub"><?php echo $sub; ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===== 右键菜单 ===== -->
<div id="contextMenu">
    <div class="menu-item" id="menuNewWindow"><span class="icon">🔗</span> 新窗口打开</div>
    <div class="menu-item" id="menuCopy"><span class="icon">📋</span> 复制</div>
    <div class="menu-item" id="menuCut"><span class="icon">✂️</span> 剪切</div>
    <div class="menu-item" id="menuPaste"><span class="icon">📌</span> 粘贴</div>
    <div class="menu-item" id="menuRename"><span class="icon">✏️</span> 重命名</div>
    <div class="menu-separator"></div>
    <div class="menu-item" id="menuDownload"><span class="icon">⬇️</span> 下载</div>
    <div class="menu-item" id="menuPreview"><span class="icon">👁️</span> 预览</div>
    <div class="menu-item" id="menuDelete"><span class="icon">🗑️</span> 删除</div>
    <div class="menu-separator"></div>
    <div class="menu-item" id="menuNewFile"><span class="icon">📄</span> 新建文件</div>
    <div class="menu-item" id="menuNewFolder"><span class="icon">📁</span> 新建文件夹</div>
</div>

<!-- 模态框 -->
<div class="modal-overlay" id="customModal">
    <div class="modal-box">
        <div class="modal-title" id="modalTitle">提示</div>
        <div class="modal-body" id="modalBody">消息内容</div>
        <div class="modal-actions" id="modalActions">
            <button class="btn-cancel" id="modalCancelBtn">取消</button>
            <button class="btn-confirm" id="modalConfirmBtn">确定</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="toastContainer"></div>

<!-- 上传对话框 -->
<div id="uploadModal">
    <div class="upload-modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h3>📤 上传文件</h3>
            <button onclick="closeUploadModal()" style="background:none; border:none; color:#fff; font-size:24px; cursor:pointer;">✕</button>
        </div>
        <div class="file-list" id="uploadFileList">
            <div style="color:#858585; text-align:center; padding:20px;">暂无文件，请拖拽文件到此处</div>
        </div>
        <div class="actions">
            <button class="cancel-btn" onclick="closeUploadModal()">取消</button>
            <button class="confirm-btn" id="uploadConfirmBtn" onclick="confirmUpload()">确认上传</button>
        </div>
    </div>
</div>

<!-- 编辑器 -->
<div class="editor-overlay" id="editorOverlay">
    <!-- 最小化长条 -->
    <div class="editor-minibar" id="editorMinibar">
        <div class="minibar-label">编辑器</div>
    </div>

    <div class="editor-sidebar" id="editorSidebar">
        <div class="sidebar-header">
            <span>📂 目录</span>
            <div class="header-actions">
                <button id="sidebarCollapseBtn" title="折叠侧边栏">◀</button>
            </div>
        </div>
        <div class="sidebar-actions">
            <button id="btnUpLevel"><span class="icon">⬆</span>上一级</button>
            <button id="btnRefreshSidebar"><span class="icon">⟳</span>刷新</button>
            <button id="btnNewFolderSidebar"><span class="icon">📁</span>新建文件夹</button>
            <button id="btnNewFileSidebar"><span class="icon">📄</span>新建文件</button>
        </div>
        <div class="sidebar-search">
            <input type="text" id="sidebarSearchInput" placeholder="搜索文件 (全局匹配)..." oninput="filterSidebarTree(this.value)">
        </div>
        <div class="sidebar-tree" id="sidebarTree"></div>
    </div>

    <div class="editor-main">
        <div class="editor-toolbar">
            <div class="toolbar-left">
                <button id="editorSaveBtn" class="save-btn" disabled style="opacity:0.5; cursor:default;">💾 保存</button>
                <button id="editorSaveAllBtn" class="save-all-btn" disabled style="opacity:0.5; cursor:default;">全部保存</button>
            </div>
            <div class="toolbar-right">
                <!-- AI 按钮占位，由扩展控制显示 -->
                <button id="toggleAISidebar" style="display:none; background:rgba(10,132,255,0.2); color:#0a84ff; border-radius:4px; padding:4px 10px;">Ai</button>
                <button id="searchToggleBtn">🔍 搜索</button>
                <button id="replaceToggleBtn">🔄 替换</button>
                <button id="gotoLineToggleBtn">↩ 跳转行</button>
                <button id="editorMinimizeBtn" title="最小化">_</button>
                <button id="editorCloseBtn" style="color:#f5f5f7;font-size:18px;line-height:1;" title="关闭编辑器">✕</button>
            </div>
        </div>
        <div class="editor-tabs" id="editorTabs"></div>
        <div class="editor-body-wrapper">
            <div class="editor-body" id="editorBody"></div>

            <!-- ===== 搜索面板 ===== -->
            <div class="editor-pop-panel" id="searchPanel">
                <div class="panel-row">
                    <input type="text" id="searchInput" placeholder="输入搜索内容...">
                    <button class="close-search-btn" id="closeSearchBtn">✕</button>
                </div>
                <div class="search-info">
                    <span class="match-count" id="matchCount">0 个匹配</span>
                    <span>按 Enter 跳转下一个</span>
                </div>
            </div>

            <!-- ===== 替换面板 ===== -->
            <div class="editor-pop-panel" id="replacePanel">
                <div class="panel-row">
                    <input type="text" id="replaceSearchInput" placeholder="查找...">
                    <button class="close-search-btn" id="closeReplaceBtn">✕</button>
                </div>
                <div class="panel-row">
                    <input type="text" id="replaceInput" placeholder="替换为...">
                    <button class="action-btn" id="replaceOneBtn">替换</button>
                    <button class="action-btn" id="replaceAllBtn">全部替换</button>
                </div>
                <div class="search-info">
                    <span class="match-count" id="replaceMatchCount">0 个匹配</span>
                </div>
            </div>

            <!-- ===== 跳转行面板 ===== -->
            <div class="editor-pop-panel" id="gotoPanel">
                <div class="panel-row">
                    <input type="number" id="gotoLineInput" placeholder="行号...">
                    <button class="close-search-btn" id="closeGotoBtn">✕</button>
                </div>
                <div class="panel-actions">
                    <button class="action-btn" id="gotoLineConfirmBtn">跳转</button>
                </div>
            </div>

<!-- ========== AI 扩展占位 ========== -->
<?php if (file_exists(__DIR__ . '/kzai.php')): ?>
    <?php 
    define('IN_KONTKZ', true);
    include __DIR__ . '/kzai.php'; 
    ?>
<?php endif; ?>
<!-- ========== AI 扩展占位结束 ========== -->

        </div>
        <div class="editor-statusbar">
            <div class="status-left">
                <span class="status-path" id="statusFilePath">文件位置: /var/www/html/bt.php</span>
                <span class="status-metric" id="statusWordCount">总字数: 0</span>
                <span class="status-metric" id="statusTokenCount">代码 Token: 0</span>
                <span class="status-error" id="statusErrors">错误: 0</span>
                <span class="status-warning" id="statusWarnings">警告: 0</span>
            </div>
            <div class="status-right">
                <span id="statusLineCount">行数: 1</span>
                <span id="statusCursor">行 1, 列 1</span>
                <span id="statusLang">语言: PHP</span>
            </div>
        </div>
    </div>
</div>

<script>
    // ========== 全局剪贴板 ==========
    let clipboard = {
        paths: [],
        action: null,
    };

    // ========== 图标映射 ==========
    function getFileIcon(filename, isDir) {
        if (isDir) return '📁';
        var ext = filename.split('.').pop().toLowerCase();
        var map = {
            'php': '📘', 'html': '📦', 'htm': '📦',
            'css': '🎨', 'js': '📜', 'json': '📋',
            'md': '📓', 'py': '🐍', 'sh': '⚙️',
            'conf': '⚙️', 'ini': '⚙️', 'log': '📋',
            'txt': '📝', 'jpg': '🖼️', 'jpeg': '🖼️',
            'png': '🖼️', 'gif': '🖼️', 'bmp': '🖼️',
            'webp': '🖼️', 'svg': '🖼️',
            'zip': '🟩', 'rar': '🟩', '7z': '🟩', 'tar': '🟩', 'gz': '🟩',
            'mp3': '🎵', 'wav': '🎵', 'flac': '🎵',
            'mp4': '🎬', 'avi': '🎬', 'mkv': '🎬',
            'exe': '⚙️', 'msi': '⚙️',
            'pdf': '📕', 'xls': '📊', 'xlsx': '📊'
        };
        return map[ext] || '📄';
    }

    // ========== Toast ==========
    function showToast(msg, type = 'info', duration = 3000) {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        const toast = document.createElement('div');
        toast.className = 'toast ' + type;
        const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
        toast.innerHTML = `<span class="toast-icon">${icons[type] || 'ℹ️'}</span><span>${msg}</span><span class="toast-close">✕</span>`;
        container.appendChild(toast);
        requestAnimationFrame(() => { toast.classList.add('show'); });
        const timer = setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
        const closeBtn = toast.querySelector('.toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                clearTimeout(timer);
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            });
        }
    }

    function bindIfExists(elementOrId, eventName, handler) {
        const element = typeof elementOrId === 'string' ? document.getElementById(elementOrId) : elementOrId;
        if (!element) return null;
        element.addEventListener(eventName, handler);
        return element;
    }

    // ========== Modal ==========
    function showModal(options) {
        return new Promise((resolve) => {
            const overlay = document.getElementById('customModal');
            const title = document.getElementById('modalTitle');
            const body = document.getElementById('modalBody');
            const cancelBtn = document.getElementById('modalCancelBtn');
            const confirmBtn = document.getElementById('modalConfirmBtn');

            body.innerHTML = '';
            if (typeof options.body === 'string') {
                body.textContent = options.body;
            } else if (options.body instanceof HTMLElement) {
                body.appendChild(options.body);
            } else {
                body.textContent = options.body || '';
            }

            title.textContent = options.title || '提示';
            if (options.type === 'confirm') {
                cancelBtn.style.display = 'inline-block';
                confirmBtn.textContent = options.confirmText || '确定';
                cancelBtn.textContent = options.cancelText || '取消';
            } else {
                cancelBtn.style.display = 'none';
                confirmBtn.textContent = options.confirmText || '确定';
            }

            const inputField = body.querySelector('input, textarea');
            if (inputField) {
                inputField.focus();
                if (options.defaultValue) inputField.value = options.defaultValue;
            }

            overlay.classList.add('show');

            function close(result) {
                overlay.classList.remove('show');
                resolve(result);
            }

            confirmBtn.onclick = function() {
                if (inputField) {
                    close(inputField.value);
                } else {
                    close(true);
                }
            };
            cancelBtn.onclick = function() { close(false); };
        });
    }

    function showAlert(title, msg, confirmText = '确定') {
        return showModal({ title, body: msg, type: 'alert', confirmText });
    }
    function showConfirm(title, msg, confirmText = '确定', cancelText = '取消') {
        return showModal({ title, body: msg, type: 'confirm', confirmText, cancelText });
    }
    function showPrompt(title, msg, defaultValue = '', confirmText = '确定', cancelText = '取消') {
        const input = document.createElement('input');
        input.type = 'text';
        input.value = defaultValue;
        input.placeholder = '请输入...';
        const body = document.createElement('div');
        body.textContent = msg + '\n';
        body.appendChild(input);
        return showModal({ title, body, type: 'confirm', confirmText, cancelText }).then(val => {
            if (val === false) return null;
            return input.value;
        });
    }

    // ========== 圆环 ==========
    const CIRCUMFERENCE = 100;
    function setCircle(id, percent, color = '#4caf50') {
        const circle = document.getElementById(id);
        if (!circle) return;
        const offset = CIRCUMFERENCE - (percent / 100) * CIRCUMFERENCE;
        circle.style.strokeDashoffset = offset;
        if (percent >= 80) circle.style.stroke = '#f44336';
        else if (percent >= 60) circle.style.stroke = '#ff9800';
        else circle.style.stroke = color;
    }

    // ========== 仪表盘折叠 ==========
    const dashRow = document.getElementById('dashRow');
    const dashCollapsed = document.getElementById('dashCollapsed');
    const dashToggleBtnExp = document.getElementById('dashToggleBtnExp');
    const dashInnerToggleBtn = document.getElementById('dashInnerToggleBtn');

    function toggleDashboard() {
        if (!dashRow || !dashCollapsed || !dashToggleBtnExp) return;
        const isCollapsed = dashCollapsed.style.display === 'flex';
        if (isCollapsed) {
            dashCollapsed.style.display = 'none';
            dashRow.style.display = 'grid';
            dashToggleBtnExp.innerText = '⬇ 收起';
        } else {
            dashRow.style.display = 'none';
            dashCollapsed.style.display = 'flex';
            dashToggleBtnExp.innerText = '⬆ 展开';
            updateDashboard();
        }
    }
    bindIfExists(dashToggleBtnExp, 'click', toggleDashboard);
    bindIfExists(dashInnerToggleBtn, 'click', toggleDashboard);

    function updateCollapsedBar(data) {
        const loadP = Math.min(data.load * 20, 100);
        const cpuP = data.cpu;
        const memP = data.memory.percent;
        const diskP = data.storage.percent;
        const segs = [loadP, cpuP, memP, diskP];
        const ids = ['cLoad', 'cCpu', 'cMem', 'cDisk'];
        const labels = ['负载', 'CPU', '内存', '磁盘'];

        ids.forEach((id, idx) => {
            const el = document.getElementById(id);
            const val = segs[idx];
            el.style.flexGrow = val;
            el.innerText = labels[idx] + ' ' + Math.round(val) + '%';
        });
    }

    // ========== 监控 ==========
    let lastDashData = {};
    let dashUpdatePending = false;
    let dashTimer;

    function updateDashboard() {
        if (document.hidden) return;
        fetch('?ajax=1')
            .then(r => r.json())
            .then(data => {
                const isChanged = (newVal, oldVal) => {
                    if (typeof newVal === 'number' && typeof oldVal === 'number') {
                        return Math.abs(newVal - oldVal) > 0.5;
                    }
                    if (typeof newVal === 'string' && typeof oldVal === 'string') {
                        return newVal !== oldVal;
                    }
                    if (typeof newVal === 'object' && newVal !== null && typeof oldVal === 'object' && oldVal !== null) {
                        for (let k in newVal) {
                            if (isChanged(newVal[k], oldVal[k])) return true;
                        }
                        return false;
                    }
                    return true;
                };
                if (!isChanged(data, lastDashData)) {
                    return;
                }
                lastDashData = JSON.parse(JSON.stringify(data));

                if (!dashUpdatePending) {
                    dashUpdatePending = true;
                    requestAnimationFrame(() => {
                        applyDashData(data);
                        dashUpdatePending = false;
                    });
                }
            })
            .catch(e => console.error('监控更新失败:', e));
    }

    function applyDashData(data) {
        document.getElementById('loadVal').innerText = data.load;
        let loadP = Math.min(data.load * 20, 100);
        document.getElementById('loadPercent').innerText = Math.round(loadP) + '%';
        setCircle('loadCircle', loadP, '#4caf50');

        document.getElementById('cpuVal').innerHTML = data.cpu + '<span class="unit">%</span>';
        document.getElementById('cpuPercent').innerText = data.cpu + '%';
        setCircle('cpuCircle', data.cpu, '#2196f3');

        let mem = data.memory;
        document.getElementById('memUsed').innerText = mem.used;
        document.getElementById('memTotal').innerText = mem.total;
        document.getElementById('memPercent').innerText = mem.percent + '%';
        setCircle('memCircle', mem.percent, '#e91e63');

        let disk = data.storage;
        document.getElementById('diskUsed').innerText = disk.used;
        document.getElementById('diskTotal').innerText = disk.total;
        document.getElementById('diskPercent').innerText = disk.percent + '%';
        setCircle('diskCircle', disk.percent, '#ff9800');

        if (dashCollapsed.style.display === 'flex') {
            updateCollapsedBar(data);
        }
    }

    function startMonitor() {
        if (dashTimer) clearInterval(dashTimer);
        dashTimer = setInterval(updateDashboard, 3000);
    }
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            if (dashTimer) clearInterval(dashTimer);
        } else {
            startMonitor();
        }
    });
    startMonitor();

    // ========== 目录加载 ==========
    let loadDirTimeout;
    let currentDirPath = '';

    function loadDirectory(path) {
        if (path === currentDirPath) return;
        clearTimeout(loadDirTimeout);
        const grid = document.getElementById('fileGrid');
        grid.classList.add('loading');
        currentDirPath = path;

        loadDirTimeout = setTimeout(() => {
            fetch('?api=1&path=' + encodeURIComponent(path))
                .then(r => r.json())
                .then(data => {
                    const nav = document.getElementById('breadcrumbNav');
                    let html = '';
                    data.breadcrumbs.forEach((crumb, idx) => {
                        if (idx > 0) html += '<span>›</span>';
                        html += `<a href="javascript:void(0)" data-path="${encodeURIComponent(crumb.path)}">${escapeHtml(crumb.name)}</a>`;
                    });
                    html += `<span class="count">共 ${data.total} 个项目</span>`;
                    html += `<a href="?downloadTree=1" class="btn-tree">📄 下载结构树</a>`;
                    nav.innerHTML = html;

                    if (data.items.length === 0) {
                        grid.innerHTML = '<div class="placeholder">此目录为空</div>';
                    } else {
                        let gridHtml = '';
                        data.items.forEach(item => {
                            const isDir = item.type === 'dir';
                            const icon = item.icon || getFileIcon(item.name, isDir);
                            const sub = isDir ? '文件夹' : item.size;
                            gridHtml += `
                                <a href="javascript:void(0)" class="file-item" data-path="${encodeURIComponent(item.path)}" data-type="${item.type}">
                                    <div class="file-icon">${icon}</div>
                                    <div class="file-info">
                                        <div class="file-name">${escapeHtml(item.name)}</div>
                                        <div class="file-sub">${sub}</div>
                                    </div>
                                </a>
                            `;
                        });
                        grid.innerHTML = gridHtml;
                    }
                    history.pushState({ path: data.currentPath }, '', '?path=' + encodeURIComponent(data.currentPath));
                    grid.classList.remove('loading');
                })
                .catch(e => {
                    console.error('加载目录失败:', e);
                    grid.classList.remove('loading');
                });
        }, 150);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getCurrentPath() {
        const params = new URLSearchParams(window.location.search);
        let path = params.get('path') || '<?php echo $root_path; ?>';
        return decodeURIComponent(path);
    }

    // ========== 编辑器核心 ==========
    let editor = null;
    let isEditorReady = false;
    let tabData = {};
    let tabOrder = [];
    let activeTabPath = null;
    let isApplyingEditorValue = false;

    let currentErrors = [];
    let errorMarkers = [];

    const EDITABLE_EXTS = ['html', 'htm', 'php', 'css', 'js', 'txt', 'json', 'xml', 'md', 'py', 'sh', 'conf', 'ini', 'log'];

    function getFileExt(path) {
        const parts = path.split('.');
        return parts.length > 1 ? parts.pop().toLowerCase() : '';
    }
    function isEditableFile(path) { return EDITABLE_EXTS.includes(getFileExt(path)); }
    function getMode(ext) {
        const map = {
            'html': 'htmlmixed', 'htm': 'htmlmixed', 'php': 'php', 'css': 'css',
            'js': 'javascript', 'json': 'javascript', 'xml': 'xml', 'md': 'text',
            'py': 'text', 'sh': 'text', 'conf': 'text', 'ini': 'text', 'log': 'text'
        };
        return map[ext] || 'text';
    }

    function countVisibleChars(content) {
        return String(content || '').replace(/\s/g, '').length;
    }
    function estimateCodeTokens(content) {
        const matches = String(content || '').match(/[\u4e00-\u9fff]|[A-Za-z_][A-Za-z0-9_]*|\d+|[^\s]/g);
        return matches ? matches.length : 0;
    }
    function countLines(content) {
        const text = String(content || '');
        return text.length ? text.split(/\r\n|\r|\n/).length : 1;
    }

    let metricsUpdateTimeout;

    function updateEditorMetrics(content) {
        clearTimeout(metricsUpdateTimeout);
        metricsUpdateTimeout = setTimeout(() => {
            const text = String(content || '');
            document.getElementById('statusWordCount').innerText = '总字数: ' + countVisibleChars(text);
            document.getElementById('statusTokenCount').innerText = '代码 Token: ' + estimateCodeTokens(text);
            document.getElementById('statusLineCount').innerText = '行数: ' + countLines(text);
        }, 100);
    }

    function setEditorContent(content, mode) {
        if (!editor) return;
        isApplyingEditorValue = true;
        editor.setValue(content);
        editor.setOption('mode', mode || 'text');
        editor.refresh();
        editor.setCursor({line:0,ch:0});
        isApplyingEditorValue = false;
        updateEditorMetrics(content);
    }

    // --- 错误标记 ---
    function clearErrorMarkers() {
        errorMarkers.forEach(marker => {
            if (marker) editor.setGutterMarker(marker.line, 'error-gutter', null);
        });
        errorMarkers = [];
    }

    function updateErrorMarkers(errors) {
        clearErrorMarkers();
        currentErrors = errors;
        if (!editor) return;
        errors.forEach(err => {
            if (err.line > 0 && err.line <= editor.lineCount()) {
                const marker = document.createElement('span');
                marker.textContent = '✖';
                marker.style.color = '#f44336';
                marker.style.cursor = 'pointer';
                marker.title = err.message || '语法错误';
                marker.addEventListener('click', function(e) {
                    e.stopPropagation();
                    gotoLineNumber(err.line);
                });
                const line = err.line - 1;
                const existingMarker = editor.setGutterMarker(line, 'error-gutter', marker);
                errorMarkers.push({line: line, marker: existingMarker});
            }
        });
        document.getElementById('statusErrors').textContent = '错误: ' + errors.length;
        document.getElementById('statusWarnings').textContent = '警告: 0';
    }

    function checkFile(path) {
        const ext = getFileExt(path);
        if (ext !== 'php') {
            clearErrorMarkers();
            document.getElementById('statusErrors').textContent = '错误: 0';
            document.getElementById('statusWarnings').textContent = '警告: 0';
            return Promise.resolve();
        }
        return fetch('?check=1&path=' + encodeURIComponent(path))
            .then(r => r.json())
            .then(data => {
                if (data.code === 0) {
                    updateErrorMarkers(data.errors || []);
                } else {
                    console.error('检查失败', data.msg);
                }
            })
            .catch(e => console.error('检查请求失败', e));
    }

    function updateSaveButtons() {
        const hasOpenFiles = tabOrder.length > 0;
        const saveBtn = document.getElementById('editorSaveBtn');
        const saveAllBtn = document.getElementById('editorSaveAllBtn');
        if (saveBtn) {
            saveBtn.disabled = !hasOpenFiles;
            saveBtn.style.opacity = hasOpenFiles ? '1' : '0.5';
            saveBtn.style.cursor = hasOpenFiles ? 'pointer' : 'default';
        }
        if (saveAllBtn) {
            saveAllBtn.disabled = !hasOpenFiles;
            saveAllBtn.style.opacity = hasOpenFiles ? '1' : '0.5';
            saveAllBtn.style.cursor = hasOpenFiles ? 'pointer' : 'default';
        }
    }

    // --- 初始化编辑器 ---
    function initEditor() {
        if (isEditorReady) return;
        const body = document.getElementById('editorBody');
        editor = CodeMirror(body, {
            value: '// 加载中...',
            mode: 'text',
            theme: 'dracula',
            lineNumbers: true,
            gutters: ["CodeMirror-linenumbers", "error-gutter"],
            indentUnit: 4,
            tabSize: 4,
            lineWrapping: false,
            styleActiveLine: true,
            viewportMargin: 100,
            extraKeys: {
                "Ctrl-S": function() { saveFile(); },
                "Cmd-S": function() { saveFile(); }
            }
        });

        editor.on('gutterClick', function(cm, line, gutter, ev) {
            if (gutter === 'error-gutter') {
                const errLine = line + 1;
                const err = currentErrors.find(e => e.line === errLine);
                if (err) {
                    gotoLineNumber(errLine);
                }
            }
        });
        editor.on('cursorActivity', function() {
            const pos = editor.getCursor();
            document.getElementById('statusCursor').innerText = '行 ' + (pos.line + 1) + ', 列 ' + (pos.ch + 1);
        });

        let changeDebounce = null;
        editor.on('change', function(cm) {
            if (!activeTabPath || isApplyingEditorValue || !tabData[activeTabPath]) return;
            clearTimeout(changeDebounce);
            changeDebounce = setTimeout(() => {
                const currentContent = cm.getValue();
                const currentTab = tabData[activeTabPath];
                const wasDirty = !!currentTab.dirty;
                currentTab.content = currentContent;
                currentTab.dirty = currentContent !== currentTab.savedContent;
                updateEditorMetrics(currentContent);
                if (wasDirty !== currentTab.dirty) {
                    renderTabs();
                }
            }, 50);
        });

        isEditorReady = true;
        updateSaveButtons();
    }

    function renderTabs() {
        const container = document.getElementById('editorTabs');
        let html = '';
        tabOrder.forEach(p => {
            const name = p.split('/').pop();
            const active = p === activeTabPath ? 'active' : '';
            const icon = getFileIcon(name, false);
            const dirtyMark = tabData[p] && tabData[p].dirty ? ' ●' : '';
            html += `<div class="tab ${active}" data-path="${encodeURIComponent(p)}">
                <span>${icon} ${escapeHtml(name)}${dirtyMark}</span>
                <span class="close-tab" data-path="${encodeURIComponent(p)}">×</span>
            </div>`;
        });
        container.innerHTML = html;
        container.querySelectorAll('.close-tab').forEach(el => {
            el.addEventListener('click', function(e) {
                e.stopPropagation();
                const p = decodeURIComponent(this.dataset.path);
                closeTab(p);
            });
        });
        container.querySelectorAll('.tab').forEach(el => {
            el.addEventListener('click', function(e) {
                if (e.target.classList.contains('close-tab')) return;
                const p = decodeURIComponent(this.dataset.path);
                switchTab(p);
            });
        });
        // 右键标签页添加到AI引用
        container.querySelectorAll('.tab').forEach(el => {
            el.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const p = decodeURIComponent(this.dataset.path);
                const event = new CustomEvent('tab-contextmenu', { detail: { path: p } });
                document.dispatchEvent(event);
            });
        });
        if (activeTabPath && tabData[activeTabPath]) {
            document.getElementById('statusLang').innerText = '语言: ' + tabData[activeTabPath].mode.toUpperCase();
        }
        updateSaveButtons();
    }

    function updateStatusBar(path) {
        document.getElementById('statusFilePath').innerText = '文件位置: ' + path;
        if (tabData[path]) {
            document.getElementById('statusLang').innerText = '语言: ' + tabData[path].mode.toUpperCase();
            updateEditorMetrics(tabData[path].content);
        } else {
            updateEditorMetrics('');
        }
    }

    function switchTab(path) {
        if (!tabData[path]) return;
        if (activeTabPath && tabData[activeTabPath]) {
            tabData[activeTabPath].content = editor.getValue();
        }
        activeTabPath = path;
        renderTabs();
        const data = tabData[path];
        setEditorContent(data.content, data.mode);
        updateStatusBar(path);
        if (path === activeTabPath) {
            if (tabData[path].checked !== false) {
                checkFile(path).then(() => {
                    tabData[path].checked = true;
                });
            }
        }
        document.querySelectorAll('.sidebar-tree .tree-item').forEach(el => {
            el.classList.toggle('active', el.dataset.path && decodeURIComponent(el.dataset.path) === path);
        });
    }

    function closeTab(path) {
        const idx = tabOrder.indexOf(path);
        if (idx === -1) return;
        if (tabData[path] && tabData[path].dirty) {
            showConfirm('未保存', '文件 "' + path.split('/').pop() + '" 有未保存的更改，确定要关闭吗？', '关闭', '取消')
                .then(confirmed => {
                    if (!confirmed) return;
                    doCloseTab(path);
                });
        } else {
            doCloseTab(path);
        }
    }

    function doCloseTab(path) {
        const idx = tabOrder.indexOf(path);
        if (idx === -1) return;
        tabOrder.splice(idx, 1);
        delete tabData[path];
        if (path === activeTabPath) {
            if (tabOrder.length > 0) {
                const newPath = tabOrder[Math.min(idx, tabOrder.length - 1)];
                switchTab(newPath);
            } else {
                closeEditor();
            }
        } else {
            renderTabs();
        }
        updateSaveButtons();
    }

    // --- 编辑器打开/关闭/最小化 ---
    function openEditor(path) {
        initEditor();
        if (tabData[path]) {
            document.getElementById('editorOverlay').classList.add('active');
            document.getElementById('editorOverlay').classList.remove('minimized');
            document.body.classList.remove('editor-minimized');
            if (window.innerWidth <= 600) {
                document.getElementById('editorSidebar').classList.add('show');
            }
            switchTab(path);
            if (window.AI_EXTENSION_LOADED) {
                document.getElementById('toggleAISidebar').style.display = 'inline-block';
            }
            return;
        }
        document.getElementById('editorOverlay').classList.add('active');
        document.getElementById('editorOverlay').classList.remove('minimized');
        document.body.classList.remove('editor-minimized');
        if (window.innerWidth <= 600) {
            document.getElementById('editorSidebar').classList.add('show');
        }
        setEditorContent('// 正在加载...', 'text');
        fetch('?getcontent=1&path=' + encodeURIComponent(path))
            .then(r => r.json())
            .then(data => {
                if (data.code !== 0) {
                    showAlert('加载失败', data.msg);
                    closeEditor();
                    return;
                }
                const ext = getFileExt(path);
                const mode = getMode(ext);
                const content = data.content;
                tabData[path] = { content, savedContent: content, mode, path, dirty: false, checked: false };
                tabOrder.push(path);
                activeTabPath = path;
                renderTabs();
                setEditorContent(content, mode);
                updateStatusBar(path);
                const basePath = path.substring(0, path.lastIndexOf('/'));
                loadSidebarTree(basePath);
                document.querySelectorAll('.sidebar-tree .tree-item').forEach(el => {
                    el.classList.toggle('active', el.dataset.path && decodeURIComponent(el.dataset.path) === path);
                });
                if (ext === 'php') {
                    checkFile(path).then(() => {
                        tabData[path].checked = true;
                    });
                }
                if (window.AI_EXTENSION_LOADED) {
                    document.getElementById('toggleAISidebar').style.display = 'inline-block';
                }
            })
            .catch(e => {
                showAlert('加载失败', e.message);
                closeEditor();
            });
    }

    function closeEditor() {
        document.getElementById('editorOverlay').classList.remove('active');
        document.getElementById('editorOverlay').classList.remove('minimized');
        document.body.classList.remove('editor-minimized');
        hideEditorPanels();
        tabData = {};
        tabOrder = [];
        activeTabPath = null;
        renderTabs();
        document.getElementById('statusFilePath').innerText = '文件位置: 无';
        updateEditorMetrics('');
        document.getElementById('statusLineCount').innerText = '行数: 1';
        document.getElementById('statusLang').innerText = '语言: -';
        clearErrorMarkers();
        document.getElementById('statusErrors').textContent = '错误: 0';
        document.getElementById('statusWarnings').textContent = '警告: 0';
        updateSaveButtons();
        document.getElementById('toggleAISidebar').style.display = 'none';
    }

    function minimizeEditor() {
        const overlay = document.getElementById('editorOverlay');
        if (!overlay.classList.contains('active')) {
            showToast('编辑器未打开', 'warning');
            return;
        }
        overlay.classList.add('minimized');
        document.body.classList.add('editor-minimized');
        document.getElementById('editorSidebar').classList.remove('show');
        if (activeTabPath && tabData[activeTabPath]) {
            tabData[activeTabPath].content = editor.getValue();
        }
        hideEditorPanels();
    }

    function restoreEditor() {
        const overlay = document.getElementById('editorOverlay');
        if (!overlay.classList.contains('active')) return;
        overlay.classList.remove('minimized');
        document.body.classList.remove('editor-minimized');
        if (window.innerWidth <= 600) {
            document.getElementById('editorSidebar').classList.add('show');
        }
        if (activeTabPath && tabData[activeTabPath]) {
            setEditorContent(tabData[activeTabPath].content, tabData[activeTabPath].mode);
            updateStatusBar(activeTabPath);
        }
    }

    function saveFile(path = activeTabPath, options = {}) {
        const silent = !!options.silent;
        if (!path || !tabData[path]) {
            showAlert('提示', '没有打开的文件');
            return Promise.resolve(false);
        }
        const isActiveFile = path === activeTabPath;
        const content = isActiveFile && editor ? editor.getValue() : tabData[path].content;
        tabData[path].content = content;
        const formData = new FormData();
        formData.append('path', path);
        formData.append('content', content);

        const statusBar = document.getElementById('statusFilePath');
        const originalText = statusBar.innerText;
        if (isActiveFile) {
            statusBar.innerText = '💾 保存中...';
        }

        return fetch('?save=1', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.code === 0) {
                tabData[path].savedContent = content;
                tabData[path].dirty = false;
                renderTabs();
                if (isActiveFile) {
                    statusBar.innerText = '✅ 保存成功 | ' + originalText;
                    updateStatusBar(path);
                }
                if (!silent) {
                    showToast('文件已保存', 'success');
                }
                if (getFileExt(path) === 'php') {
                    checkFile(path).then(() => {
                        tabData[path].checked = true;
                    });
                }
                return true;
            } else {
                if (isActiveFile) {
                    statusBar.innerText = '❌ 保存失败: ' + data.msg;
                }
                if (!silent) {
                    showAlert('保存失败', data.msg);
                }
                return false;
            }
        })
        .catch(e => {
            if (isActiveFile) {
                statusBar.innerText = '❌ 保存出错';
            }
            if (!silent) {
                showAlert('保存出错', e.message);
            }
            return false;
        });
    }

    async function saveAllFiles() {
        const pendingPaths = tabOrder.filter(path => {
            if (!tabData[path]) return false;
            return tabData[path].dirty || tabData[path].content !== tabData[path].savedContent;
        });
        if (pendingPaths.length === 0) {
            showToast('没有需要保存的文件', 'info');
            return;
        }
        const failed = [];
        for (const path of pendingPaths) {
            const ok = await saveFile(path, { silent: true });
            if (!ok) {
                failed.push(path.split('/').pop());
            }
        }
        if (failed.length > 0) {
            showAlert('部分保存失败', '以下文件保存失败：' + failed.join('、'));
            return;
        }
        if (activeTabPath) {
            updateStatusBar(activeTabPath);
        }
        refreshSidebar(false);
        showToast('全部保存完成，共保存 ' + pendingPaths.length + ' 个文件', 'success');
    }

    // ========== 侧边栏树 ==========
    let currentSidebarPath = '';
    let folderDataCache = {};

    function setTreeChildrenState(childrenContainer, shouldOpen, immediate = false) {
        if (!childrenContainer) return;
        if (childrenContainer._treeTransitionHandler) {
            childrenContainer.removeEventListener('transitionend', childrenContainer._treeTransitionHandler);
            childrenContainer._treeTransitionHandler = null;
        }

        if (immediate) {
            childrenContainer.classList.add('instant');
        }

        if (shouldOpen) {
            childrenContainer.classList.add('open');
            const targetHeight = childrenContainer.scrollHeight;
            childrenContainer.style.maxHeight = immediate ? 'none' : '0px';
            if (immediate) {
                childrenContainer.classList.remove('instant');
                return;
            }

            requestAnimationFrame(() => {
                childrenContainer.style.maxHeight = targetHeight + 'px';
            });

            const onTransitionEnd = function(e) {
                if (e.propertyName !== 'max-height') return;
                childrenContainer.style.maxHeight = 'none';
                childrenContainer.removeEventListener('transitionend', onTransitionEnd);
                childrenContainer._treeTransitionHandler = null;
            };
            childrenContainer._treeTransitionHandler = onTransitionEnd;
            childrenContainer.addEventListener('transitionend', onTransitionEnd);
            return;
        }

        const currentHeight = childrenContainer.scrollHeight;
        childrenContainer.style.maxHeight = currentHeight + 'px';
        if (immediate) {
            childrenContainer.classList.remove('open');
            childrenContainer.style.maxHeight = '0px';
            childrenContainer.classList.remove('instant');
            return;
        }

        requestAnimationFrame(() => {
            childrenContainer.classList.remove('open');
            childrenContainer.style.maxHeight = '0px';
        });
    }

    function loadSidebarTree(path) {
        currentSidebarPath = path;
        fetch('?api=1&path=' + encodeURIComponent(path))
            .then(r => r.json())
            .then(data => {
                const container = document.getElementById('sidebarTree');
                container.innerHTML = '';
                const rootNode = document.createElement('div');
                rootNode.className = 'tree-node';
                rootNode.dataset.path = data.currentPath;
                rootNode.dataset.type = 'dir';

                const rootLabel = document.createElement('div');
                rootLabel.className = 'tree-label';
                rootLabel.innerHTML = `<span class="icon">📂</span><span class="name"><?php echo $root_path; ?></span>`;
                rootLabel.addEventListener('click', function(e) {
                    const path = rootNode.dataset.path;
                    loadDirectory(path);
                });
                rootNode.appendChild(rootLabel);

                const rootChildren = document.createElement('div');
                rootChildren.className = 'tree-children open';
                rootChildren.dataset.loaded = 'true';
                rootChildren.dataset.path = data.currentPath;

                const fragment = buildTreeNodes(data.items, data.currentPath);
                rootChildren.appendChild(fragment);
                rootNode.appendChild(rootChildren);
                container.appendChild(rootNode);
                setTreeChildrenState(rootChildren, true, true);
            });
    }

    function buildTreeNodes(items, parentPath) {
        const fragment = document.createDocumentFragment();
        items.forEach(item => {
            const node = document.createElement('div');
            node.className = 'tree-node';
            node.dataset.path = item.path;
            node.dataset.type = item.type;

            const label = document.createElement('div');
            label.className = 'tree-label';

            if (item.type === 'dir') {
                const toggle = document.createElement('span');
                toggle.className = 'toggle-icon';
                toggle.textContent = '▶';
                toggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleFolder(this, item.path);
                });
                label.appendChild(toggle);

                const icon = document.createElement('span');
                icon.className = 'icon';
                icon.textContent = item.icon || '📁';
                label.appendChild(icon);
            } else {
                const icon = document.createElement('span');
                icon.className = 'icon';
                icon.textContent = item.icon || getFileIcon(item.name, false);
                label.appendChild(icon);
            }

            const name = document.createElement('span');
            name.className = 'name';
            name.textContent = item.name;
            label.appendChild(name);

            label.addEventListener('click', function(e) {
                if (e.target.closest('.toggle-icon')) return;
                const path = node.dataset.path;
                const type = node.dataset.type;
                if (type === 'dir') {
                    loadDirectory(path);
                    const toggle = label.querySelector('.toggle-icon');
                    if (toggle) {
                        toggleFolder(toggle, path);
                    }
                } else if (isEditableFile(path)) {
                    openEditor(path);
                } else {
                    window.location.href = '?download=1&path=' + encodeURIComponent(path);
                }
            });
            node.appendChild(label);

            if (item.type === 'dir') {
                const children = document.createElement('div');
                children.className = 'tree-children';
                children.dataset.loaded = 'false';
                children.dataset.path = item.path;
                node.appendChild(children);
            }
            fragment.appendChild(node);
        });
        return fragment;
    }

    function toggleFolder(toggleEl, folderPath) {
        const parentNode = toggleEl.closest('.tree-node');
        const childrenContainer = parentNode.querySelector('.tree-children');
        if (!childrenContainer) return;

        const shouldOpen = !childrenContainer.classList.contains('open');

        if (childrenContainer.dataset.loaded === 'false') {
            if (folderDataCache[folderPath]) {
                const fragment = buildTreeNodes(folderDataCache[folderPath], folderPath);
                childrenContainer.innerHTML = '';
                childrenContainer.appendChild(fragment);
                childrenContainer.dataset.loaded = 'true';
                toggleEl.classList.add('open');
                setTreeChildrenState(childrenContainer, true);
                return;
            }
            fetch('?api=1&path=' + encodeURIComponent(folderPath))
                .then(r => r.json())
                .then(data => {
                    folderDataCache[folderPath] = data.items;
                    childrenContainer.innerHTML = '';
                    const fragment = buildTreeNodes(data.items, folderPath);
                    childrenContainer.appendChild(fragment);
                    childrenContainer.dataset.loaded = 'true';
                    toggleEl.classList.add('open');
                    setTreeChildrenState(childrenContainer, true);
                })
                .catch(e => console.error('加载子节点失败:', e));
        } else {
            toggleEl.classList.toggle('open', shouldOpen);
            setTreeChildrenState(childrenContainer, shouldOpen);
        }
    }

    function goUpLevel() {
        if (!currentSidebarPath) return;
        const parentPath = currentSidebarPath.substring(0, currentSidebarPath.lastIndexOf('/'));
        if (parentPath && parentPath.length > 0) {
            loadSidebarTree(parentPath);
            loadDirectory(parentPath);
        }
    }

    function refreshSidebar(showToastMessage = true) {
        const sidebarSearchInput = document.getElementById('sidebarSearchInput');
        if (sidebarSearchInput) {
            sidebarSearchInput.value = '';
        }
        folderDataCache = {};
        const sidebarPath = activeTabPath
            ? activeTabPath.substring(0, activeTabPath.lastIndexOf('/'))
            : (currentSidebarPath || getCurrentPath());
        loadSidebarTree(sidebarPath);
        loadDirectory(getCurrentPath());
        if (showToastMessage) {
            showToast('目录已刷新', 'info', 1200);
        }
    }

    // ========== 全局搜索 ==========
    let sidebarSearchTimeout = null;
    function filterSidebarTree(keyword) {
        const container = document.getElementById('sidebarTree');
        keyword = keyword.toLowerCase().trim();
        clearTimeout(sidebarSearchTimeout);
        if (keyword.length < 2) {
            if (currentSidebarPath) loadSidebarTree(currentSidebarPath);
            return;
        }
        container.innerHTML = '<div style="padding:12px;color:#858585;text-align:center;">🔍 正在全局搜索...</div>';
        sidebarSearchTimeout = setTimeout(() => {
            fetch('?search=1&keyword=' + encodeURIComponent(keyword))
                .then(r => r.json())
                .then(data => {
                    if (data.items.length === 0) {
                        container.innerHTML = '<div style="padding:12px;color:#858585;text-align:center;">未找到匹配的文件</div>';
                        return;
                    }
                    let html = '';
                    data.items.forEach(item => {
                        const icon = item.icon || getFileIcon(item.name, false);
                        html += `<div class="tree-item tree-file" data-path="${encodeURIComponent(item.path)}" data-type="${item.type}" onclick="handleSidebarClick('${encodeURIComponent(item.path)}', '${item.type}')">
                            <span class="tree-icon">${icon}</span> ${escapeHtml(item.name)}
                        </div>`;
                    });
                    container.innerHTML = html;
                })
                .catch(e => {
                    console.error(e);
                    if (currentSidebarPath) loadSidebarTree(currentSidebarPath);
                });
        }, 300);
    }

    function handleSidebarClick(encodedPath, type) {
        const path = decodeURIComponent(encodedPath);
        if (type === 'dir') {
            loadDirectory(path);
            loadSidebarTree(path);
        } else if (isEditableFile(path)) {
            openEditor(path);
        } else {
            window.location.href = '?download=1&path=' + encodeURIComponent(path);
        }
    }

    // ========== 编辑器搜索 ==========
    let searchMatches = [];
    let currentMatchIndex = -1;
    let searchMarkers = [];

    function clearSearchHighlights() {
        searchMarkers.forEach(marker => marker.clear());
        searchMarkers = [];
    }

    function setMatchCount(elementId, count) {
        const counter = document.getElementById(elementId);
        if (counter) {
            counter.innerText = count + ' 个匹配';
        }
    }

    function updateSearchCount(query, countElementId = 'matchCount') {
        clearSearchHighlights();
        searchMatches = [];
        if (!query.trim() || !editor) {
            setMatchCount(countElementId, 0);
            return 0;
        }
        const cursor = editor.getSearchCursor(query, {line:0, ch:0}, {caseFold: true});
        while (cursor.findNext()) {
            searchMatches.push({
                from: cursor.from(),
                to: cursor.to()
            });
        }
        searchMatches.forEach(match => {
            const marker = editor.markText(match.from, match.to, {className: 'cm-search-match'});
            searchMarkers.push(marker);
        });
        setMatchCount(countElementId, searchMatches.length);
        currentMatchIndex = -1;
        return searchMatches.length;
    }

    function jumpToNextMatch() {
        if (searchMatches.length === 0 || !editor) return false;
        currentMatchIndex = (currentMatchIndex + 1) % searchMatches.length;
        const match = searchMatches[currentMatchIndex];
        editor.focus();
        editor.setSelection(match.from, match.to);
        editor.scrollIntoView(match.from, 80);
        return true;
    }

    function getNextReplaceCursor(query) {
        if (!editor || !query.trim()) return null;
        const startPos = editor.getCursor('to');
        let cursor = editor.getSearchCursor(query, startPos, {caseFold: true});
        if (cursor.findNext()) return cursor;
        cursor = editor.getSearchCursor(query, {line:0, ch:0}, {caseFold: true});
        return cursor.findNext() ? cursor : null;
    }

    function replaceNextMatch(query, replacement) {
        if (!editor) return;
        if (!query.trim()) {
            showToast('请先输入要查找的内容', 'warning', 1200);
            return;
        }
        const cursor = getNextReplaceCursor(query);
        if (!cursor) {
            updateSearchCount(query, 'replaceMatchCount');
            showToast('未找到匹配内容', 'warning', 1200);
            return;
        }
        const from = cursor.from();
        editor.operation(function() {
            editor.setSelection(from, cursor.to());
            cursor.replace(replacement);
        });
        const afterPos = { line: from.line, ch: from.ch + String(replacement).length };
        editor.focus();
        editor.setCursor(afterPos);
        updateSearchCount(query, 'replaceMatchCount');
        const nextCursor = getNextReplaceCursor(query);
        if (nextCursor) {
            editor.setSelection(nextCursor.from(), nextCursor.to());
            editor.scrollIntoView(nextCursor.from(), 80);
        }
    }

    function replaceAllMatches(query, replacement) {
        if (!editor) return;
        if (!query.trim()) {
            showToast('请先输入要查找的内容', 'warning', 1200);
            return;
        }
        const ranges = [];
        const cursor = editor.getSearchCursor(query, {line:0, ch:0}, {caseFold: true});
        while (cursor.findNext()) {
            ranges.push({
                from: cursor.from(),
                to: cursor.to()
            });
        }
        if (ranges.length === 0) {
            updateSearchCount(query, 'replaceMatchCount');
            showToast('未找到匹配内容', 'warning', 1200);
            return;
        }
        editor.operation(function() {
            for (let i = ranges.length - 1; i >= 0; i--) {
                editor.replaceRange(replacement, ranges[i].from, ranges[i].to);
            }
        });
        updateSearchCount(query, 'replaceMatchCount');
        showToast('已替换 ' + ranges.length + ' 处', 'success', 1400);
    }

    function gotoLineNumber(lineNumber) {
        if (!editor) return;
        const parsed = parseInt(lineNumber, 10);
        if (!Number.isFinite(parsed)) {
            showToast('请输入有效的行号', 'warning', 1200);
            return;
        }
        const totalLines = editor.lineCount();
        const safeLine = Math.min(Math.max(parsed, 1), totalLines);
        const target = { line: safeLine - 1, ch: 0 };
        editor.focus();
        editor.setCursor(target);
        editor.scrollIntoView(target, 80);
        const gotoInput = document.getElementById('gotoLineInput');
        if (gotoInput) {
            gotoInput.value = safeLine;
        }
    }

    // ========== 上传相关 ==========
    let uploadFiles = [];
    let uploadTargetPath = '';

    function showUploadModal() {
        document.getElementById('uploadModal').classList.add('show');
        renderUploadList();
    }
    function closeUploadModal() {
        document.getElementById('uploadModal').classList.remove('show');
        uploadFiles = [];
        renderUploadList();
    }
    function renderUploadList() {
        const container = document.getElementById('uploadFileList');
        if (uploadFiles.length === 0) {
            container.innerHTML = '<div style="color:#858585;text-align:center;padding:20px;">暂无文件，请拖拽文件到此处</div>';
            return;
        }
        let html = '';
        uploadFiles.forEach((file, index) => {
            const sizeKB = (file.size / 1024).toFixed(1);
            html += `<div class="file-entry">
                <span>${escapeHtml(file.name)} (${sizeKB} KB)</span>
                <button class="remove-btn" onclick="removeUploadFile(${index})">✕</button>
            </div>`;
        });
        container.innerHTML = html;
    }
    function removeUploadFile(index) {
        uploadFiles.splice(index, 1);
        renderUploadList();
    }
    function addFilesToUpload(files) {
        const modal = document.getElementById('uploadModal');
        const isShowing = modal.classList.contains('show');
        for (let f of files) {
            uploadFiles.push(f);
        }
        if (!isShowing) {
            showUploadModal();
        } else {
            renderUploadList();
        }
    }
    function confirmUpload() {
        if (uploadFiles.length === 0) return;
        const formData = new FormData();
        formData.append('target_path', uploadTargetPath);
        for (let file of uploadFiles) {
            formData.append('files[]', file);
        }
        const confirmBtn = document.getElementById('uploadConfirmBtn');
        confirmBtn.disabled = true;
        confirmBtn.textContent = '上传中...';

        fetch('?upload=1', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        })
        .then(r => r.json())
        .then(data => {
            if (data.code === 0) {
                showToast('上传成功，成功 ' + data.uploaded.length + ' 个文件' + (data.errors.length ? '，失败 ' + data.errors.length + ' 个' : ''), 'success');
                closeUploadModal();
                loadDirectory(uploadTargetPath);
            } else {
                showAlert('上传失败', data.msg);
            }
        })
        .catch(e => showAlert('上传出错', e.message))
        .finally(() => {
            confirmBtn.disabled = false;
            confirmBtn.textContent = '确认上传';
        });
    }

    // ========== 拖拽事件 ==========
    const fileExplorer = document.getElementById('fileExplorer');
    bindIfExists(fileExplorer, 'dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        this.classList.add('drag-over');
    });
    bindIfExists(fileExplorer, 'dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
    });
    bindIfExists(fileExplorer, 'drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            uploadTargetPath = getCurrentPath();
            addFilesToUpload(files);
        }
    });
    const uploadModalEl = document.getElementById('uploadModal');
    bindIfExists(uploadModalEl, 'dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
    });
    bindIfExists(uploadModalEl, 'drop', function(e) {
        e.preventDefault();
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            if (!uploadTargetPath) uploadTargetPath = getCurrentPath();
            addFilesToUpload(files);
        }
    });

    // ========== 新建文件/文件夹 ==========
    function createNewFile() {
        const dir = getCurrentPath();
        showPrompt('新建文件', '请输入文件名（含扩展名）:', 'untitled.txt').then(name => {
            if (name === null || name.trim() === '') return;
            const formData = new FormData();
            formData.append('dir', dir);
            formData.append('name', name.trim());
            formData.append('content', '');
            fetch('?action=create_file', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(data => {
                if (data.code === 0) {
                    showToast('文件创建成功', 'success');
                    loadDirectory(dir);
                    refreshSidebar();
                } else {
                    showAlert('创建失败', data.msg);
                }
            })
            .catch(e => showAlert('出错', e.message));
        });
    }

    function createNewFolder() {
        const dir = getCurrentPath();
        showPrompt('新建文件夹', '请输入文件夹名称:', '新文件夹').then(name => {
            if (name === null || name.trim() === '') return;
            const formData = new FormData();
            formData.append('dir', dir);
            formData.append('name', name.trim());
            fetch('?action=create_folder', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(data => {
                if (data.code === 0) {
                    showToast('文件夹创建成功', 'success');
                    loadDirectory(dir);
                    refreshSidebar();
                } else {
                    showAlert('创建失败', data.msg);
                }
            })
            .catch(e => showAlert('出错', e.message));
        });
    }

    // ========== 预览 ==========
    function previewFile(path) {
        const ext = getFileExt(path);
        const imageExts = ['jpg','jpeg','png','gif','bmp','webp','svg'];
        if (imageExts.includes(ext)) {
            const win = window.open('?preview=1&path=' + encodeURIComponent(path), '_blank');
            if (!win) showAlert('提示', '请允许弹窗窗口以预览图片');
            return;
        }
        if (isEditableFile(path)) {
            openEditor(path);
            return;
        }
        showAlert('预览', '此文件类型不支持在线预览，将下载');
        window.location.href = '?download=1&path=' + encodeURIComponent(path);
    }

    // ========== 复制/剪切/粘贴/重命名 ==========
    function copyItem(path) {
        clipboard.paths = [path];
        clipboard.action = 'copy';
        showToast('已复制: ' + path.split('/').pop(), 'info');
    }

    function cutItem(path) {
        clipboard.paths = [path];
        clipboard.action = 'cut';
        showToast('已剪切: ' + path.split('/').pop(), 'info');
    }

    function pasteItem(targetDir) {
        if (!clipboard.paths || clipboard.paths.length === 0) {
            showAlert('粘贴', '剪贴板为空，请先复制或剪切');
            return;
        }
        const source = clipboard.paths[0];
        const action = clipboard.action;
        const fileName = source.split('/').pop();
        const confirmMsg = (action === 'copy' ? '复制' : '移动') + ' "' + fileName + '" 到当前目录？';
        showConfirm('确认' + (action === 'copy' ? '复制' : '移动'), confirmMsg).then(confirmed => {
            if (!confirmed) return;
            const formData = new FormData();
            formData.append('source', source);
            formData.append('target_dir', targetDir);
            const url = '?action=' + (action === 'copy' ? 'copy' : 'move');
            fetch(url, {
                method: 'POST',
                body: formData,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(data => {
                if (data.code === 0) {
                    showToast((action === 'copy' ? '复制' : '移动') + '成功', 'success');
                    if (action === 'cut') {
                        clipboard.paths = [];
                        clipboard.action = null;
                    }
                    loadDirectory(targetDir);
                } else {
                    showAlert('操作失败', data.msg);
                }
            })
            .catch(e => showAlert('出错', e.message));
        });
    }

    function renameItem(path) {
        const oldName = path.split('/').pop();
        showPrompt('重命名', '请输入新名称:', oldName).then(newName => {
            if (newName === null || newName.trim() === '' || newName === oldName) return;
            const formData = new FormData();
            formData.append('path', path);
            formData.append('new_name', newName.trim());
            fetch('?action=rename', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(data => {
                if (data.code === 0) {
                    showToast('重命名成功', 'success');
                    loadDirectory(getCurrentPath());
                    refreshSidebar();
                } else {
                    showAlert('重命名失败', data.msg);
                }
            })
            .catch(e => showAlert('出错', e.message));
        });
    }

    function openInNewWindow(path) {
        const url = window.location.origin + window.location.pathname + '?path=' + encodeURIComponent(path);
        window.open(url, '_blank');
    }

    // ============================================================
    // ========== 右键菜单 ==========
    // ============================================================

    function showContextMenu(e, targetPath, targetType) {
        if (!targetPath) return;
        const contextMenu = document.getElementById('contextMenu');
        document.getElementById('menuPreview').style.display = (targetType === 'dir') ? 'none' : 'flex';
        document.querySelectorAll('#contextMenu .menu-item').forEach(el => el.style.display = 'flex');

        let menuWidth = contextMenu.offsetWidth || 180;
        let menuHeight = contextMenu.offsetHeight || 300;
        let x = e.clientX || e.pageX || 0;
        let y = e.clientY || e.pageY || 0;
        if (x + menuWidth > window.innerWidth) x = window.innerWidth - menuWidth - 10;
        if (y + menuHeight > window.innerHeight) y = window.innerHeight - menuHeight - 10;
        contextMenu.style.left = Math.max(0, x) + 'px';
        contextMenu.style.top = Math.max(0, y) + 'px';
        contextMenu.classList.add('show');

        currentTargetPath = targetPath;
        currentTargetType = targetType;
    }

    let currentTargetPath = null;
    let currentTargetType = null;

    document.addEventListener('contextmenu', function(e) {
        const explorer = e.target.closest('.file-explorer');
        const sidebarTree = e.target.closest('.sidebar-tree');
        if (!explorer && !sidebarTree) {
            const contextMenu = document.getElementById('contextMenu');
            contextMenu.classList.remove('show');
            return;
        }

        let targetPath = null;
        let targetType = null;

        const item = e.target.closest('.file-item');
        if (item) {
            targetPath = decodeURIComponent(item.dataset.path);
            targetType = item.dataset.type || 'file';
        } else {
            const treeNode = e.target.closest('.tree-node');
            if (treeNode) {
                targetPath = decodeURIComponent(treeNode.dataset.path);
                targetType = treeNode.dataset.type || 'file';
            } else {
                targetPath = getCurrentPath();
                targetType = 'dir';
            }
        }

        if (!targetPath) {
            const contextMenu = document.getElementById('contextMenu');
            contextMenu.classList.remove('show');
            return;
        }

        e.preventDefault();
        showContextMenu(e, targetPath, targetType);
    });

    document.addEventListener('click', function(e) {
        if (e.target.closest('#contextMenu')) return;
        const contextMenu = document.getElementById('contextMenu');
        contextMenu.classList.remove('show');
    });

    bindIfExists('menuNewWindow', 'click', function(e) {
        e.stopPropagation(); if (!currentTargetPath) return; openInNewWindow(currentTargetPath); document.getElementById('contextMenu').classList.remove('show');
    });
    bindIfExists('menuCopy', 'click', function(e) {
        e.stopPropagation(); if (!currentTargetPath) return; copyItem(currentTargetPath); document.getElementById('contextMenu').classList.remove('show');
    });
    bindIfExists('menuCut', 'click', function(e) {
        e.stopPropagation(); if (!currentTargetPath) return; cutItem(currentTargetPath); document.getElementById('contextMenu').classList.remove('show');
    });
    bindIfExists('menuPaste', 'click', function(e) {
        e.stopPropagation(); const targetDir = getCurrentPath(); pasteItem(targetDir); document.getElementById('contextMenu').classList.remove('show');
    });
    bindIfExists('menuRename', 'click', function(e) {
        e.stopPropagation(); if (!currentTargetPath) return; renameItem(currentTargetPath); document.getElementById('contextMenu').classList.remove('show');
    });
    bindIfExists('menuDownload', 'click', function(e) {
        e.stopPropagation(); if (!currentTargetPath) return; const url = '?download=1&path=' + encodeURIComponent(currentTargetPath); const a = document.createElement('a'); a.href = url; a.download = ''; document.body.appendChild(a); a.click(); document.body.removeChild(a); document.getElementById('contextMenu').classList.remove('show');
    });
    bindIfExists('menuPreview', 'click', function(e) {
        e.stopPropagation(); if (!currentTargetPath) return; previewFile(currentTargetPath); document.getElementById('contextMenu').classList.remove('show');
    });
    bindIfExists('menuDelete', 'click', function(e) {
        e.stopPropagation(); if (!currentTargetPath) return; const fileName = currentTargetPath.split('/').pop(); showConfirm('确认删除', '确定要删除文件 "' + fileName + '" 吗？').then(confirmed => {
            if (!confirmed) return; fetch('?delete=1&path=' + encodeURIComponent(currentTargetPath), { credentials: 'include' }).then(r => r.json()).then(data => {
                if (data.code === 0) { showToast('删除成功', 'success'); loadDirectory(getCurrentPath()); } else { showAlert('删除失败', data.msg); }
            }).catch(e => showAlert('出错', e.message));
        }); document.getElementById('contextMenu').classList.remove('show');
    });
    bindIfExists('menuNewFile', 'click', function(e) {
        e.stopPropagation(); document.getElementById('contextMenu').classList.remove('show'); createNewFile();
    });
    bindIfExists('menuNewFolder', 'click', function(e) {
        e.stopPropagation(); document.getElementById('contextMenu').classList.remove('show'); createNewFolder();
    });

    // ========== 侧边栏折叠 ==========
    const sidebarCollapseBtn = document.getElementById('sidebarCollapseBtn');
    const editorSidebar = document.getElementById('editorSidebar');
    if (sidebarCollapseBtn && editorSidebar) {
        sidebarCollapseBtn.addEventListener('click', function() {
            const isCollapsed = editorSidebar.classList.toggle('collapsed');
            this.textContent = isCollapsed ? '▶' : '◀';
        });
    }

    // ========== 长按支持（触屏设备） ==========
    let longPressTimer = null;
    let longPressTarget = null;
    let longPressStartX = 0, longPressStartY = 0;

    document.addEventListener('touchstart', function(e) {
        const target = e.target.closest('.file-item') || e.target.closest('.tree-node') || e.target.closest('.tab');
        if (!target) return;
        const touch = e.touches[0];
        longPressStartX = touch.clientX;
        longPressStartY = touch.clientY;
        longPressTarget = target;
        longPressTimer = setTimeout(() => {
            const path = target.dataset.path ? decodeURIComponent(target.dataset.path) : null;
            if (target.closest('.tab')) {
                const event = new CustomEvent('tab-contextmenu', { detail: { path: path } });
                document.dispatchEvent(event);
                if (navigator.vibrate) navigator.vibrate(10);
            } else {
                const type = target.dataset.type || 'file';
                const fakeEvent = { clientX: longPressStartX, clientY: longPressStartY, target: target };
                showContextMenu(fakeEvent, path, type);
                if (navigator.vibrate) navigator.vibrate(10);
            }
            e.preventDefault();
        }, 600);
    }, { passive: false });

    document.addEventListener('touchmove', function(e) {
        clearTimeout(longPressTimer);
        longPressTimer = null;
        longPressTarget = null;
    });
    document.addEventListener('touchend', function(e) {
        clearTimeout(longPressTimer);
        longPressTimer = null;
        longPressTarget = null;
    });
    document.addEventListener('touchcancel', function(e) {
        clearTimeout(longPressTimer);
        longPressTimer = null;
        longPressTarget = null;
    });

    // ========== 事件绑定 ==========
    document.addEventListener('click', function(e) {
        const link = e.target.closest('.file-item, .breadcrumb-nav a');
        if (!link) return;
        if (link.classList.contains('btn-tree')) return;
        e.preventDefault();

        if (link.classList.contains('file-item')) {
            const type = link.dataset.type;
            if (type === 'dir') {
                loadDirectory(decodeURIComponent(link.dataset.path));
                return;
            } else {
                const path = decodeURIComponent(link.dataset.path);
                if (isEditableFile(path)) {
                    openEditor(path);
                } else {
                    window.location.href = '?download=1&path=' + encodeURIComponent(path);
                }
                return;
            }
        }
        if (link.closest('.breadcrumb-nav')) {
            if (link.dataset.path) {
                loadDirectory(decodeURIComponent(link.dataset.path));
            }
        }
    });

    bindIfExists('editorSaveBtn', 'click', saveFile);
    bindIfExists('editorSaveAllBtn', 'click', saveAllFiles);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('editorOverlay').classList.contains('active') && !document.getElementById('editorOverlay').classList.contains('minimized')) {
            const hasOpenPanel = ['searchPanel', 'replacePanel', 'gotoPanel'].some(function(id) {
                const panel = document.getElementById(id);
                return panel && panel.classList.contains('active');
            });
            if (hasOpenPanel) {
                hideEditorPanels();
                return;
            }
            closeEditor();
        }
    });

    window.addEventListener('popstate', function(event) {
        const params = new URLSearchParams(window.location.search);
        const path = params.get('path') || '<?php echo $root_path; ?>';
        loadDirectory(path);
    });

    // ========== 搜索面板 ==========
    const searchPanel = document.getElementById('searchPanel');
    const searchInput = document.getElementById('searchInput');
    const searchToggleBtn = document.getElementById('searchToggleBtn');
    const closeSearchBtn = document.getElementById('closeSearchBtn');
    const replacePanel = document.getElementById('replacePanel');
    const replaceSearchInput = document.getElementById('replaceSearchInput');
    const replaceInput = document.getElementById('replaceInput');
    const replaceToggleBtn = document.getElementById('replaceToggleBtn');
    const closeReplaceBtn = document.getElementById('closeReplaceBtn');
    const gotoPanel = document.getElementById('gotoPanel');
    const gotoLineInput = document.getElementById('gotoLineInput');
    const gotoLineToggleBtn = document.getElementById('gotoLineToggleBtn');
    const gotoLineConfirmBtn = document.getElementById('gotoLineConfirmBtn');
    const closeGotoBtn = document.getElementById('closeGotoBtn');
    const editorCloseBtn = document.getElementById('editorCloseBtn');
    const toggleSidebarBtn = document.getElementById('toggleSidebarBtn');

    function hideEditorPanels() {
        [searchPanel, replacePanel, gotoPanel].forEach(function(panel) {
            if (panel) panel.classList.remove('active');
        });
        clearSearchHighlights();
        setMatchCount('matchCount', 0);
        setMatchCount('replaceMatchCount', 0);
    }

    function toggleEditorPanel(panel, onOpen) {
        if (!panel) return;
        const shouldOpen = !panel.classList.contains('active');
        hideEditorPanels();
        if (!shouldOpen) return;
        panel.classList.add('active');
        if (typeof onOpen === 'function') {
            onOpen();
        }
    }

    bindIfExists(searchToggleBtn, 'click', function() {
        toggleEditorPanel(searchPanel, function() {
            const selectedText = editor ? editor.getSelection() : '';
            if (selectedText.trim()) {
                searchInput.value = selectedText;
            }
            searchInput.focus();
            searchInput.select();
            updateSearchCount(searchInput.value);
        });
    });
    bindIfExists(closeSearchBtn, 'click', function() {
        hideEditorPanels();
    });
    let searchDebounceTimer;
    bindIfExists(searchInput, 'input', function() {
        clearTimeout(searchDebounceTimer);
        const query = this.value;
        searchDebounceTimer = setTimeout(() => {
            updateSearchCount(query);
        }, 300);
    });
    bindIfExists(searchInput, 'keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            jumpToNextMatch();
        }
    });
    bindIfExists(replaceToggleBtn, 'click', function() {
        toggleEditorPanel(replacePanel, function() {
            const selectedText = editor ? editor.getSelection() : '';
            if (selectedText.trim()) {
                replaceSearchInput.value = selectedText;
            } else if (searchInput && searchInput.value.trim()) {
                replaceSearchInput.value = searchInput.value;
            }
            replaceSearchInput.focus();
            replaceSearchInput.select();
            updateSearchCount(replaceSearchInput.value, 'replaceMatchCount');
        });
    });
    bindIfExists(closeReplaceBtn, 'click', function() {
        hideEditorPanels();
    });
    let replaceDebounceTimer;
    bindIfExists(replaceSearchInput, 'input', function() {
        clearTimeout(replaceDebounceTimer);
        const query = this.value;
        replaceDebounceTimer = setTimeout(function() {
            updateSearchCount(query, 'replaceMatchCount');
        }, 300);
    });
    function handleReplaceAction() {
        replaceNextMatch(replaceSearchInput ? replaceSearchInput.value : '', replaceInput ? replaceInput.value : '');
    }
    bindIfExists(replaceSearchInput, 'keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleReplaceAction();
        }
    });
    bindIfExists(replaceInput, 'keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleReplaceAction();
        }
    });
    bindIfExists('replaceOneBtn', 'click', function() {
        handleReplaceAction();
    });
    bindIfExists('replaceAllBtn', 'click', function() {
        replaceAllMatches(replaceSearchInput ? replaceSearchInput.value : '', replaceInput ? replaceInput.value : '');
    });
    bindIfExists(gotoLineToggleBtn, 'click', function() {
        toggleEditorPanel(gotoPanel, function() {
            const currentLine = editor ? (editor.getCursor().line + 1) : 1;
            gotoLineInput.value = currentLine;
            gotoLineInput.focus();
            gotoLineInput.select();
        });
    });
    bindIfExists(closeGotoBtn, 'click', function() {
        hideEditorPanels();
    });
    bindIfExists(gotoLineInput, 'keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            gotoLineNumber(this.value);
            hideEditorPanels();
        }
    });
    bindIfExists(gotoLineConfirmBtn, 'click', function() {
        gotoLineNumber(gotoLineInput ? gotoLineInput.value : '');
        hideEditorPanels();
    });
    bindIfExists(editorCloseBtn, 'click', closeEditor);

    bindIfExists(toggleSidebarBtn, 'click', function() {
        const sidebar = document.getElementById('editorSidebar');
        if (!sidebar) return;
        sidebar.classList.toggle('show');
    });

    bindIfExists('btnUpLevel', 'click', goUpLevel);
    bindIfExists('btnRefreshSidebar', 'click', function() {
        refreshSidebar();
    });
    bindIfExists('btnNewFolderSidebar', 'click', function() {
        if (document.getElementById('editorOverlay').classList.contains('active')) {
            createNewFolder();
        }
    });
    bindIfExists('btnNewFileSidebar', 'click', function() {
        if (document.getElementById('editorOverlay').classList.contains('active')) {
            createNewFile();
        }
    });
    bindIfExists('editorMinimizeBtn', 'click', function(e) {
        e.stopPropagation();
        minimizeEditor();
    });
    bindIfExists('editorMinibar', 'click', function(e) {
        restoreEditor();
    });

    // ========== 启动 ==========
    loadSidebarTree(getCurrentPath());

    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'v') {
            const active = document.activeElement;
            if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT')) {
                return;
            }
            if (clipboard.paths && clipboard.paths.length > 0) {
                e.preventDefault();
                pasteItem(getCurrentPath());
            }
        }
    });

    // AI 扩展初始化
    if (window.AI_EXTENSION_LOADED) {
        if (document.getElementById('editorOverlay').classList.contains('active')) {
            document.getElementById('toggleAISidebar').style.display = 'inline-block';
        }
    }
</script>
</body>
</html>