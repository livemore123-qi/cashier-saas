/**
 * Electron API 类型声明
 * 使 Vue 端可以直接调用 window.electronAPI
 */
import type { ElectronAPI } from '../electron/src/preload/index';

declare global {
  interface Window {
    electronAPI?: ElectronAPI;
    flutterChannel?: {
      postMessage: (message: string) => void;
    };
  }
}

export {};