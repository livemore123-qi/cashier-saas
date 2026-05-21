/**
 * 扫码枪桥接层
 * 通过全局键盘钩子识别扫码枪输入
 * 扫码枪特征：输入速度极快（通常 <50ms/字符），以回车结尾
 */
import { ipcMain, BrowserWindow } from 'electron';

let scanBuffer = '';
let lastKeyTime = 0;
const SCAN_THRESHOLD_MS = 50; // 字符间最大间隔，超过则认为是手动输入
const MIN_SCAN_LENGTH = 5; // 最短扫码长度

export function initScanner(): void {
  // 监听全局按键（通过 BrowserWindow 的 before-input-event）
  // 注意：需要在创建窗口后进行绑定
  ipcMain.handle('scanner:start-listen', () => {
    const win = BrowserWindow.getFocusedWindow();
    if (!win) return { success: false, error: 'No focused window' };

    win.webContents.on('before-input-event', (_event, input) => {
      if (input.type !== 'keyDown') return;

      const now = Date.now();
      const key = input.key;

      // 回车 = 扫码完成
      if (key === 'Enter' && scanBuffer.length >= MIN_SCAN_LENGTH) {
        const code = scanBuffer;
        scanBuffer = '';
        lastKeyTime = 0;
        win.webContents.send('scanner:scan', code);
        return;
      }

      // 间隔过长 = 手动输入，重置
      if (now - lastKeyTime > SCAN_THRESHOLD_MS && scanBuffer.length > 0) {
        scanBuffer = '';
      }

      // 只收集可打印字符
      if (key.length === 1) {
        scanBuffer += key;
        lastKeyTime = now;
      }
    });

    return { success: true };
  });

  // 也可以通过 serialport 方式直接监听串口扫码枪
  ipcMain.handle('scanner:start-serial', async (_event, portPath: string) => {
    try {
      const { SerialPort } = require('serialport');
      const { ReadlineParser } = require('@serialport/parser-readline');

      const port = new SerialPort({ path: portPath, baudRate: 9600 });
      const parser = port.pipe(new ReadlineParser({ delimiter: '\r\n' }));

      parser.on('data', (data: string) => {
        const win = BrowserWindow.getFocusedWindow();
        if (win) {
          win.webContents.send('scanner:scan', data.trim());
        }
      });

      return { success: true };
    } catch (error: any) {
      return { success: false, error: error.message };
    }
  });
}