/**
 * 小票打印机桥接层
 * 支持 USB (escpos) 和网口 (node-thermal-printer) 两种方式
 */
import { ipcMain } from 'electron';

export function initPrinter(): void {
  // 打印 ESC/POS 原始数据
  ipcMain.handle('printer:print', async (_event, data: { device: string; commands: Buffer }) => {
    try {
      // 根据设备类型选择驱动
      if (data.device.startsWith('USB')) {
        const escpos = require('escpos');
        escpos.USB = require('escpos-usb');
        const device = new escpos.USB();
        const printer = new escpos.Printer(device, { encoding: 'GB18030' });
        await new Promise<void>((resolve, reject) => {
          device.open((err: Error | null) => {
            if (err) return reject(err);
            printer.raw(data.commands).close();
            resolve();
          });
        });
      } else if (data.device.startsWith('NETWORK')) {
        const ThermalPrinter = require('node-thermal-printer').printer;
        const PrinterTypes = require('node-thermal-printer').types;
        const [host, port] = data.device.replace('NETWORK:', '').split(':');
        const printer = new ThermalPrinter({
          type: PrinterTypes.EPSON,
          interface: `tcp://${host}:${port || 9100}`,
          characterSet: 'SLOVENIA',
        });
        await printer.isPrinterConnected();
        await printer.raw(data.commands);
      }
      return { success: true };
    } catch (error: any) {
      console.error('打印失败:', error.message);
      return { success: false, error: error.message };
    }
  });

  // 快捷打印小票（结构化数据）
  ipcMain.handle('printer:print-receipt', async (_event, receipt: {
    shopName: string;
    items: Array<{ name: string; qty: number; price: number }>;
    total: number;
    payment: string;
  }) => {
    try {
      const escpos = require('escpos');
      escpos.USB = require('escpos-usb');
      const device = new escpos.USB();
      const printer = new escpos.Printer(device, { encoding: 'GB18030' });

      await new Promise<void>((resolve, reject) => {
        device.open((err: Error | null) => {
          if (err) return reject(err);

          printer
            .align('ct')
            .style('b')
            .size(1, 1)
            .text(receipt.shopName)
            .style('normal')
            .size(0, 0)
            .text('--------------------------------')
            .tableCustom([
              { text: '商品', align: 'LEFT', width: 0.5 },
              { text: '数量', align: 'CENTER', width: 0.15 },
              { text: '金额', align: 'RIGHT', width: 0.35 },
            ]);

          receipt.items.forEach((item) => {
            printer.tableCustom([
              { text: item.name, align: 'LEFT', width: 0.5 },
              { text: String(item.qty), align: 'CENTER', width: 0.15 },
              { text: `¥${item.price.toFixed(2)}`, align: 'RIGHT', width: 0.35 },
            ]);
          });

          printer
            .text('--------------------------------')
            .align('rt')
            .style('b')
            .size(1, 1)
            .text(`合计: ¥${receipt.total.toFixed(2)}`)
            .style('normal')
            .size(0, 0)
            .text(`支付方式: ${receipt.payment}`)
            .cut()
            .close();

          resolve();
        });
      });
      return { success: true };
    } catch (error: any) {
      console.error('打印小票失败:', error.message);
      return { success: false, error: error.message };
    }
  });

  // 获取可用打印机列表
  ipcMain.handle('printer:list', async () => {
    try {
      const escpos = require('escpos');
      escpos.USB = require('escpos-usb');
      const devices = escpos.USB.findPrinter();
      return { success: true, printers: devices.map((d: any) => `USB:${d.deviceDescriptor?.idProduct}`) };
    } catch (error: any) {
      return { success: false, printers: [], error: error.message };
    }
  });
}