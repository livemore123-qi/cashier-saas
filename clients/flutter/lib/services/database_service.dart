import 'dart:convert';
import 'package:sqflite/sqflite.dart';
import 'package:path/path.dart';

class DatabaseService {
  static Database? _db;

  /// 初始化数据库
  static Future<Database> get database async {
    if (_db != null) return _db!;
    _db = await _init();
    return _db!;
  }

  static Future<Database> _init() async {
    final path = join(await getDatabasesPath(), 'cashier_offline.db');

    return openDatabase(
      path,
      version: 1,
      onCreate: (db, version) async {
        await db.execute('''
          CREATE TABLE products (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            barcode TEXT,
            price REAL NOT NULL,
            category_id INTEGER,
            image_url TEXT,
            updated_at TEXT NOT NULL
          )
        ''');

        await db.execute('''
          CREATE TABLE customers (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            phone TEXT UNIQUE,
            points REAL DEFAULT 0,
            updated_at TEXT NOT NULL
          )
        ''');

        await db.execute('''
          CREATE TABLE offline_orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_data TEXT NOT NULL,
            created_at TEXT NOT NULL,
            synced INTEGER NOT NULL DEFAULT 0
          )
        ''');

        await db.execute('''
          CREATE TABLE sync_version (
            entity TEXT PRIMARY KEY,
            version TEXT NOT NULL
          )
        ''');

        await db.execute('CREATE INDEX idx_products_barcode ON products(barcode)');
        await db.execute('CREATE INDEX idx_offline_orders_synced ON offline_orders(synced)');
      },
    );
  }

  /// 搜索商品
  static Future<List<Map<String, dynamic>>> searchProducts(String keyword) async {
    final db = await database;
    return db.rawQuery(
      'SELECT * FROM products WHERE name LIKE ? OR barcode = ? LIMIT 50',
      ['%$keyword%', keyword],
    );
  }

  /// 查询会员
  static Future<Map<String, dynamic>?> getCustomer(String phone) async {
    final db = await database;
    final results = await db.query('customers', where: 'phone = ?', whereArgs: [phone]);
    return results.isNotEmpty ? results.first : null;
  }

  /// 保存离线订单
  static Future<int> saveOfflineOrder(Map<String, dynamic> order) async {
    final db = await database;
    return db.insert('offline_orders', {
      'order_data': jsonEncode(order),
      'created_at': DateTime.now().toIso8601String(),
      'synced': 0,
    });
  }

  /// 批量插入商品
  static Future<void> batchInsertProducts(List<Map<String, dynamic>> products) async {
    final db = await database;
    final batch = db.batch();
    for (final product in products) {
      batch.insert('products', product, conflictAlgorithm: ConflictAlgorithm.replace);
    }
    await batch.commit();
  }

  /// 批量插入会员
  static Future<void> batchInsertCustomers(List<Map<String, dynamic>> customers) async {
    final db = await database;
    final batch = db.batch();
    for (final customer in customers) {
      batch.insert('customers', customer, conflictAlgorithm: ConflictAlgorithm.replace);
    }
    await batch.commit();
  }
}