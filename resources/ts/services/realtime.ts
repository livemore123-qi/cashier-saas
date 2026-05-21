/**
 * Realtime 实时通信服务
 * 封装 Laravel Echo + Reverb 连接
 */
import Echo from 'laravel-echo';
import { getPlatform } from '~/libraries/environment';

let echoInstance: Echo | null = null;

export function initRealtime(): Echo {
  if (echoInstance) return echoInstance;

  const platform = getPlatform();
  const wsHost = window.location.hostname;
  const wsPort = platform === 'web' ? 6001 : 8080; // Reverb 默认端口
  const protocol = window.location.protocol === 'https:' ? 'wss' : 'ws';

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'reverb-key',
    wsHost,
    wsPort,
    wssPort: wsPort,
    forceTLS: protocol === 'wss:',
    enabledTransports: ['ws', 'wss'],
  });

  return echoInstance;
}

/**
 * 监听新订单
 */
export function onNewOrder(callback: (order: any) => void): void {
  const echo = initRealtime();
  echo.channel('store.0.orders').listen('.order.created', (data: any) => {
    callback(data.order);
  });
}

/**
 * 监听库存变更
 */
export function onStockUpdate(callback: (product: any) => void): void {
  const echo = initRealtime();
  echo.channel('store.0.stock').listen('.stock.updated', (data: any) => {
    callback(data.product);
  });
}

/**
 * 监听离线订单同步完成
 */
export function onOrderSyncComplete(callback: (data: any) => void): void {
  const echo = initRealtime();
  echo.channel('store.0.sync').listen('.order.synced', (data: any) => {
    callback(data);
  });
}

/**
 * 断开连接
 */
export function disconnectRealtime(): void {
  if (echoInstance) {
    echoInstance.disconnect();
    echoInstance = null;
  }
}