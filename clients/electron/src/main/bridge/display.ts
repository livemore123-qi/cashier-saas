/**
 * 客显屏桥接层（硬件依赖在实际运行时可选安装）
 */
import { ipcMain } from 'electron';

let displayConnected = false;

export function initDisplay(): void {
  ipcMain.handle('display:connect', async (_event, _portPath: string) => {
    displayConnected = true;
    return { success: true, note: '请安装 serialport 模块以获得硬件支持' };
  });

  ipcMain.handle('display:show-price', async (_event, price: string) => {
    if (!displayConnected) return { success: false, error: '客显未连接' };
    return { success: true, price };
  });

  ipcMain.handle('display:show-text', async (_event, text: string) => {
    if (!displayConnected) return { success: false, error: '客显未连接' };
    return { success: true, text };
  });

  ipcMain.handle('display:clear', async () => {
    return { success: true };
  });
}