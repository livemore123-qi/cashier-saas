/**
 * 本地 SQLite 数据库模块
 * 用于离线商品/会员数据缓存和离线订单存储
 */
import Database from 'better-sqlite3';
import { ipcMain } from 'electron';
import { join } from 'path';

let db: Database.Database;

export function initDatabase(): void {
  // 数据库文件存储在用户数据目录
  const dbPath = join(process.env.APPDATA || '', 'cashier-saas', 'offline.db');

  db = new Database(dbPath);
  db.pragma('journal_mode = WAL');
  db.pragma('synchronous = NORMAL');

  // 创建表结构
  db.exec(`
    CREATE TABLE IF NOT EXISTS products (
      id INTEGER PRIMARY KEY,
      name TEXT NOT NULL,
      barcode TEXT,
      price REAL NOT NULL,
      category_id INTEGER,
      image_url TEXT,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS customers (
      id INTEGER PRIMARY KEY,
      name TEXT NOT NULL,
      phone TEXT UNIQUE,
      points REAL DEFAULT 0,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS config (
      key TEXT PRIMARY KEY,
      value TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS offline_orders (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      order_data TEXT NOT NULL,
      created_at TEXT NOT NULL DEFAULT (datetime('now')),
      synced INTEGER NOT NULL DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS sync_version (
      entity TEXT PRIMARY KEY,
      version TEXT NOT NULL
    );

    CREATE INDEX IF NOT EXISTS idx_products_barcode ON products(barcode);
    CREATE INDEX IF NOT EXISTS idx_products_updated ON products(updated_at);
    CREATE INDEX IF NOT EXISTS idx_offline_orders_synced ON offline_orders(synced);
  `);

  // 注册 IPC 处理
  registerIpcHandlers();
}

function registerIpcHandlers(): void {
  // 商品搜索
  ipcMain.handle('database:get-products', (_event, keyword: string) => {
    const stmt = db.prepare(
      `SELECT * FROM products WHERE name LIKE ? OR barcode = ? LIMIT 50`
    );
    return stmt.all(`%${keyword}%`, keyword);
  });

  // 会员查询
  ipcMain.handle('database:get-customer', (_event, phone: string) => {
    const stmt = db.prepare('SELECT * FROM customers WHERE phone = ?');
    return stmt.get(phone) || null;
  });

  // 保存离线订单
  ipcMain.handle('database:save-offline-order', (_event, order: unknown) => {
    const stmt = db.prepare(
      'INSERT INTO offline_orders (order_data, synced) VALUES (?, 0)'
    );
    const result = stmt.run(JSON.stringify(order));
    return { id: result.lastInsertRowid };
  });

  // 获取待同步订单
  ipcMain.handle('database:get-pending-orders', () => {
    const stmt = db.prepare(
      'SELECT * FROM offline_orders WHERE synced = 0 ORDER BY created_at ASC'
    );
    return stmt.all();
  });

  // 标记订单已同步
  ipcMain.handle('database:mark-order-synced', (_event, id: number) => {
    const stmt = db.prepare('UPDATE offline_orders SET synced = 1 WHERE id = ?');
    stmt.run(id);
    return { success: true };
  });

  // 获取配置
  ipcMain.handle('database:get-config', (_event, key: string) => {
    const stmt = db.prepare('SELECT value FROM config WHERE key = ?');
    const row = stmt.get(key) as { value: string } | undefined;
    return row?.value || null;
  });

  // 批量插入商品（全量同步用）
  ipcMain.handle('database:batch-insert-products', (_event, products: any[]) => {
    const stmt = db.prepare(
      `INSERT OR REPLACE INTO products (id, name, barcode, price, category_id, image_url, updated_at)
       VALUES (@id, @name, @barcode, @price, @category_id, @image_url, @updated_at)`
    );
    const insertMany = db.transaction((items: any[]) => {
      for (const item of items) {
        stmt.run(item);
      }
    });
    insertMany(products);
    return { success: true, count: products.length };
  });

  // 批量插入会员
  ipcMain.handle('database:batch-insert-customers', (_event, customers: any[]) => {
    const stmt = db.prepare(
      `INSERT OR REPLACE INTO customers (id, name, phone, points, updated_at)
       VALUES (@id, @name, @phone, @points, @updated_at)`
    );
    const insertMany = db.transaction((items: any[]) => {
      for (const item of items) {
        stmt.run(item);
      }
    });
    insertMany(customers);
    return { success: true, count: customers.length };
  });

  // 更新配置
  ipcMain.handle('database:set-config', (_event, entity: string, data: any[]) => {
    const configStmt = db.prepare(
      `INSERT OR REPLACE INTO config (key, value, updated_at) VALUES (?, ?, datetime('now'))`
    );
    const versionStmt = db.prepare(
      `INSERT OR REPLACE INTO sync_version (entity, version) VALUES (?, ?)`
    );
    const batch = db.transaction(() => {
      for (const item of data) {
        configStmt.run(`${entity}:${item.key}`, item.value);
      }
      versionStmt.run(entity, new Date().toISOString());
    });
    batch();
    return { success: true };
  });
}

export { db };