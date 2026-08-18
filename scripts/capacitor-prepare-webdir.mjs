/**
 * يكتب resources/capacitor/www/index.html مطلوبًا من Capacitor للنسخ.
 * عند ضبط CAPACITOR_SERVER_URL يوجّه WebView فورًا إلى خادم Laravel (HTTPS).
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const www = path.join(__dirname, '..', 'resources', 'capacitor', 'www');

fs.mkdirSync(www, { recursive: true });

const raw = (process.env.CAPACITOR_SERVER_URL || '').trim().replace(/\/+$/, '');
const loginPath = (process.env.CAPACITOR_LOGIN_PATH || '/').trim();
const startPath = raw ? `${raw}${loginPath.startsWith('/') ? loginPath : `/${loginPath}`}` : '';

const html = `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="color-scheme" content="light">
  <title>خارج المخزون</title>
  <style>
    html,body{margin:0;height:100%;background:#000000;color:#e5e7eb;font-family:system-ui,sans-serif;}
    .c{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100%;padding:max(1rem, env(safe-area-inset-top));text-align:center;}
    p{max-width:22rem;line-height:1.55;font-size:14px;color:#9ca3af;}
  </style>
</head>
<body>
<div class="c">
  <p id="msg">جاري فتح التطبيق…</p>
</div>
<script>
(function(){
  var u = ${JSON.stringify(startPath)};
  if (!u) {
    document.getElementById('msg').textContent =
      'لم يُضبط CAPACITOR_SERVER_URL. من الجذر: CAPACITOR_SERVER_URL=https://your-domain npm run cap:sync';
    return;
  }
  window.location.replace(u);
})();
</script>
</body>
</html>
`;

fs.writeFileSync(path.join(www, 'index.html'), html, 'utf8');
// eslint-disable-next-line no-console
console.log(
    '[capacitor-www]',
    raw ? `entry → ${startPath}` : 'stub index (set CAPACITOR_SERVER_URL for redirect)',
);
