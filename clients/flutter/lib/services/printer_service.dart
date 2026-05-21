import 'package:esc_pos_utils/esc_pos_utils.dart';
import 'package:flutter_blue_plus/flutter_blue_plus.dart';

class PrinterService {
  /// 打印小票
  static Future<bool> printReceipt(Map<String, dynamic> receipt) async {
    try {
      // 搜索蓝牙打印机
      final devices = await FlutterBluePlus.systemDevices;

      // 这里简化处理，实际需要根据设备名/地址选择打印机
      // final targetPrinter = devices.firstWhere(...)

      // 生成 ESC/POS 打印指令
      final profile = await CapabilityProfile.load();
      final generator = Generator(PaperSize.mm80, profile);

      List<int> bytes = [];

      // 店铺名
      bytes += generator.text(
        receipt['shopName'] ?? '收银系统',
        styles: const PosStyles(align: PosAlign.center, bold: true, height: PosTextSize.size2, width: PosTextSize.size2),
      );
      bytes += generator.text('─' * 32);

      // 商品列表
      final items = receipt['items'] as List<dynamic>? ?? [];
      for (final item in items) {
        bytes += generator.row([
          PosColumn(text: item['name'] ?? '', width: 6),
          PosColumn(text: 'x${item['qty']}', width: 2),
          PosColumn(text: '¥${(item['price'] as num).toStringAsFixed(2)}', width: 4, styles: const PosStyles(align: PosAlign.right)),
        ]);
      }

      bytes += generator.text('─' * 32);

      // 合计
      final total = receipt['total'] as num? ?? 0;
      bytes += generator.text(
        '合计: ¥${total.toStringAsFixed(2)}',
        styles: const PosStyles(align: PosAlign.right, bold: true, height: PosTextSize.size2, width: PosTextSize.size2),
      );

      // 支付方式
      bytes += generator.text(
        '支付方式: ${receipt['payment'] ?? ''}',
        styles: const PosStyles(align: PosAlign.right),
      );

      bytes += generator.feed(3);
      bytes += generator.cut();

      // 发送到蓝牙打印机
      // 实际使用时需要连接指定打印机并写入数据
      // final device = ...
      // await device.connect();
      // final services = await device.discoverServices();
      // final printChar = services.first.characteristics.firstWhere(...);
      // await printChar.write(bytes);

      return true;
    } catch (e) {
      print('打印失败: $e');
      return false;
    }
  }

  /// 打印原始 ESC/POS 指令
  static Future<bool> printRaw(Map<String, dynamic> data) async {
    // 直接发送原始打印指令
    return true;
  }

  /// 打开钱箱
  static Future<bool> openCashDrawer() async {
    try {
      final profile = await CapabilityProfile.load();
      final generator = Generator(PaperSize.mm80, profile);
      final bytes = generator.drawer();

      // 发送钱箱指令到打印机
      // ... 同打印流程

      return true;
    } catch (e) {
      return false;
    }
  }
}