import fs from 'node:fs/promises';
import path from 'node:path';

const outputDir = path.resolve('docs/partner-test/assets');
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

const targets = await fetch('http://127.0.0.1:9224/json/list').then((response) => response.json());
const target = targets.find((item) => item.type === 'page');
if (!target) throw new Error('Nenhuma pagina do Chromium encontrada.');

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
const evaluate = (expression) => send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });

async function screenshot(name) {
  const result = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
  await fs.writeFile(path.join(outputDir, `${name}.png`), Buffer.from(result.data, 'base64'));
}

await send('Page.enable');
await send('Runtime.enable');
await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 980, deviceScaleFactor: 1, mobile: false });
await send('Page.navigate', { url: 'http://127.0.0.1:8081/manager/' });
await pause(1800);

const loginFields = await evaluate(`document.querySelectorAll('input').length`);
if (loginFields.result.value >= 2) await evaluate(`(() => {
  const inputs = [...document.querySelectorAll('input')];
  const setValue = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
  setValue.call(inputs[0], 'http://127.0.0.1:8081');
  inputs[0].dispatchEvent(new Event('input', { bubbles: true }));
  setValue.call(inputs[1], ${JSON.stringify(env.EVOLUTION_API_KEY)});
  inputs[1].dispatchEvent(new Event('input', { bubbles: true }));
  inputs[1].dispatchEvent(new Event('change', { bubbles: true }));
})()`);
if (loginFields.result.value >= 2) {
  await pause(500);
  await evaluate(`document.querySelector('button').click()`);
  await pause(2500);
}
await screenshot('evolution-02-instancias');

const bodyText = await evaluate(`document.body.innerText`);
await fs.writeFile(path.join(outputDir, 'manager-screen.txt'), bodyText.result.value);

const controls = await evaluate(`[...document.querySelectorAll('button, a')].map((item, index) => ({
  index,
  text: item.innerText,
  title: item.title,
  ariaLabel: item.getAttribute('aria-label'),
  html: item.outerHTML.slice(0, 500),
}))`);
await fs.writeFile(path.join(outputDir, 'manager-controls.json'), JSON.stringify(controls.result.value, null, 2));

const openedInstance = await evaluate(`(() => {
  const target = document.querySelector('a[href*="/instance/"][href$="/dashboard"]');
  if (!target) return false;
  target.click();
  return true;
})()`);

if (openedInstance.result.value) {
  await pause(2500);
  await screenshot('evolution-03-detalhe');

  const detailControls = await evaluate(`[...document.querySelectorAll('button, a')].map((item, index) => ({
    index,
    text: item.innerText,
    title: item.title,
    ariaLabel: item.getAttribute('aria-label'),
    html: item.outerHTML.slice(0, 500),
  }))`);
  await fs.writeFile(path.join(outputDir, 'detail-controls.json'), JSON.stringify(detailControls.result.value, null, 2));

  const clickedConnect = await evaluate(`(() => {
    const candidates = [...document.querySelectorAll('button, a')];
    const target = candidates.find((item) => /connect|conectar|qr code|qrcode/i.test(item.innerText));
    if (!target) return false;
    target.click();
    return true;
  })()`);
  if (clickedConnect.result.value) {
    await pause(2500);
    await evaluate(`(() => {
      const dialog = document.querySelector('[role="dialog"]');
      if (!dialog) return;
      [...dialog.querySelectorAll('img, canvas')].forEach((item) => {
        item.style.filter = 'blur(18px)';
        item.style.opacity = '0.28';
      });
      const warning = document.createElement('div');
      warning.textContent = 'QR CODE OCULTADO — gere um novo na hora do teste';
      warning.style.cssText = 'position:absolute;inset:42% 12% auto;z-index:9999;padding:16px;background:#102b33;color:#fff;border-radius:8px;text-align:center;font-weight:800';
      dialog.style.position = 'relative';
      dialog.appendChild(warning);
    })()`);
    await screenshot('evolution-04-qrcode');
  }
}

socket.close();
