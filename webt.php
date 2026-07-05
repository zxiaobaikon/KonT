<?php
/**
 * =====================================================
 * 系统监控与文件管理面板（开源版）
 * 功能：文件浏览、上传、下载、编辑、删除、重命名、复制/移动、
 *       新建文件/文件夹、预览、全局搜索、系统负载/CPU/内存/磁盘监控
 * 配置：请在下方设置你的网站根目录路径
 * 安全提示：本工具无任何用户认证，请仅在安全环境或本地网络使用
 * =====================================================
 */

// ---------- 配置区 ----------
// 设置你的网站根目录（所有文件操作将限制在此目录内）
define('ROOT_PATH', '/var/www/html');  // 请修改为你的实际路径
// -----------------------------

// 关闭错误显示（生产环境建议开启日志）
error_reporting(0);

// ========== 图标映射函数 ==========
function getFileIcon($filename, $is_dir = false) {
    if ($is_dir) return '📁';
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $map = [
        'php' => '📘', 'html' => '📦', 'htm' => '📦', 'css' => '🎨',
        'js' => '📜', 'json' => '📋', 'md' => '📓', 'py' => '🐍',
        'sh' => '⚙️', 'conf' => '⚙️', 'ini' => '⚙️', 'log' => '📋',
        'txt' => '📝', 'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️',
        'gif' => '🖼️', 'bmp' => '🖼️', 'webp' => '🖼️', 'svg' => '🖼️',
        'zip' => '🟩', 'rar' => '🟩', '7z' => '🟩', 'tar' => '🟩',
        'gz' => '🟩', 'mp3' => '🎵', 'wav' => '🎵', 'flac' => '🎵',
        'mp4' => '🎬', 'avi' => '🎬', 'mkv' => '🎬', 'exe' => '⚙️',
        'msi' => '⚙️', 'pdf' => '📕', 'xls' => '📊', 'xlsx' => '📊'
    ];
    return isset($map[$ext]) ? $map[$ext] : '📄';
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

    $real_root = realpath(ROOT_PATH);
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
        $zipData = create_zip_from_dir($real_path, ROOT_PATH);
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
    $target_dir = isset($_POST['target_path']) ? $_POST['target_path'] : ROOT_PATH;
    $real_root = realpath(ROOT_PATH);
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
    $real_root = realpath(ROOT_PATH);
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

// ---------- 获取文件内容 ----------
if (isset($_GET['getcontent']) && $_GET['getcontent'] == '1') {
    header('Content-Type: application/json');
    $path = isset($_GET['path']) ? $_GET['path'] : '';
    if (empty($path)) {
        echo json_encode(['code' => -1, 'msg' => '路径为空']);
        exit;
    }
    $real_root = realpath(ROOT_PATH);
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

// ---------- 保存文件内容 ----------
if (isset($_GET['save']) && $_GET['save'] == '1') {
    header('Content-Type: application/json');
    $path = isset($_POST['path']) ? $_POST['path'] : '';
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    if (empty($path)) {
        echo json_encode(['code' => -1, 'msg' => '路径为空']);
        exit;
    }
    $real_root = realpath(ROOT_PATH);
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

// ---------- 新建文件 ----------
if (isset($_GET['action']) && $_GET['action'] == 'create_file') {
    header('Content-Type: application/json');
    $dir = isset($_POST['dir']) ? $_POST['dir'] : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    if (empty($dir) || empty($name)) {
        echo json_encode(['code' => -1, 'msg' => '目录或文件名为空']);
        exit;
    }
    $real_root = realpath(ROOT_PATH);
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

// ---------- 新建文件夹 ----------
if (isset($_GET['action']) && $_GET['action'] == 'create_folder') {
    header('Content-Type: application/json');
    $dir = isset($_POST['dir']) ? $_POST['dir'] : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    if (empty($dir) || empty($name)) {
        echo json_encode(['code' => -1, 'msg' => '目录或文件夹名为空']);
        exit;
    }
    $real_root = realpath(ROOT_PATH);
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

// ---------- 预览文件 ----------
if (isset($_GET['preview']) && $_GET['preview'] == '1') {
    $path = isset($_GET['path']) ? $_GET['path'] : '';
    if (empty($path)) {
        http_response_code(400);
        exit('路径为空');
    }
    $real_root = realpath(ROOT_PATH);
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

// ---------- 复制文件/文件夹 ----------
if (isset($_GET['action']) && $_GET['action'] == 'copy') {
    header('Content-Type: application/json');
    set_time_limit(0);
    $source = isset($_POST['source']) ? $_POST['source'] : '';
    $target_dir = isset($_POST['target_dir']) ? $_POST['target_dir'] : '';
    if (empty($source) || empty($target_dir)) {
        echo json_encode(['code' => -1, 'msg' => '源路径或目标目录为空']);
        exit;
    }
    $real_root = realpath(ROOT_PATH);
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

// ---------- 移动文件/文件夹 ----------
if (isset($_GET['action']) && $_GET['action'] == 'move') {
    header('Content-Type: application/json');
    set_time_limit(0);
    $source = isset($_POST['source']) ? $_POST['source'] : '';
    $target_dir = isset($_POST['target_dir']) ? $_POST['target_dir'] : '';
    if (empty($source) || empty($target_dir)) {
        echo json_encode(['code' => -1, 'msg' => '源路径或目标目录为空']);
        exit;
    }
    $real_root = realpath(ROOT_PATH);
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

    function copy_recursive_move($src, $dst) {
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
                copy_recursive_move($src_file, $dst_file);
            } else {
                copy($src_file, $dst_file);
            }
        }
        closedir($dir);
        return true;
    }

    // 先复制，再删除源
    if (copy_recursive_move($real_source, $dest_path)) {
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

// ---------- 重命名 ----------
if (isset($_GET['action']) && $_GET['action'] == 'rename') {
    header('Content-Type: application/json');
    $path = isset($_POST['path']) ? $_POST['path'] : '';
    $new_name = isset($_POST['new_name']) ? trim($_POST['new_name']) : '';
    if (empty($path) || empty($new_name)) {
        echo json_encode(['code' => -1, 'msg' => '路径或新名称为空']);
        exit;
    }
    $real_root = realpath(ROOT_PATH);
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

// ---------- 系统监控函数 ----------
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

// ---------- AJAX 监控数据 ----------
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
    $path = isset($_GET['path']) ? $_GET['path'] : ROOT_PATH;
    $real_root = realpath(ROOT_PATH);
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

// ---------- 全局搜索 ----------
if (isset($_GET['search']) && $_GET['search'] == '1') {
    header('Content-Type: application/json');
    $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
    if (strlen($keyword) < 2) {
        echo json_encode(['code' => 0, 'items' => []]);
        exit;
    }
    $real_root = realpath(ROOT_PATH);
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
$current_path = isset($_GET['path']) ? $_GET['path'] : ROOT_PATH;
$real_root = realpath(ROOT_PATH);
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
    <title>系统监控与文件管理</title>
    <!-- CodeMirror 依赖 -->
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
.main-wrapper { max-width: 1400px; width: 100%; }
::-webkit-scrollbar { width: 8px; height: 8px; }
::-webkit-scrollbar-track { background: #1c1c1e; }
::-webkit-scrollbar-thumb { background: #3a3a3c; border-radius: 0; }
.dash-card, .file-explorer, .file-item, .breadcrumb-nav a { border-radius: 0 !important; }

/* -------- 仪表盘模块（可折叠） -------- */
.dash-wrapper {
    position: relative;
    margin-bottom: 28px;
}
.dash-toggle-btn-expand {
    position: absolute;
    right: 0;
    top: -36px;
    background: transparent;
    border: none;
    color: #8e8e93;
    font-size: 13px;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: all 0.2s;
}
.dash-toggle-btn-expand:hover { background: rgba(255,255,255,0.08); color: #fff; }

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
}
.dash-card:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(0,0,0,0.6); }
.dash-info h4 { color: #8e8e93; font-size: 13px; font-weight: 500; margin-bottom: 8px; }
.dash-info .value { font-size: 26px; font-weight: 700; color: #ffffff; display: flex; align-items: baseline; }
.dash-info .unit { font-size: 13px; color: #8e8e93; margin-left: 2px; }
.dash-info .sub { font-size: 12px; color: #636366; margin-top: 6px; }
.circle-chart { position: relative; width: 72px; height: 72px; flex-shrink: 0; }
.circle-chart svg { transform: rotate(-90deg); width: 100%; height: 100%; }
.circle-chart .bg { fill: none; stroke: rgba(255, 255, 255, 0.05); stroke-width: 4.5; }
.circle-chart .progress { fill: none; stroke-width: 4.5; stroke-linecap: round; transition: stroke-dashoffset 0.6s cubic-bezier(0.34, 1.56, 0.64, 1); }
.circle-text { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 600; color: #f5f5f7; }

/* -------- 折叠后的长条样式 -------- */
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

.file-explorer {
    background: rgba(28, 28, 30, 0.7);
    backdrop-filter: blur(16px);
    padding: 24px;
    border: 1px solid rgba(255, 255, 255, 0.06);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
    margin-top: 8px;
    position: relative;
    transition: border-color 0.2s ease, background 0.2s ease;
}
.file-explorer.drag-over {
    border-color: #0a84ff;
    background: rgba(10, 132, 255, 0.08);
}
.breadcrumb-nav { display: flex; gap: 8px; font-size: 14px; padding-bottom: 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); margin-bottom: 18px; flex-wrap: wrap; align-items: center; }
.breadcrumb-nav a { color: #0a84ff; text-decoration: none; padding: 4px 8px; border: 1px solid transparent; transition: all 0.15s; cursor: pointer; }
.breadcrumb-nav a:hover { color: #2f97ff; background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.08); }
.breadcrumb-nav span { color: #636366; }
.breadcrumb-nav .count { margin-left: auto; color: #8e8e93; font-size: 13px; }

.file-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 10px;
    transition: opacity 0.2s ease, transform 0.2s ease;
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

.file-item { display: flex; align-items: center; padding: 14px 18px; background: rgba(255, 255, 255, 0.03); text-decoration: none; color: #f5f5f7; transition: all 0.15s ease; border: 1px solid rgba(255, 255, 255, 0.02); cursor: pointer; }
.file-item:hover { background: rgba(255, 255, 255, 0.06); border-color: rgba(255, 255, 255, 0.12); transform: translateX(2px); }
.file-icon { font-size: 24px; margin-right: 14px; width: 32px; text-align: center; }
.file-info { display: flex; flex-direction: column; overflow: hidden; }
.file-name { font-size: 14px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.file-sub { font-size: 12px; color: #8e8e93; margin-top: 4px; }

/* 右键菜单 */
#contextMenu {
    display: none;
    position: fixed;
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
}
#contextMenu .menu-item { padding: 10px 20px; color: #f5f5f7; font-size: 14px; cursor: pointer; transition: background 0.15s; display: flex; align-items: center; gap: 10px; }
#contextMenu .menu-item:hover { background: rgba(255, 255, 255, 0.08); }
#contextMenu .menu-separator { height: 1px; background: rgba(255,255,255,0.1); margin: 4px 0; }

/* 自定义模态框 */
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

/* Toast 通知 */
#toastContainer {
    position: fixed;
    top: 20px;
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

/* 全屏编辑器 */
.editor-overlay {
    display: flex;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: #1e1e1e;
    z-index: 10000;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 0.25s ease, visibility 0.25s ease;
    flex-direction: row;
}
.editor-overlay.active {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}
.editor-sidebar {
    width: 280px;
    background: #252526;
    display: flex;
    flex-direction: column;
    border-right: 1px solid #3e3e42;
    flex-shrink: 0;
    transition: transform 0.3s ease, opacity 0.3s ease;
}
.editor-sidebar.hidden {
    transform: translateX(-100%);
    opacity: 0;
}
.sidebar-header {
    padding: 10px 12px;
    font-size: 12px;
    color: #cccccc;
    background: #252526;
    border-bottom: 1px solid #3e3e42;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.sidebar-header .toggle-sidebar {
    background: none;
    border: none;
    color: #ccc;
    font-size: 20px;
    cursor: pointer;
    padding: 0 6px;
}
.sidebar-search {
    padding: 8px 10px;
    display: flex;
    background: #1e1e1e;
    border-bottom: 1px solid #333;
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
}
.sidebar-search input:focus { background: #4a4a4c; }
.sidebar-tree {
    flex: 1;
    overflow-y: auto;
    padding: 8px 0;
}
.tree-item {
    display: flex;
    align-items: center;
    padding: 6px 14px;
    color: #cccccc;
    font-size: 13px;
    cursor: pointer;
    text-decoration: none;
    transition: background 0.15s;
}
.tree-item:hover { background: #2a2d2e; }
.tree-item.active { background: #37373d; color: #fff; }
.tree-icon { margin-right: 8px; font-size: 16px; }
.tree-folder { padding-left: 4px; }
.tree-file { padding-left: 24px; }
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
.toolbar-left button.save-all-btn { color: #6a6a6a; cursor: default; }
.toolbar-left button.save-all-btn:hover { background: transparent; }
.editor-tabs {
    height: 35px;
    background: #252526;
    display: flex;
    align-items: flex-end;
    border-bottom: 1px solid #1e1e1e;
    flex-shrink: 0;
    overflow-x: auto;
    gap: 1px;
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
.editor-body { flex: 1; overflow: hidden; background: #1e1e1e; }
.editor-body .CodeMirror { height: 100% !important; font-size: 14px; line-height: 1.6; background: #1e1e1e; }
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
.editor-statusbar .status-left { display: flex; align-items: center; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.editor-statusbar .status-right { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }

.search-panel {
    display: none;
    position: absolute;
    top: 50px;
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
.search-panel.active { display: flex; }
.search-panel .search-row {
    display: flex;
    align-items: center;
    gap: 8px;
}
.search-panel input {
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
.search-panel input:focus { border-color: #0a84ff; }
.search-panel .search-info {
    font-size: 12px;
    color: #858585;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.search-panel .search-info .match-count { color: #0a84ff; }
.search-panel .close-search-btn {
    background: none;
    border: none;
    color: #ccc;
    font-size: 18px;
    cursor: pointer;
    padding: 0 4px;
    line-height: 1;
}
.search-panel .close-search-btn:hover { color: #fff; }

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

/* 移动端适配 */
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
        width: 100%;
        height: 40vh;
        border-right: none;
        border-bottom: 1px solid #3e3e42;
        flex-shrink: 0;
        transform: none;
        opacity: 1;
        display: none;
    }
    .editor-sidebar.show { display: flex; }
    .editor-sidebar.hidden { display: none; }
    .editor-main { height: 60vh; }
    .editor-toolbar {
        height: auto;
        padding: 6px 8px;
        flex-wrap: wrap;
        gap: 4px;
    }
    .editor-toolbar button { font-size: 12px; padding: 4px 8px; min-height: 28px; }
    .toolbar-left, .toolbar-right { gap: 4px; }
    .editor-tabs .tab { font-size: 12px; padding: 0 10px; min-width: 60px; max-width: 140px; }
    .editor-body .CodeMirror { font-size: 13px; }
    .editor-statusbar { font-size: 11px; padding: 0 8px; }
    .search-panel { min-width: 220px; right: 6px; top: 48px; padding: 8px 12px; }
    .modal-box { padding: 16px; max-width: 95%; }
    .upload-modal-content { max-width: 95%; padding: 16px; }
    #contextMenu { min-width: 140px; }
    #contextMenu .menu-item { font-size: 13px; padding: 8px 14px; }
    .sidebar-header .toggle-sidebar { display: inline-block; }
    .dash-collapsed { height: 28px; }
    .dash-bar-container { height: 10px; }
    .dash-bar-seg { font-size: 8px; }
    .dash-inner-btn { font-size: 10px; }
}
@media (max-width: 400px) {
    .dashboard-row { grid-template-columns: 1fr; }
    .circle-chart { width: 60px; height: 60px; }
}
</style>
</head>
<body>
<div class="main-wrapper">
    <!-- 仪表盘（可折叠） -->
    <div class="dash-wrapper">
        <button class="dash-toggle-btn-expand" id="dashToggleBtnExp">⬇ 收起</button>

        <div class="dashboard-row" id="dashRow">
            <div class="dash-card">
                <div class="dash-info"><h4>系统负载</h4><div class="value" id="loadVal">0.00</div><div class="sub">1分钟 / 5分钟 / 15分钟</div></div>
                <div class="circle-chart"><svg viewBox="0 0 36 36"><circle cx="18" cy="18" r="15.9155" class="bg" /><circle cx="18" cy="18" r="15.9155" class="progress" id="loadCircle" stroke="#4caf50" stroke-dasharray="100" stroke-dashoffset="100" /></svg><div class="circle-text" id="loadPercent">0%</div></div>
            </div>
            <div class="dash-card">
                <div class="dash-info"><h4>CPU 使用率</h4><div class="value" id="cpuVal">0<span class="unit">%</span></div><div class="sub">1 核心 Intel(R) Xeon(R) Gold 6152 CPU @ 2.10GHz * 1 </div></div>
                <div class="circle-chart"><svg viewBox="0 0 36 36"><circle cx="18" cy="18" r="15.9155" class="bg" /><circle cx="18" cy="18" r="15.9155" class="progress" id="cpuCircle" stroke="#2196f3" stroke-dasharray="100" stroke-dashoffset="100" /></svg><div class="circle-text" id="cpuPercent">0%</div></div>
            </div>
            <div class="dash-card">
                <div class="dash-info"><h4>内存使用</h4><div class="value"><span id="memUsed">0</span><span class="unit"> MB</span></div><div class="sub">总计: <span id="memTotal">0</span> MB</div></div>
                <div class="circle-chart"><svg viewBox="0 0 36 36"><circle cx="18" cy="18" r="15.9155" class="bg" /><circle cx="18" cy="18" r="15.9155" class="progress" id="memCircle" stroke="#e91e63" stroke-dasharray="100" stroke-dashoffset="100" /></svg><div class="circle-text" id="memPercent">0%</div></div>
            </div>
            <div class="dash-card">
                <div class="dash-info"><h4>磁盘使用</h4><div class="value"><span id="diskUsed">0</span><span class="unit"> GB</span></div><div class="sub">总计: <span id="diskTotal">0</span> GB</div></div>
                <div class="circle-chart"><svg viewBox="0 0 36 36"><circle cx="18" cy="18" r="15.9155" class="bg" /><circle cx="18" cy="18" r="15.9155" class="progress" id="diskCircle" stroke="#ff9800" stroke-dasharray="100" stroke-dashoffset="100" /></svg><div class="circle-text" id="diskPercent">0%</div></div>
            </div>
        </div>

        <!-- 折叠后的极简长条 -->
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
        <div class="breadcrumb-nav">
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <?php if ($index > 0): ?><span>›</span><?php endif; ?>
                <a href="javascript:void(0)" data-path="<?php echo urlencode($crumb['path']); ?>"><?php echo htmlspecialchars($crumb['name']); ?></a>
            <?php endforeach; ?>
            <span class="count">共 <?php echo count($all_items); ?> 个项目</span>
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

<!-- 右键菜单 -->
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

<!-- 自定义模态框（通用） -->
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

<!-- Toast 容器 -->
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

<!-- 全屏编辑器 -->
<div class="editor-overlay" id="editorOverlay">
    <div class="editor-sidebar" id="editorSidebar">
        <div class="sidebar-header">
            <span>📂 目录</span>
            <button class="toggle-sidebar" id="toggleSidebarBtn" title="切换侧栏">☰</button>
        </div>
        <div class="sidebar-search">
            <input type="text" id="sidebarSearchInput" placeholder="搜索文件 (全局匹配)..." oninput="filterSidebarTree(this.value)">
        </div>
        <div class="sidebar-tree" id="sidebarTree"></div>
    </div>
    <div class="editor-main">
        <div class="editor-toolbar">
            <div class="toolbar-left">
                <button id="editorSaveBtn" class="save-btn">💾 保存</button>
                <button class="save-all-btn">全部保存</button>
            </div>
            <div class="toolbar-right">
                <button id="searchToggleBtn">🔍 搜索</button>
                <button onclick="editor.execCommand('replace')">🔄 替换</button>
                <button onclick="editor.execCommand('jumpToLine')">↩ 跳转行</button>
                <button id="editorCloseBtn" style="color:#f5f5f7;font-size:18px;line-height:1;" title="关闭编辑器">✕</button>
            </div>
        </div>
        <div class="editor-tabs" id="editorTabs"></div>
        <div class="editor-body" id="editorBody"></div>
        <div class="search-panel" id="searchPanel">
            <div class="search-row">
                <input type="text" id="searchInput" placeholder="搜索文件内容..." autofocus>
                <button class="close-search-btn" id="closeSearchBtn" title="关闭搜索">✕</button>
            </div>
            <div class="search-info">
                <span class="match-count" id="matchCount">0 个匹配</span>
                <span style="color:#858585;">回车跳转</span>
            </div>
        </div>
        <div class="editor-statusbar">
            <div class="status-left" id="statusFilePath">文件位置: 无</div>
            <div class="status-right">
                <span id="statusCursor">行 1, 列 1</span>
                <span id="statusLang">语言: -</span>
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

    // ========== 图标映射（JS） ==========
    function getFileIcon(filename, isDir) {
        if (isDir) return '📁';
        var ext = filename.split('.').pop().toLowerCase();
        var map = {
            'php': '📘', 'html': '📦', 'htm': '📦', 'css': '🎨',
            'js': '📜', 'json': '📋', 'md': '📓', 'py': '🐍',
            'sh': '⚙️', 'conf': '⚙️', 'ini': '⚙️', 'log': '📋',
            'txt': '📝', 'jpg': '🖼️', 'jpeg': '🖼️', 'png': '🖼️',
            'gif': '🖼️', 'bmp': '🖼️', 'webp': '🖼️', 'svg': '🖼️',
            'zip': '🟩', 'rar': '🟩', '7z': '🟩', 'tar': '🟩', 'gz': '🟩',
            'mp3': '🎵', 'wav': '🎵', 'flac': '🎵',
            'mp4': '🎬', 'avi': '🎬', 'mkv': '🎬',
            'exe': '⚙️', 'msi': '⚙️', 'pdf': '📕', 'xls': '📊', 'xlsx': '📊'
        };
        return map[ext] || '📄';
    }

    // ========== Toast 通知 ==========
    function showToast(msg, type = 'info', duration = 3000) {
        const container = document.getElementById('toastContainer');
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
        toast.querySelector('.toast-close').addEventListener('click', function() {
            clearTimeout(timer);
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        });
    }

    // ========== 自定义 Modal ==========
    function showModal(options) {
        return new Promise((resolve) => {
            const overlay = document.getElementById('customModal');
            const title = document.getElementById('modalTitle');
            const body = document.getElementById('modalBody');
            const actions = document.getElementById('modalActions');
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

    // ========== 圆环更新 ==========
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

    // ========== 折叠/展开逻辑 ==========
    const dashRow = document.getElementById('dashRow');
    const dashCollapsed = document.getElementById('dashCollapsed');
    const dashToggleBtnExp = document.getElementById('dashToggleBtnExp');
    const dashInnerToggleBtn = document.getElementById('dashInnerToggleBtn');

    function toggleDashboard() {
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
    dashToggleBtnExp.addEventListener('click', toggleDashboard);
    dashInnerToggleBtn.addEventListener('click', toggleDashboard);

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
            if (val < 5) {
                el.innerText = labels[idx] + ' ' + Math.round(val) + '%';
            } else {
                el.innerText = labels[idx] + ' ' + Math.round(val) + '%';
            }
        });
    }

    // ========== 监控更新 ==========
    function updateDashboard() {
        fetch('?ajax=1')
            .then(r => r.json())
            .then(data => {
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
            })
            .catch(e => console.error('监控更新失败:', e));
    }

    // ========== 目录加载 ==========
    function loadDirectory(path) {
        const grid = document.getElementById('fileGrid');
        grid.classList.add('loading');
        requestAnimationFrame(() => {
            fetch('?api=1&path=' + encodeURIComponent(path))
                .then(r => r.json())
                .then(data => {
                    const nav = document.querySelector('.breadcrumb-nav');
                    let html = '';
                    data.breadcrumbs.forEach((crumb, idx) => {
                        if (idx > 0) html += '<span>›</span>';
                        html += `<a href="javascript:void(0)" data-path="${encodeURIComponent(crumb.path)}">${escapeHtml(crumb.name)}</a>`;
                    });
                    html += `<span class="count">共 ${data.total} 个项目</span>`;
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
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getCurrentPath() {
        const params = new URLSearchParams(window.location.search);
        let path = params.get('path') || '<?php echo ROOT_PATH; ?>';
        return decodeURIComponent(path);
    }

    // ========== 编辑器核心 ==========
    let editor = null;
    let isEditorReady = false;
    let tabData = {};
    let tabOrder = [];
    let activeTabPath = null;

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

    function initEditor() {
        if (isEditorReady) return;
        const body = document.getElementById('editorBody');
        editor = CodeMirror(body, {
            value: '// 加载中...',
            mode: 'text',
            theme: 'dracula',
            lineNumbers: true,
            indentUnit: 4,
            tabSize: 4,
            lineWrapping: true,
            styleActiveLine: true,
            extraKeys: {
                "Ctrl-S": function() { saveFile(); },
                "Cmd-S": function() { saveFile(); }
            }
        });
        editor.on('cursorActivity', function() {
            const pos = editor.getCursor();
            document.getElementById('statusCursor').innerText = '行 ' + (pos.line + 1) + ', 列 ' + (pos.ch + 1);
        });
        isEditorReady = true;
    }

    function renderTabs() {
        const container = document.getElementById('editorTabs');
        let html = '';
        tabOrder.forEach(p => {
            const name = p.split('/').pop();
            const active = p === activeTabPath ? 'active' : '';
            const icon = getFileIcon(name, false);
            html += `<div class="tab ${active}" data-path="${encodeURIComponent(p)}">
                <span>${icon} ${escapeHtml(name)}</span>
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
        if (activeTabPath && tabData[activeTabPath]) {
            document.getElementById('statusLang').innerText = '语言: ' + tabData[activeTabPath].mode.toUpperCase();
        }
    }

    function updateStatusBar(path) {
        document.getElementById('statusFilePath').innerText = '文件位置: ' + path;
        if (tabData[path]) {
            document.getElementById('statusLang').innerText = '语言: ' + tabData[path].mode.toUpperCase();
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
        editor.setValue(data.content);
        editor.setOption('mode', data.mode);
        editor.refresh();
        editor.setCursor({line:0,ch:0});
        updateStatusBar(path);
        document.querySelectorAll('.sidebar-tree .tree-item').forEach(el => {
            el.classList.toggle('active', el.dataset.path && decodeURIComponent(el.dataset.path) === path);
        });
    }

    function closeTab(path) {
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
    }

    function openEditor(path) {
        initEditor();
        if (tabData[path]) {
            document.getElementById('editorOverlay').classList.add('active');
            if (window.innerWidth <= 600) {
                document.getElementById('editorSidebar').classList.add('show');
            }
            switchTab(path);
            return;
        }
        document.getElementById('editorOverlay').classList.add('active');
        if (window.innerWidth <= 600) {
            document.getElementById('editorSidebar').classList.add('show');
        }
        editor.setValue('// 正在加载...');
        editor.setOption('mode', 'text');
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
                tabData[path] = { content, mode, path };
                tabOrder.push(path);
                activeTabPath = path;
                renderTabs();
                editor.setValue(content);
                editor.setOption('mode', mode);
                editor.refresh();
                editor.setCursor({line:0,ch:0});
                updateStatusBar(path);
                const basePath = path.substring(0, path.lastIndexOf('/'));
                loadSidebarTree(basePath);
                document.querySelectorAll('.sidebar-tree .tree-item').forEach(el => {
                    el.classList.toggle('active', el.dataset.path && decodeURIComponent(el.dataset.path) === path);
                });
            })
            .catch(e => {
                showAlert('加载失败', e.message);
                closeEditor();
            });
    }

    function closeEditor() {
        document.getElementById('editorOverlay').classList.remove('active');
        document.getElementById('searchPanel').classList.remove('active');
        clearSearchHighlights();
        tabData = {};
        tabOrder = [];
        activeTabPath = null;
        renderTabs();
        document.getElementById('statusFilePath').innerText = '文件位置: 无';
        document.getElementById('statusLang').innerText = '语言: -';
    }

    function saveFile() {
        if (!activeTabPath) {
            showAlert('提示', '没有打开的文件');
            return;
        }
        const content = editor.getValue();
        tabData[activeTabPath].content = content;
        const formData = new FormData();
        formData.append('path', activeTabPath);
        formData.append('content', content);

        const statusBar = document.getElementById('statusFilePath');
        const originalText = statusBar.innerText;
        statusBar.innerText = '💾 保存中...';

        fetch('?save=1', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.code === 0) {
                statusBar.innerText = '✅ 保存成功 | ' + originalText;
                showToast('文件已保存', 'success');
            } else {
                statusBar.innerText = '❌ 保存失败: ' + data.msg;
                showAlert('保存失败', data.msg);
            }
        })
        .catch(e => {
            statusBar.innerText = '❌ 保存出错';
            showAlert('保存出错', e.message);
        });
    }

    // ========== 侧边栏树 ==========
    let currentSidebarPath = '';
    function loadSidebarTree(path) {
        currentSidebarPath = path;
        fetch('?api=1&path=' + encodeURIComponent(path))
            .then(r => r.json())
            .then(data => {
                const container = document.getElementById('sidebarTree');
                let html = '';
                const rootName = '<?php echo ROOT_PATH; ?>';
                html += `<div class="tree-item tree-folder active" data-path="${encodeURIComponent(data.currentPath)}" onclick="loadDirectory(this.dataset.path);loadSidebarTree(this.dataset.path);">📂 ${rootName}</div>`;

                data.items.forEach(item => {
                    const icon = item.icon || getFileIcon(item.name, item.type === 'dir');
                    const cls = item.type === 'dir' ? 'tree-folder' : 'tree-file';
                    const activeCls = (activeTabPath && activeTabPath === item.path) ? ' active' : '';
                    html += `<div class="tree-item ${cls}${activeCls}" data-path="${encodeURIComponent(item.path)}" data-type="${item.type}" onclick="handleSidebarClick('${encodeURIComponent(item.path)}', '${item.type}')">
                        <span class="tree-icon">${icon}</span> ${escapeHtml(item.name)}
                    </div>`;
                });
                container.innerHTML = html;
            });
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

    // ========== 编辑器搜索 ==========
    let searchMatches = [];
    let currentMatchIndex = -1;
    let searchMarkers = [];

    function clearSearchHighlights() {
        searchMarkers.forEach(marker => marker.clear());
        searchMarkers = [];
    }

    function updateSearchCount(query) {
        clearSearchHighlights();
        searchMatches = [];
        if (!query.trim() || !editor) {
            document.getElementById('matchCount').innerText = '0 个匹配';
            return;
        }
        const cursor = editor.getSearchCursor(query, {line:0, ch:0}, {caseFold: true});
        while (cursor.findNext()) {
            searchMatches.push(cursor.from());
        }
        searchMatches.forEach(pos => {
            const marker = editor.markText(pos, {line:pos.line, ch:pos.ch + query.length}, {className: 'cm-search-match'});
            searchMarkers.push(marker);
        });
        document.getElementById('matchCount').innerText = searchMatches.length + ' 个匹配';
        currentMatchIndex = -1;
    }

    function jumpToNextMatch() {
        if (searchMatches.length === 0) return;
        currentMatchIndex = (currentMatchIndex + 1) % searchMatches.length;
        const pos = searchMatches[currentMatchIndex];
        editor.setCursor(pos);
        editor.scrollIntoView(pos);
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
    fileExplorer.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        this.classList.add('drag-over');
    });
    fileExplorer.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
    });
    fileExplorer.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            uploadTargetPath = getCurrentPath();
            addFilesToUpload(files);
        }
    });
    const uploadModal = document.getElementById('uploadModal');
    uploadModal.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
    });
    uploadModal.addEventListener('drop', function(e) {
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

    // ========== 复制/剪切/粘贴/重命名/新窗口打开 ==========
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

    // ========== 右键菜单 ==========
    const contextMenu = document.getElementById('contextMenu');
    let currentTargetPath = null;
    let currentTargetType = null;

    document.addEventListener('contextmenu', function(e) {
        const item = e.target.closest('.file-item');
        const explorer = e.target.closest('.file-explorer');
        if (!explorer) { contextMenu.style.display = 'none'; return; }

        const showForItem = !!item;
        const targetPath = showForItem ? decodeURIComponent(item.dataset.path) : null;
        const targetType = showForItem ? (item.dataset.type || 'file') : null;

        document.getElementById('menuNewWindow').style.display = 'flex';
        document.getElementById('menuCopy').style.display = 'flex';
        document.getElementById('menuCut').style.display = 'flex';
        document.getElementById('menuPaste').style.display = 'flex';
        document.getElementById('menuRename').style.display = showForItem ? 'flex' : 'none';
        document.getElementById('menuDownload').style.display = showForItem ? 'flex' : 'none';
        document.getElementById('menuPreview').style.display = showForItem ? 'flex' : 'none';
        document.getElementById('menuDelete').style.display = showForItem ? 'flex' : 'none';
        document.getElementById('menuNewFile').style.display = 'flex';
        document.getElementById('menuNewFolder').style.display = 'flex';

        if (showForItem) {
            currentTargetPath = targetPath;
            currentTargetType = targetType;
        } else {
            currentTargetPath = null;
            currentTargetType = null;
        }

        e.preventDefault();
        contextMenu.style.display = 'block';

        let menuWidth = contextMenu.offsetWidth || 180;
        let menuHeight = contextMenu.offsetHeight || 300;
        let x = e.clientX;
        let y = e.clientY;
        if (x + menuWidth > window.innerWidth) x = window.innerWidth - menuWidth - 10;
        if (y + menuHeight > window.innerHeight) y = window.innerHeight - menuHeight - 10;
        contextMenu.style.left = Math.max(0, x) + 'px';
        contextMenu.style.top = Math.max(0, y) + 'px';
    });

    document.addEventListener('click', function(e) {
        if (!contextMenu.contains(e.target)) contextMenu.style.display = 'none';
    });

    document.getElementById('menuNewWindow').addEventListener('click', function(e) {
        e.stopPropagation(); if (!currentTargetPath) return; openInNewWindow(currentTargetPath); contextMenu.style.display = 'none';
    });
    document.getElementById('menuCopy').addEventListener('click', function(e) {
        e.stopPropagation(); if (!currentTargetPath) return; copyItem(currentTargetPath); contextMenu.style.display = 'none';
    });
    document.getElementById('menuCut').addEventListener('click', function(e) {
        e.stopPropagation(); if (!currentTargetPath) return; cutItem(currentTargetPath); contextMenu.style.display = 'none';
    });
    document.getElementById('menuPaste').addEventListener('click', function(e) {
        e.stopPropagation(); const targetDir = getCurrentPath(); pasteItem(targetDir); contextMenu.style.display = 'none';
    });
    document.getElementById('menuRename').addEventListener('click', function(e) {
        e.stopPropagation(); if (!currentTargetPath) return; renameItem(currentTargetPath); contextMenu.style.display = 'none';
    });
    document.getElementById('menuDownload').addEventListener('click', function(e) {
        e.stopPropagation(); if (!currentTargetPath) return; const url = '?download=1&path=' + encodeURIComponent(currentTargetPath); const a = document.createElement('a'); a.href = url; a.download = ''; document.body.appendChild(a); a.click(); document.body.removeChild(a); contextMenu.style.display = 'none';
    });
    document.getElementById('menuPreview').addEventListener('click', function(e) {
        e.stopPropagation(); if (!currentTargetPath) return; previewFile(currentTargetPath); contextMenu.style.display = 'none';
    });
    document.getElementById('menuDelete').addEventListener('click', function(e) {
        e.stopPropagation(); if (!currentTargetPath) return; const fileName = currentTargetPath.split('/').pop(); showConfirm('确认删除', '确定要删除文件 "' + fileName + '" 吗？').then(confirmed => {
            if (!confirmed) return; fetch('?delete=1&path=' + encodeURIComponent(currentTargetPath), { credentials: 'include' }).then(r => r.json()).then(data => {
                if (data.code === 0) { showToast('删除成功', 'success'); loadDirectory(getCurrentPath()); } else { showAlert('删除失败', data.msg); }
            }).catch(e => showAlert('出错', e.message));
        }); contextMenu.style.display = 'none';
    });
    document.getElementById('menuNewFile').addEventListener('click', function(e) {
        e.stopPropagation(); contextMenu.style.display = 'none'; createNewFile();
    });
    document.getElementById('menuNewFolder').addEventListener('click', function(e) {
        e.stopPropagation(); contextMenu.style.display = 'none'; createNewFolder();
    });

    // ========== 事件绑定 ==========
    document.addEventListener('click', function(e) {
        const link = e.target.closest('.file-item, .breadcrumb-nav a');
        if (!link) return;
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
            loadDirectory(decodeURIComponent(link.dataset.path));
        }
    });

    document.getElementById('editorSaveBtn').addEventListener('click', saveFile);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('editorOverlay').classList.contains('active')) {
            closeEditor();
        }
    });

    window.addEventListener('popstate', function(event) {
        const params = new URLSearchParams(window.location.search);
        const path = params.get('path') || '<?php echo ROOT_PATH; ?>';
        loadDirectory(path);
    });

    // ========== 搜索面板 ==========
    const searchPanel = document.getElementById('searchPanel');
    const searchInput = document.getElementById('searchInput');
    const searchToggleBtn = document.getElementById('searchToggleBtn');
    const closeSearchBtn = document.getElementById('closeSearchBtn');
    const editorCloseBtn = document.getElementById('editorCloseBtn');
    const toggleSidebarBtn = document.getElementById('toggleSidebarBtn');

    searchToggleBtn.addEventListener('click', function() {
        const isActive = searchPanel.classList.toggle('active');
        if (isActive) {
            searchInput.value = '';
            searchInput.focus();
            updateSearchCount('');
        } else {
            clearSearchHighlights();
        }
    });
    closeSearchBtn.addEventListener('click', function() {
        searchPanel.classList.remove('active');
        clearSearchHighlights();
    });
    let searchDebounceTimer;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchDebounceTimer);
        const query = this.value;
        searchDebounceTimer = setTimeout(() => {
            updateSearchCount(query);
        }, 300);
    });
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            jumpToNextMatch();
        }
    });
    editorCloseBtn.addEventListener('click', closeEditor);

    toggleSidebarBtn.addEventListener('click', function() {
        const sidebar = document.getElementById('editorSidebar');
        sidebar.classList.toggle('show');
    });

    // ========== 启动 ==========
    updateDashboard();
    setInterval(updateDashboard, 1000);
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
</script>
</body>
</html>