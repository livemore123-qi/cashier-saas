// 预加载脚本 - 暴露硬件 API 给渲染进程
import { contextBridge, ipcRenderer } from 'electron';

contextBridge.exposeInMainWorld('electronAPI', {
  printer: {
    print: (data: unknown) => ipcRenderer.invoke('printer:print', data),
    printReceipt: (receipt: unknown) => ipcRenderer.invoke('printer:print-receipt', receipt),
  },
  cashbox: {
    open: () => ipcRenderer.invoke('cashbox:open'),
  },
  scanner: {
    start: () => ipcRenderer.invoke('scanner:start'),
    onScan: (cb: (code: string) => void) => {
      ipcRenderer.on('scanner:scan', (_e, code: string) => cb(code));
    },
  },
  app: {
    quit: () => ipcRenderer.invoke('app:quit'),
    isOnline: () => ipcRenderer.invoke('app:isOnline'),
    version: () => ipcRenderer.invoke('app:version'),
  },
});