# 越山对话ai

**由 mountain 开发**

## 项目简介

“越山对话ai”是一个基于 PHP 实现的智能对话系统，旨在提供一个简洁、高效的 AI 对话体验。本项目经过安全加固，适用于生产环境部署。

## 安全特性 (已修复)

- **环境变量隔离**：敏感配置（如管理密码）现已存储在 `.env` 文件中，不进入版本控制，防止源码泄露导致密码外泄。
- **SSL 强制校验**：修复了原代码中关闭 SSL 校验的安全隐患，默认开启 `CURLOPT_SSL_VERIFYPEER`，确保与 AI 接口通信的安全性，防止中间人攻击。
- **配置与源码分离**：`models.json` 和 `.env` 已加入 `.gitignore`。仓库中仅保留 `.example` 模板文件，确保 API Key 等私密信息不会意外推送到公开仓库。
- **全站强制登录**：集成统一身份验证模块。访问前必须通过密码验证。
- **API 密钥脱敏**：管理后台中，已保存的密钥以 `********` 形式显示。
- **CSRF 跨站请求伪造防护**：所有 POST 请求（对话、模型增删改）均引入了 Token 校验，防止第三方恶意网站伪造请求。
- **Session 安全加固**：启用了 `HttpOnly`（防止脚本读取 Cookie）和 `SameSite: Lax`（减少 CSRF 风险）配置。
- **Git 历史深度清理**：已彻底从 Git 提交历史中抹除曾包含 API Key 的 `models.json` 痕迹。

## 快速开始

1. **部署代码**：将项目克隆或上传至支持 PHP 的服务器。
2. **环境配置**：
   - 复制 `.env.example` 为 `.env`：`cp .env.example .env`
   - 编辑 `.env` 文件，修改 `ADMIN_PASSWORD` 为您的强密码。
   - 复制 `models.json.example` 为 `models.json`：`cp models.json.example .json`
3. **访问系统**：
   - 浏览器打开 `index.php`。
   - 使用您在 `.env` 中设置的密码登录。
4. **管理模型**：
   - 访问 `kjzz025247.php`（或您重命名后的后台文件）配置 AI 模型和 API Key。

## 技术栈

- **后端**: PHP (需启用 `curl` 扩展)
- **前端**: HTML, CSS, JavaScript
- **API**: 支持任何兼容 OpenAI API 格式的接口

## 开发者与鸣谢

- **项目所有者**: **mountain**
- **核心贡献者**: GitHub 用户 [@Jemesnb](https://github.com/Jemesnb)
  - 提供了深度安全审计与专业改进建议。
  - 通过 Pull Request 贡献了关键的安全加固、性能优化及 UI 修复代码。
  - 协助项目完成了从教学示例到生产级安全标准的蜕变。

## 许可证

本项目采用 [MIT License](LICENSE) 开源。
