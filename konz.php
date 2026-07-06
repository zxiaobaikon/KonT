<?php
// ========== 登录验证处理 ==========
session_start();

// 硬编码的用户名和密码
$VALID_USERNAME = 'abcd1234';
$VALID_PASSWORD = '123456';

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($username === $VALID_USERNAME && $password === $VALID_PASSWORD) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        // 登录成功，重定向到主面板
        header('Location: kontkz.php');
        exit;
    } else {
        // 登录失败，返回原页面并带错误参数
        header('Location: kontkz.php?error=1');
        exit;
    }
}

// 如果直接访问该文件，重定向到主面板
if (!isset($_POST['action'])) {
    header('Location: kontkz.php');
    exit;
}