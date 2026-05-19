<?php

namespace App\Extensions\CLI;

use App\Extensions\ExtensionBase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * 平台 CLI 模块
 * 
 * 功能：
 * - Artisan 命令行工具
 * - 批量操作（批量改价、批量上下架）
 * - 数据导入导出
 * - 定时任务管理
 * - 系统维护命令
 */
class CLIExtension extends ExtensionBase
{
    protected string $code = 'cli';
    protected string $name = '平台CLI';
    protected string $type = 'cli';
    protected string $version = '1.0.0';
    protected string $description = '平台命令行工具：批量操作、数据导入导出、定时任务';
    protected string $author = '收银SaaS';

    protected array $config = [
        'enabled' => true,
        'allow_remote' => true,  // 允许远程执行
        'commands' => [
            'batch-update-price',
            'batch-toggle-status',
            'import-products',
            'export-orders',
            'sync-inventory',
            'cleanup-logs',
        ],
    ];

    public function register(): void
    {
        $this->registerCommands();
    }

    public function boot(): void
    {
        // 注册定时任务
        $this->registerSchedules();
    }

    /**
     * 注册 CLI 命令
     */
    private function registerCommands(): void
    {
        // 命令在 Commands/ 目录下，通过 Artisan 注册
    }

    /**
     * 注册定时任务
     */
    private function registerSchedules(): void
    {
        // - 每小时：同步库存
        // - 每天凌晨：清理过期日志
        // - 每天凌晨：生成日报
        // - 每周：全量备份
    }
}

/**
 * 通用批量操作命令
 */
class BatchOperationCommand
{
    /**
     * 批量更新商品价格
     */
    public static function updatePrices(array $productIds, float $percentage, bool $isIncrease = true): array
    {
        $updated = 0;
        $failed = [];

        foreach ($productIds as $productId) {
            try {
                $product = \App\Models\Product::find($productId);
                
                if ($product) {
                    $oldPrice = $product->price;
                    $newPrice = $isIncrease 
                        ? $oldPrice * (1 + $percentage / 100)
                        : $oldPrice * (1 - $percentage / 100);
                    
                    $product->price = round($newPrice, 2);
                    $product->save();
                    
                    Log::info("批量改价", [
                        'product_id' => $productId,
                        'old_price' => $oldPrice,
                        'new_price' => $newPrice,
                    ]);
                    
                    $updated++;
                }
            } catch (\Exception $e) {
                $failed[] = $productId;
                Log::error("批量改价失败", [
                    'product_id' => $productId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => $updated,
            'failed' => count($failed),
            'total' => count($productIds),
        ];
    }

    /**
     * 批量切换商品状态（上架/下架）
     */
    public static function toggleStatus(array $productIds, string $status): array
    {
        $updated = 0;
        $failed = [];

        $validStatus = ['active', 'inactive', 'draft'];

        if (!in_array($status, $validStatus)) {
            return ['error' => "无效状态: {$status}"];
        }

        foreach ($productIds as $productId) {
            try {
                $product = \App\Models\Product::find($productId);
                
                if ($product) {
                    $product->status = $status;
                    $product->save();
                    $updated++;
                }
            } catch (\Exception $e) {
                $failed[] = $productId;
            }
        }

        return [
            'success' => $updated,
            'failed' => count($failed),
        ];
    }

    /**
     * 批量设置分类
     */
    public static function setCategories(array $productIds, int $categoryId): array
    {
        $updated = 0;

        \App\Models\Product::whereIn('id', $productIds)
            ->update(['category_id' => $categoryId]);

        return ['success' => count($productIds)];
    }
}

/**
 * 数据导入服务
 */
class ImportService
{
    /**
     * 从 CSV 导入商品
     */
    public static function importProducts(string $filePath, array $options = []): array
    {
        $handle = fopen($filePath, 'r');
        
        if (!$handle) {
            return ['error' => '无法打开文件'];
        }

        $headers = fgetcsv($handle);
        $imported = 0;
        $failed = 0;
        $errors = [];

        // 映射列名
        $map = $options['column_map'] ?? [
            'sku' => 0,
            'name' => 1,
            'price' => 2,
            'category' => 3,
        ];

        while (($row = fgetcsv($handle)) !== false) {
            try {
                $data = [
                    'sku' => $row[$map['sku']] ?? '',
                    'name' => $row[$map['name']] ?? '',
                    'price' => floatval($row[$map['price']] ?? 0),
                    'category' => $row[$map['category']] ?? '',
                ];

                // 验证必填字段
                if (empty($data['sku']) || empty($data['name'])) {
                    throw new \Exception("SKU或名称为空");
                }

                // 检查是否已存在
                $exists = \App\Models\Product::where('sku', $data['sku'])->exists();
                
                if ($exists && !($options['update_existing'] ?? false)) {
                    throw new \Exception("商品已存在");
                }

                // 创建或更新
                $product = \App\Models\Product::updateOrCreate(
                    ['sku' => $data['sku']],
                    [
                        'name' => $data['name'],
                        'price' => $data['price'],
                    ]
                );

                $imported++;

            } catch (\Exception $e) {
                $failed++;
                $errors[] = "行 {$imported + $failed}: " . $e->getMessage();
            }
        }

        fclose($handle);

        return [
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * 从 Excel 导入
     */
    public static function importFromExcel(string $filePath): array
    {
        // 需要 phpoffice/phpspreadsheet
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $rows = $worksheet->toArray();
            
            // 第一行是表头
            $headers = array_shift($rows);
            
            $imported = 0;
            $failed = 0;

            foreach ($rows as $row) {
                try {
                    $data = array_combine($headers, $row);
                    
                    \App\Models\Product::updateOrCreate(
                        ['sku' => $data['sku'] ?? ''],
                        [
                            'name' => $data['name'] ?? '',
                            'price' => floatval($data['price'] ?? 0),
                            'stock' => intval($data['stock'] ?? 0),
                        ]
                    );
                    
                    $imported++;
                } catch (\Exception $e) {
                    $failed++;
                }
            }

            return ['imported' => $imported, 'failed' => $failed];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

/**
 * 数据导出服务
 */
class ExportService
{
    /**
     * 导出订单到 CSV
     */
    public static function exportOrders(array $filters, string $outputPath): array
    {
        $query = \App\Models\Order::query();

        // 应用筛选
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $orders = $query->with(['items', 'customer'])->get();

        $handle = fopen($outputPath, 'w');
        
        // 表头
        fputcsv($handle, [
            '订单号', '日期', '客户', '商品', '数量', '金额', '支付方式', '状态'
        ]);

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                fputcsv($handle, [
                    $order->code,
                    $order->created_at->format('Y-m-d H:i'),
                    $order->customer->name ?? '散客',
                    $item->product_name,
                    $item->quantity,
                    $item->total,
                    $order->payment_method,
                    $order->status,
                ]);
            }
        }

        fclose($handle);

        return [
            'file' => $outputPath,
            'count' => $orders->count(),
        ];
    }

    /**
     * 导出商品到 Excel
     */
    public static function exportProducts(array $filters, string $outputPath): array
    {
        $products = \App\Models\Product::with('category')
            ->when(!empty($filters['category']), fn($q) => $q->where('category_id', $filters['category']))
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->get();

        $reader = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $reader->getActiveSheet();
        
        // 表头
        $sheet->setCellValue('A1', 'SKU');
        $sheet->setCellValue('B1', '商品名称');
        $sheet->setCellValue('C1', '分类');
        $sheet->setCellValue('D1', '价格');
        $sheet->setCellValue('E1', '库存');
        $sheet->setCellValue('F1', '状态');

        $row = 2;
        foreach ($products as $product) {
            $sheet->setCellValue("A{$row}", $product->sku);
            $sheet->setCellValue("B{$row}", $product->name);
            $sheet->setCellValue("C{$row}", $product->category->name ?? '');
            $sheet->setCellValue("D{$row}", $product->price);
            $sheet->setCellValue("E{$row}", $product->stock);
            $sheet->setCellValue("F{$row}", $product->status);
            $row++;
        }

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($reader, 'Xlsx');
        $writer->save($outputPath);

        return [
            'file' => $outputPath,
            'count' => $products->count(),
        ];
    }
}

/**
 * 系统维护命令
 */
class SystemMaintenanceCommand
{
    /**
     * 清理过期日志
     */
    public static function cleanupLogs(int $days = 30): int
    {
        $cutoff = now()->subDays($days);

        $count = \App\Models\SystemLog::where('created_at', '<', $cutoff)->delete();

        Log::info("清理过期日志", ['deleted' => $count]);

        return $count;
    }

    /**
     * 清理临时文件
     */
    public static function cleanupTempFiles(): int
    {
        $tempPath = storage_path('app/temp/');
        $count = 0;

        if (is_dir($tempPath)) {
            $files = glob($tempPath . '*');
            
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < time() - 86400) {
                    unlink($file);
                    $count++;
                }
            }
        }

        Log::info("清理临时文件", ['deleted' => $count]);

        return $count;
    }

    /**
     * 重建缓存
     */
    public static function rebuildCache(): bool
    {
        try {
            // 清除所有缓存
            \Illuminate\Support\Facades\Cache::flush();

            // 重建配置缓存
            Artisan::call('config:cache');

            // 重建路由缓存
            Artisan::call('route:cache');

            // 重建视图缓存
            Artisan::call('view:cache');

            Log::info("缓存重建完成");

            return true;

        } catch (\Exception $e) {
            Log::error("缓存重建失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 检查数据库完整性
     */
    public static function checkDatabaseIntegrity(): array
    {
        $issues = [];

        // 检查孤立的外键记录
        // 检查缺失的必填字段
        // 检查重复的 SKU

        $duplicateSkus = \App\Models\Product::select('sku')
            ->groupBy('sku')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicateSkus->count() > 0) {
            $issues['duplicate_skus'] = $duplicateSkus;
        }

        return [
            'status' => empty($issues) ? 'ok' : 'issues_found',
            'issues' => $issues,
        ];
    }
}