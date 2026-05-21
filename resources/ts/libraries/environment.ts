/**
 * 环境检测工具
 */
export function isElectron(): boolean {
  return !!(window as any).electronAPI;
}

export function isNativeApp(): boolean {
  return isElectron() || isFlutterWebView();
}

export function isFlutterWebView(): boolean {
  return !!(window as any).flutterChannel;
}

export function isOnline(): boolean {
  return navigator.onLine;
}

export function getPlatform(): 'web' | 'electron' | 'flutter' {
  if (isElectron()) return 'electron';
  if (isFlutterWebView()) return 'flutter';
  return 'web';
}