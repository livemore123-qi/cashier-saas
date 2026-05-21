/**
 * 硬件调用服务
 * 封装 Electron Client 的硬件 API，在浏览器模式下自动降级为 mock
 */
import { isElectron } from '~/libraries/environment';

interface ElectronAPI {
  printer: {
    print: (data: unknown) => Promise<{ success: boolean; error?: string }>;
    printReceipt: (receipt: unknown) => Promise<{ success: boolean; error?: string }>;
    getPrinters: () => Promise<{ success: boolean; printers: string[] }>;
  };
  cashbox: {
    open: () => Promise<{ success: boolean; error?: string }>;
  };
  scanner: {
    onScan: (callback: (data: string) => void) => void;
    removeListener: () => void;
  };
  database: {
    getProducts: (keyword: string) => Promise<any[]>;
    getCustomer: (phone: string) => Promise<any>;
    saveOfflineOrder: (order: unknown) => Promise<{ id: number }>;
    getPendingOrders: () => Promise<any[]>;
    markOrderSynced: (id: number) => Promise<{ success: boolean }>;
    getConfig: (key: string) => Promise<string | null>;
  };
  sync: {
    forceSync: () => Promise<void>;
    getStatus: () => Promise<{ syncing: boolean; lastSync: string }>;
  };
  app: {
    quit: () => Promise<void>;
    isOnline: () => Promise<boolean>;
    getVersion: () => Promise<string>;
  };
}

const mockAPI: ElectronAPI = {
  printer: {
    print: async () => {
      console.log('[Mock] 模拟打印');
      return { success: true };
    },
    printReceipt: async () => {
      console.log('[Mock] 模拟打印小票');
      return { success: true };
    },
    getPrinters: async () => ({ success: true, printers: ['模拟打印机'] }),
  },
  cashbox: {
    open: async () => {
      console.log('[Mock] 模拟打开钱箱');
      return { success: true };
    },
  },
  scanner: {
    onScan: (callback) => {
      console.log('[Mock] 扫码枪监听已启动（按 F8 模拟扫码）');
      const handler = (e: KeyboardEvent) => {
        if (e.key === 'F8') {
          callback('6901234567890');
        }
      };
      document.addEventListener('keydown', handler);
    },
    removeListener: () => {},
  },
  database: {
    getProducts: async () => [],
    getCustomer: async () => null,
    saveOfflineOrder: async () => ({ id: 0 }),
    getPendingOrders: async () => [],
    markOrderSynced: async () => ({ success: true }),
    getConfig: async () => null,
  },
  sync: {
    forceSync: async () => {},
    getStatus: async () => ({ syncing: false, lastSync: '' }),
  },
  app: {
    quit: async () => {},
    isOnline: async () => navigator.onLine,
    getVersion: async () => 'web-1.0.0',
  },
};

/**
 * 获取硬件 API（Electron 环境用真实 API，浏览器用 mock）
 */
export function getHardware(): ElectronAPI {
  if (isElectron() && (window as any).electronAPI) {
    return (window as any).electronAPI as ElectronAPI;
  }
  return mockAPI;
}

/**
 * 判断当前是否在 Electron 环境中
 */
export { isElectron };

/**
 * 打印小票
 */
export async function printReceipt(items: Array<{ name: string; qty: number; price: number }>, total: number, payment: string): Promise<boolean> {
  const hw = getHardware();
  const shopName = await hw.database.getConfig('shop_name') || '收银系统';
  const result = await hw.printer.printReceipt({ shopName, items, total, payment });
  return result.success;
}

/**
 * 打开钱箱
 */
export async function openCashDrawer(): Promise<boolean> {
  const hw = getHardware();
  const result = await hw.cashbox.open();
  return result.success;
}

/**
 * 监听扫码枪
 */
export function onBarcodeScanned(callback: (barcode: string) => void): void {
  const hw = getHardware();
  hw.scanner.onScan(callback);
}