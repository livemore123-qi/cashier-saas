// 收银系统 - Electron 主进程
import { app, BrowserWindow, Menu, screen, ipcMain, net } from 'electron';
import { join } from 'path';

const CLOUD_URL = 'http://localhost:8000/sign-in'; // 本地服务端地址
const isDev = !app.isPackaged;

let mainWindow: BrowserWindow | null = null;
let splashWindow: BrowserWindow | null = null;

function createSplashWindow(): void {
  splashWindow = new BrowserWindow({
    width: 480,
    height: 320,
    frame: false,
    resizable: false,
    alwaysOnTop: true,
    backgroundColor: '#1e40af',
    show: false,
  });
  splashWindow.loadFile(join(__dirname, '../src/renderer/splash.html'));
  splashWindow.once('ready-to-show', () => splashWindow?.show());
}

function createMainWindow(): void {
  const { width, height } = screen.getPrimaryDisplay().workAreaSize;
  mainWindow = new BrowserWindow({
    width,
    height,
    fullscreen: true,
    frame: false,
    show: false,
    webPreferences: {
      preload: join(__dirname, 'preload.js'),
      nodeIntegration: false,
      contextIsolation: true,
      devTools: isDev,
      spellcheck: false,
    },
  });
  Menu.setApplicationMenu(null);
  mainWindow.webContents.on('context-menu', (e) => e.preventDefault());
  mainWindow.loadURL(CLOUD_URL);
  mainWindow.once('ready-to-show', () => {
    splashWindow?.close();
    splashWindow = null;
    mainWindow?.show();
  });
  mainWindow.on('closed', () => { mainWindow = null; });
}

// ====== IPC: 打印机 ======
ipcMain.handle('printer:print', async (_e, data: { device: string; text: string }) => {
  try {
    const escpos = require('escpos');
    const { USB } = require('escpos-usb');
    const device = new USB();
    const printer = new escpos.Printer(device, { encoding: 'GB18030' });
    await new Promise<void>((resolve, reject) => {
      device.open((err: Error | null) => {
        if (err) return reject(err);
        printer.align('ct').text(data.text || '').cut().close();
        resolve();
      });
    });
    return { success: true };
  } catch (e: any) {
    return { success: false, error: e.message };
  }
});

ipcMain.handle('printer:print-receipt', async (_e, receipt: {
  shopName: string;
  items: { name: string; qty: number; price: number }[];
  total: number;
  payment: string;
}) => {
  try {
    const escpos = require('escpos');
    const { USB } = require('escpos-usb');
    const device = new USB();
    const printer = new escpos.Printer(device, { encoding: 'GB18030' });
    await new Promise<void>((resolve, reject) => {
      device.open((err: Error | null) => {
        if (err) return reject(err);
        printer.align('ct').style('b').size(1, 1).text(receipt.shopName);
        printer.style('normal').size(0, 0).text('-'.repeat(32));
        receipt.items.forEach((item) => {
          printer.text(`${item.name.padEnd(16)} x${String(item.qty).padStart(3)} ¥${item.price.toFixed(2)}`);
        });
        printer.text('-'.repeat(32));
        printer.align('rt').style('b').size(1, 1).text(`合计: ¥${receipt.total.toFixed(2)}`);
        printer.style('normal').size(0, 0).text(`支付: ${receipt.payment}`);
        printer.cut().close();
        resolve();
      });
    });
    return { success: true };
  } catch (e: any) {
    return { success: false, error: e.message };
  }
});

// ====== IPC: 钱箱 ======
ipcMain.handle('cashbox:open', async () => {
  try {
    const { USB } = require('escpos-usb');
    const device = new USB();
    await new Promise<void>((resolve, reject) => {
      device.open((err: Error | null) => {
        if (err) return reject(err);
        device.write(Buffer.from([0x1B, 0x70, 0x00, 0x32, 0x32]));
        device.close();
        resolve();
      });
    });
    return { success: true };
  } catch (e: any) {
    return { success: false, error: e.message };
  }
});

// ====== IPC: 扫码枪 ======
let scanBuffer = '';
let lastKeyTime = 0;

ipcMain.handle('scanner:start', () => {
  const win = BrowserWindow.getFocusedWindow();
  if (!win) return { success: false, error: 'No window' };
  win.webContents.on('before-input-event', (_e, input) => {
    if (input.type !== 'keyDown') return;
    const now = Date.now();
    if (input.key === 'Enter' && scanBuffer.length >= 5) {
      const code = scanBuffer;
      scanBuffer = '';
      lastKeyTime = 0;
      win.webContents.send('scanner:scan', code);
      return;
    }
    if (now - lastKeyTime > 50 && scanBuffer.length > 0) scanBuffer = '';
    if (input.key.length === 1) {
      scanBuffer += input.key;
      lastKeyTime = now;
    }
  });
  return { success: true };
});

// ====== IPC: 应用 ======
ipcMain.handle('app:isOnline', () => net.isOnline());
ipcMain.handle('app:quit', () => app.quit());
ipcMain.handle('app:version', () => app.getVersion());

// ====== 初始化 ======
app.whenReady().then(() => {
  createSplashWindow();
  setTimeout(createMainWindow, 2000);
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});