/**
 * 钱箱桥接层
 * 通过 ESC/POS 指令触发钱箱弹出（通常通过打印机端口发送）
 */
import { ipcMain } from 'electron';

export function initCashbox(): void {
  ipcMain.handle('cashbox:open', async () => {
    try {
      // 查找 USB 打印机设备
      const escpos = require('escpos');
      escpos.USB = require('escpos-usb');
      const device = new escpos.USB();

      await new Promise<void>((resolve, reject) => {
        device.open((err: Error | null) => {
          if (err) return reject(err);
          // ESC/POS 钱箱脉冲指令: ESC p m t1 t2
          // m=0 表示连接钱箱1号引脚, t1=50(高电平50ms), t2=50(低电平50ms)
          const cashDrawerCommand = Buffer.from([0x1B, 0x70, 0x00, 0x32, 0x32]);
          device.write(cashDrawerCommand);
          device.close();
          resolve();
        });
      });
      return { success: true };
    } catch (error: any) {
      console.error('钱箱打开失败:', error.message);

      // 尝试串口方式
      try {
        const { SerialPort } = require('serialport');
        const ports = await SerialPort.list();
        const receiptPrinter = ports.find((p: any) =>
          p.manufacturer?.includes('EPSON') || p.manufacturer?.includes('Star')
        );
        if (receiptPrinter) {
          const port = new SerialPort({ path: receiptPrinter.path, baudRate: 9600 });
          await new Promise<void>((resolve, reject) => {
            port.write(Buffer.from([0x1B, 0x70, 0x00, 0x32, 0x32]), (err: Error | null) => {
              port.close();
              if (err) return reject(err);
              resolve();
            });
          });
          return { success: true };
        }
      } catch {
        // 串口方式也失败
      }

      return { success: false, error: error.message };
    }
  });
}