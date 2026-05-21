/**
 * 后台同步服务
 * 定时从云端同步商品/会员数据，上传离线订单
 */
import { net } from 'electron';
import { db } from '../database/database';

const SYNC_INTERVAL_MS = 30000; // 30秒
const CLOUD_API = process.env.CASHIER_CLOUD_URL || 'https://your-saas.com';
let syncTimer: NodeJS.Timeout | null = null;
let isSyncing = false;

// 启动定时同步
export function startSync(): void {
  // 首次全量同步
  fullSync();

  // 定时增量同步
  syncTimer = setInterval(() => {
    if (!isSyncing && net.isOnline()) {
      incrementalSync();
      uploadPendingOrders();
    }
  }, SYNC_INTERVAL_MS);
}

// 停止同步
export function stopSync(): void {
  if (syncTimer) {
    clearInterval(syncTimer);
    syncTimer = null;
  }
}

// 获取上次同步版本
function getLastVersion(entity: string): string {
  const row = db.prepare('SELECT version FROM sync_version WHERE entity = ?').get(entity);
  return (row as any)?.version || '1970-01-01T00:00:00Z';
}

// 全量同步
async function fullSync(): Promise<void> {
  if (isSyncing || !net.isOnline()) return;
  isSyncing = true;

  try {
    // 同步商品
    await fetchAndStore(`${CLOUD_API}/api/v1/sync/products`, 'products');
    // 同步会员
    await fetchAndStore(`${CLOUD_API}/api/v1/sync/customers`, 'customers');
    // 同步配置
    await fetchAndStore(`${CLOUD_API}/api/v1/sync/config`, 'config');

    console.log('[Sync] 全量同步完成');
  } catch (error: any) {
    console.error('[Sync] 全量同步失败:', error.message);
  } finally {
    isSyncing = false;
  }
}

// 增量同步
async function incrementalSync(): Promise<void> {
  if (isSyncing || !net.isOnline()) return;
  isSyncing = true;

  try {
    const entities = ['products', 'customers'];
    for (const entity of entities) {
      const version = getLastVersion(entity);
      await fetchAndStore(`${CLOUD_API}/api/v1/sync/${entity}?since=${encodeURIComponent(version)}`, entity);
    }
    console.log('[Sync] 增量同步完成');
  } catch (error: any) {
    console.error('[Sync] 增量同步失败:', error.message);
  } finally {
    isSyncing = false;
  }
}

// 上传离线订单
async function uploadPendingOrders(): Promise<void> {
  if (!net.isOnline()) return;

  const stmt = db.prepare('SELECT * FROM offline_orders WHERE synced = 0 ORDER BY created_at ASC');
  const orders = stmt.all() as any[];

  for (const order of orders) {
    try {
      const response = await fetch(`${CLOUD_API}/api/v1/sync/orders`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: order.order_data,
      });

      if (response.ok) {
        db.prepare('UPDATE offline_orders SET synced = 1 WHERE id = ?').run(order.id);
        console.log(`[Sync] 订单 ${order.id} 同步成功`);
      } else if (response.status === 409) {
        // 冲突，跳过
        db.prepare('UPDATE offline_orders SET synced = 1 WHERE id = ?').run(order.id);
        console.log(`[Sync] 订单 ${order.id} 已存在，跳过`);
      } else {
        // 其他错误，停止上传（保序）
        console.error(`[Sync] 订单 ${order.id} 同步失败: ${response.status}`);
        break;
      }
    } catch (error: any) {
      console.error(`[Sync] 订单 ${order.id} 上传失败:`, error.message);
      break;
    }
  }
}

// 辅助：拉取并存储数据
async function fetchAndStore(url: string, entity: string): Promise<void> {
  const response = await fetch(url);
  if (!response.ok) throw new Error(`HTTP ${response.status}`);

  const result = await response.json();
  if (result.data && result.data.length > 0) {
    if (entity === 'products') {
      db.prepare(`INSERT OR REPLACE INTO products (id, name, barcode, price, category_id, image_url, updated_at)
        VALUES (@id, @name, @barcode, @price, @category_id, @image_url, @updated_at)`).run(result.data);
    } else if (entity === 'customers') {
      db.prepare(`INSERT OR REPLACE INTO customers (id, name, phone, points, updated_at)
        VALUES (@id, @name, @phone, @points, @updated_at)`).run(result.data);
    }
  }

  if (result.version) {
    db.prepare('INSERT OR REPLACE INTO sync_version (entity, version) VALUES (?, ?)')
      .run(entity, result.version);
  }
}