import fs from 'node:fs/promises';
import path from 'node:path';

const baseUrl = 'http://127.0.0.1:8001';
const outputDir = path.resolve('docs/manual/assets');
const env = Object.fromEntries(
  (await fs.readFile('.env', 'utf8'))
    .split(/\r?\n/)
    .filter((line) => line && !line.startsWith('#') && line.includes('='))
    .map((line) => {
      const separator = line.indexOf('=');
      return [line.slice(0, separator), line.slice(separator + 1).replace(/^['"]|['"]$/g, '')];
    }),
);

await fs.mkdir(outputDir, { recursive: true });

const targets = await fetch('http://127.0.0.1:9222/json/list').then((response) => response.json());
const target = targets.find((item) => item.type === 'page');

if (!target) {
  throw new Error('Nenhuma pagina do Chromium encontrada.');
}

const socket = new WebSocket(target.webSocketDebuggerUrl);
let sequence = 0;
const pending = new Map();

socket.addEventListener('message', (event) => {
  const message = JSON.parse(event.data);
  if (!message.id || !pending.has(message.id)) return;
  const { resolve, reject } = pending.get(message.id);
  pending.delete(message.id);
  if (message.error) reject(new Error(message.error.message));
  else resolve(message.result);
});

await new Promise((resolve, reject) => {
  socket.addEventListener('open', resolve, { once: true });
  socket.addEventListener('error', reject, { once: true });
});

function send(method, params = {}) {
  const id = ++sequence;
  return new Promise((resolve, reject) => {
    pending.set(id, { resolve, reject });
    socket.send(JSON.stringify({ id, method, params }));
  });
}

const pause = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

async function navigate(url) {
  await send('Page.navigate', { url });
  await pause(1800);
}

async function evaluate(expression) {
  return send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
}

async function screenshot(name, url) {
  await navigate(`${baseUrl}${url}`);
  await evaluate(`document.documentElement.style.scrollBehavior = 'auto'`);
  const result = await send('Page.captureScreenshot', {
    format: 'png',
    captureBeyondViewport: false,
  });
  await fs.writeFile(path.join(outputDir, `${name}.png`), Buffer.from(result.data, 'base64'));
}

await send('Page.enable');
await send('Runtime.enable');
await send('Emulation.setDeviceMetricsOverride', {
  width: 1440,
  height: 980,
  deviceScaleFactor: 1,
  mobile: false,
});

await navigate(`${baseUrl}/admin/login`);
await evaluate(`(() => {
  const email = document.querySelector('input[type="email"]');
  const password = document.querySelector('input[type="password"]');
  if (!email || !password) throw new Error('Campos de login nao encontrados');
  const setValue = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
  setValue.call(email, ${JSON.stringify(env.ADMIN_SEED_EMAIL || 'admin@sorrisoevida.com.br')});
  email.dispatchEvent(new Event('input', { bubbles: true }));
  email.dispatchEvent(new Event('change', { bubbles: true }));
  setValue.call(password, ${JSON.stringify(env.ADMIN_SEED_PASSWORD || 'password')});
  password.dispatchEvent(new Event('input', { bubbles: true }));
  password.dispatchEvent(new Event('change', { bubbles: true }));
})()`);
await pause(700);
await evaluate(`document.querySelector('form').requestSubmit()`);
await pause(2500);

const loginState = await evaluate(`({ url: location.href, text: document.body.innerText })`);
if (loginState.result.value.url.includes('/login')) {
  throw new Error(`Login local falhou: ${loginState.result.value.text.slice(0, 240)}`);
}

const pages = [
  ['01-dashboard', '/admin'],
  ['02-agenda', '/admin/appointments'],
  ['03-nova-consulta', '/admin/appointments/create'],
  ['04-pacientes', '/admin/patients/create'],
  ['05-dentistas', '/admin/dentists/create'],
  ['06-procedimentos', '/admin/procedures/create'],
  ['07-horarios', '/admin/availabilities/create'],
  ['08-bloqueios', '/admin/schedule-blocks/create'],
  ['09-dentistas-lista', '/admin/dentists'],
  ['10-procedimentos-lista', '/admin/procedures'],
  ['11-horarios-lista', '/admin/availabilities'],
  ['12-bloqueios-lista', '/admin/schedule-blocks'],
];

for (const [name, url] of pages) {
  await screenshot(name, url);
}

socket.close();
