// 收银系统 - 模拟后端服务
// 模拟 Laravel Sanctum 的认证流程，供 Electron 客户端连接测试
const http = require('http');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const PORT = 8000;
const ROOT = __dirname;

// 模拟用户数据库
const USERS = {
  admin: { username: 'admin', password: '123456', name: '管理员' },
  cashier: { username: 'cashier', password: '123456', name: '收银员' },
};

// 生成 CSRF Token
function csrfToken() {
  return crypto.randomBytes(32).toString('hex');
}

let currentToken = '';

// MIME types
const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.ico': 'image/x-icon',
};

function json(res, data, status = 200) {
  res.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8', 'Access-Control-Allow-Origin': '*' });
  res.end(JSON.stringify(data));
}

function serveFile(res, filePath) {
  const ext = path.extname(filePath);
  const mime = MIME[ext] || 'application/octet-stream';
  try {
    const content = fs.readFileSync(filePath);
    res.writeHead(200, { 'Content-Type': mime, 'Access-Control-Allow-Origin': '*' });
    res.end(content);
  } catch {
    res.writeHead(404);
    res.end('Not Found');
  }
}

const server = http.createServer((req, res) => {
  const url = new URL(req.url, `http://localhost:${PORT}`);
  const method = req.method;

  // CORS 预检
  if (method === 'OPTIONS') {
    res.writeHead(204, {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET,POST,PUT,DELETE,OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type,Authorization,X-XSRF-TOKEN,X-Requested-With',
      'Access-Control-Allow-Credentials': 'true',
    });
    return res.end();
  }

  console.log(`[${new Date().toLocaleTimeString()}] ${method} ${url.pathname}`);

  // ===== API 路由 =====

  // CSRF Cookie
  if (url.pathname === '/sanctum/csrf-cookie') {
    currentToken = csrfToken();
    res.setHeader('Set-Cookie', `XSRF-TOKEN=${encodeURIComponent(currentToken)}; Path=/; SameSite=Lax`);
    return json(res, { message: 'ok' });
  }

  // 获取登录字段
  if (url.pathname === '/api/v1/fields/ns.login' || url.pathname === '/api/fields/ns.login') {
    return json(res, [
      {
        name: 'username',
        type: 'text',
        label: '用户名',
        description: '请输入您的用户名',
        placeholder: '请输入用户名',
        validation: 'required|min:3',
        value: '',
        disabled: false,
        errors: [],
      },
      {
        name: 'password',
        type: 'password',
        label: '密码',
        description: '请输入您的密码',
        placeholder: '请输入密码',
        validation: 'required|min:6',
        value: '',
        disabled: false,
        errors: [],
      },
    ]);
  }

  // 登录
  if (url.pathname === '/auth/sign-in' && method === 'POST') {
    let body = '';
    req.on('data', (chunk) => (body += chunk));
    req.on('end', () => {
      try {
        const { username, password } = JSON.parse(body);
        const user = USERS[username];
        if (!user || user.password !== password) {
          return json(res, {
            message: '用户名或密码错误',
            data: { username: ['用户名或密码不正确'], password: ['用户名或密码不正确'] },
          }, 422);
        }
        json(res, {
          message: '登录成功',
          data: { redirectTo: '/cashier', user: { name: user.name, username: user.username } },
        });
      } catch {
        json(res, { message: '请求格式错误' }, 400);
      }
    });
    return;
  }

  // ===== 页面路由 =====
  if (url.pathname === '/' || url.pathname === '/sign-in') {
    return serveFile(res, path.join(ROOT, 'public/sign-in.html'));
  }

  if (url.pathname === '/cashier') {
    return serveFile(res, path.join(ROOT, 'cashier.html'));
  }

  if (url.pathname === '/dashboard') {
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    return res.end(`
<!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="UTF-8"><title>收银系统 - 仪表盘</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:'Microsoft YaHei',sans-serif;background:#f0f2f5;min-height:100vh}
  .header{background:linear-gradient(135deg,#1e3a8a,#3b82f6);color:#fff;padding:16px 24px;display:flex;align-items:center;justify-content:space-between}
  .header h1{font-size:20px}
  .user{font-size:14px;opacity:.9}
  .content{padding:24px;max-width:1200px;margin:0 auto}
  .card{background:#fff;border-radius:12px;padding:24px;margin-bottom:16px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
  .card h2{font-size:16px;margin-bottom:12px;color:#333}
  .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
  .stat{background:#fff;border-radius:12px;padding:20px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.06)}
  .stat .num{font-size:32px;font-weight:700;color:#3b82f6}
  .stat .label{font-size:13px;color:#999;margin-top:4px}
</style></head>
<body>
<div class="header">
  <h1>收银系统</h1>
  <span class="user">管理员</span>
</div>
<div class="content">
  <div class="stats">
    <div class="stat"><div class="num">0</div><div class="label">今日订单</div></div>
    <div class="stat"><div class="num">¥0</div><div class="label">今日营收</div></div>
    <div class="stat"><div class="num">0</div><div class="label">待处理</div></div>
    <div class="stat"><div class="num">在线</div><div class="label">系统状态</div></div>
  </div>
  <div class="card" style="margin-top:16px">
    <h2>快速操作</h2>
    <p style="color:#999">收银系统已就绪，请在收银机客户端操作。</p>
    <p style="color:#999;margin-top:8px">服务端运行在 http://localhost:${PORT}</p>
  </div>
</div>
</body></html>`);
  }

  // 静态文件
  serveFile(res, path.join(ROOT, url.pathname));
});

server.listen(PORT, () => {
  console.log('');
  console.log('  ╔══════════════════════════════════╗');
  console.log('  ║   收银系统 - 模拟后端服务       ║');
  console.log(`  ║   地址: http://localhost:${PORT}    ║`);
  console.log('  ║                                ║');
  console.log('  ║   测试账号:                    ║');
  console.log('  ║   admin  / 123456              ║');
  console.log('  ║   cashier / 123456             ║');
  console.log('  ╚══════════════════════════════════╝');
  console.log('');
});