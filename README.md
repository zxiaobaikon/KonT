# KonT-Z
注意！请使用https://konsv.cn/kaiy/kzmb.php访问最新文件！github版本不是最新的！

一个纯单文件 PHP 轻量 Web 仪表盘与文件管理器。零依赖，开箱即用。

<h1 align="center">🚀 系统监控与代码编辑器</h1>

<p align="center">
  <strong>一个轻量级、功能完整的 PHP 单页应用，集文件管理、代码编辑与 AI 助手于一体。</strong><br>
  适合开发环境、服务器运维或快速原型搭建。
</p>

<p align="c enter">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat-square&logo=php" alt="PHP Version">
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="License">
  <img src="https://img.shields.io/badge/Status-Stable-brightgreen?style=flat-square" alt="Status">
  <img src="https://img.shields.io/badge/AI-DeepSeek-0a84ff?style=flat-square" alt="AI Support">
</p>

---


![系统仪表盘](https://i.imgur.com/xxxxx.png)

## 📖 简介

本项目是一个**基于 Session 登录认证**的 Web 工具，提供以下核心能力：

- 📂 **文件管理**：浏览、上传、下载、删除、重命名、复制/剪切/粘贴、新建文件/文件夹
- ✏️ **在线编辑器**：基于 CodeMirror，支持 PHP/HTML/CSS/JS 等语法高亮，带搜索/替换/跳转行
- 📊 **系统监控**：实时显示系统负载、CPU 使用率、内存和磁盘占用
- 🤖 **AI 代码助手**：集成 DeepSeek API，支持代码问答对话、文件附加、流式响应
- 🌳 **侧边栏目录树**：树形结构浏览，全局文件名搜索
- 🖱️ **右键菜单**：所有操作均可通过右键快速完成
- 📱 **响应式设计**：完美适配桌面端与移动端，支持长按触屏右键
> 🔐 **安全提示**：本工具使用 Session 登录认证，默认用户名/密码为演示占位符，**部署前请务必修改**！

---

## 🚀 功能特性

| 功能模块 | 说明 |
|----------|------|
| 📁 文件浏览 | 网格视图展示文件和文件夹，支持面包屑导航 |
| ⬆️ 上传文件 | 支持拖拽上传，可批量上传，支持文件夹上传 |
| ⬇️ 下载 | 单个文件直接下载，文件夹自动打包为 ZIP |
| 🗑️ 删除 | 删除文件（支持权限自动修正） |
| ✏️ 重命名 | 修改文件或文件夹名称 |
| 📋 复制/剪切/粘贴 | 跨目录操作，支持复制和移动 |
| 📄 新建文件/文件夹 | 在任意目录下快速创建 |
| 👁️ 预览 | 图片直接预览，文本文件展示内容 |
| 🔍 全局搜索 | 在根目录下按文件名搜索（最多 100 个结果） |
| 📊 系统监控 | 实时显示负载、CPU、内存、磁盘使用率（每 3 秒刷新） |
| ✏️ 在线编辑器 | 全屏编辑器，支持语法高亮、搜索/替换、跳转行、PHP 语法检查 |
| 🤖 AI 代码助手 | 基于 DeepSeek API，支持对话、文件附加、代码块应用 |

---

### 2. 安装步骤
1. 将 `kontkz.php`、`kzai.php`、`konz.php` 三个文件下载到您的网站目录。
2. **修改登录凭证**：编辑 `konz.php`，找到以下配置并修改：
   ```php
   $VALID_USERNAME = 'abcd1234';  // 请修改为强密码
   $VALID_PASSWORD = '123456';     // 请修改为强密码
修改网站根目录：编辑 kontkz.php，找到以下配置并修改：

php
$root_path = '/var/www/html';   // 请修改为您要管理的目录
（可选）配置 AI 功能：编辑 kzai.php，找到以下配置并修改：

js
const DEEPSEEK_API_KEY = 'sk=海力士';  // 请替换为您的 DeepSeek API Key
通过浏览器访问 http://您的域名/kontkz.php 即可使用。

![KonT-Z 系统监控与文件管理器界面](https://kon666.com/github/index.png)
![KonT-Z 系统监控与文件管理器界面](https://kon666.com/github/no1.png)
![KonT-Z 系统监控与文件管理器界面](https://kon666.com/github/no2.png)


