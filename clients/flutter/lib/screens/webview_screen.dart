import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import '../services/printer_service.dart';
import '../services/scanner_service.dart';
import '../services/database_service.dart';

class WebViewScreen extends StatefulWidget {
  final String url;
  const WebViewScreen({super.key, required this.url});

  @override
  State<WebViewScreen> createState() => _WebViewScreenState();
}

class _WebViewScreenState extends State<WebViewScreen> {
  late final WebViewController _controller;
  bool _isOnline = true;

  @override
  void initState() {
    super.initState();
    _initWebView();
    _listenNetwork();
  }

  void _initWebView() {
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageFinished: (_) {
            _injectJavaScriptBridge();
          },
        ),
      )
      ..addJavaScriptChannel(
        'printer',
        onMessageReceived: _handlePrinterMessage,
      )
      ..addJavaScriptChannel(
        'scanner',
        onMessageReceived: _handleScannerMessage,
      )
      ..addJavaScriptChannel(
        'database',
        onMessageReceived: _handleDatabaseMessage,
      )
      ..addJavaScriptChannel(
        'cashbox',
        onMessageReceived: _handleCashboxMessage,
      )
      ..loadRequest(Uri.parse(widget.url));
  }

  /// 注入 JS 桥接代码
  void _injectJavaScriptBridge() {
    _controller.runJavaScript('''
      // 标记为 Flutter WebView 环境
      window.flutterChannel = {
        postMessage: function(message) {}
      };
      
      // 暴露硬件调用接口
      window.electronAPI = {
        printer: {
          print: function(data) {
            PrinterChannel.postMessage(JSON.stringify(data));
          },
          printReceipt: function(receipt) {
            PrinterChannel.postMessage(JSON.stringify({type: 'receipt', data: receipt}));
          }
        },
        scanner: {
          onScan: function(callback) {
            window.__scannerCallback = callback;
            ScannerChannel.postMessage('start');
          }
        },
        database: {
          getProducts: function(keyword) {
            return new Promise(function(resolve) {
              DatabaseChannel.postMessage(JSON.stringify({action: 'getProducts', keyword: keyword}));
              window.__dbCallback = resolve;
            });
          }
        },
        cashbox: {
          open: function() {
            CashboxChannel.postMessage('open');
          }
        }
      };
    ''');
  }

  /// 处理打印机消息
  void _handlePrinterMessage(JavaScriptMessage msg) async {
    try {
      final data = jsonDecode(msg.message);
      if (data['type'] == 'receipt') {
        await PrinterService.printReceipt(data['data']);
      } else {
        await PrinterService.printRaw(data);
      }
    } catch (e) {
      debugPrint('打印失败: $e');
    }
  }

  /// 处理扫码枪消息
  void _handleScannerMessage(JavaScriptMessage msg) async {
    if (msg.message == 'start') {
      final barcode = await ScannerService.scan();
      if (barcode != null) {
        _controller.runJavaScript(
          'window.__scannerCallback && window.__scannerCallback("$barcode")',
        );
      }
    }
  }

  /// 处理数据库消息
  void _handleDatabaseMessage(JavaScriptMessage msg) async {
    try {
      final data = jsonDecode(msg.message);
      if (data['action'] == 'getProducts') {
        final products = await DatabaseService.searchProducts(data['keyword']);
        _controller.runJavaScript(
          'window.__dbCallback && window.__dbCallback(${jsonEncode(products)})',
        );
      }
    } catch (e) {
      debugPrint('数据库操作失败: $e');
    }
  }

  /// 处理钱箱消息
  void _handleCashboxMessage(JavaScriptMessage msg) async {
    if (msg.message == 'open') {
      await PrinterService.openCashDrawer();
    }
  }

  /// 监听网络状态
  void _listenNetwork() {
    Connectivity().onConnectivityChanged.listen((results) {
      final online = results.any((r) => r != ConnectivityResult.none);
      if (online != _isOnline) {
        setState(() => _isOnline = online);
        _controller.runJavaScript(
          'document.dispatchEvent(new CustomEvent("network-change", {detail: {online: $online}}))',
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: [
          WebViewWidget(controller: _controller),
          // 离线提示条
          if (!_isOnline)
            Positioned(
              top: 0,
              left: 0,
              right: 0,
              child: Container(
                padding: const EdgeInsets.symmetric(vertical: 4),
                color: Colors.orange.shade700,
                child: const Text(
                  '网络已断开，当前为离线收银模式',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.white, fontSize: 13),
                ),
              ),
            ),
        ],
      ),
    );
  }

  @override
  void dispose() {
    super.dispose();
  }
}