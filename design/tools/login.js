// Ouvre une session WordPress locale et enregistre les cookies dans .auth/state.json (identifiants lus dans le .env du site).
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const envPath = process.argv[2] || path.resolve(__dirname, '../../../../../.env');
const env = fs.readFileSync(envPath, 'utf8');
const user = env.match(/^username\s*=\s*(.*)$/m)[1].trim();
const pass = env.match(/^password\s*=\s*(.*)$/m)[1].trim();

(async () => {
  const b = await chromium.launch(process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {});
  const ctx = await b.newContext({ ignoreHTTPSErrors: true });
  const p = await ctx.newPage();
  await p.goto('https://agency.local/wp-login.php');
  await p.fill('#user_login', user);
  await p.fill('#user_pass', pass);
  await Promise.all([p.waitForNavigation(), p.click('#wp-submit')]);
  fs.mkdirSync(path.join(__dirname, '.auth'), { recursive: true });
  await ctx.storageState({ path: path.join(__dirname, '.auth', 'state.json') });
  console.log('Session enregistrée.');
  await b.close();
})();
