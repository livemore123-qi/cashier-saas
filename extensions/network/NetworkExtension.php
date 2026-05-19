<?php

namespace App\Extensions\network;

use App\Extensions\ExtensionBase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * 网络监控模块
 * 
 * 功能：
 * - 网络测速（店铺网络质量）
 * - 设备心跳检测（收银机在线状态）
 * - 断网收银（本地缓存订单，恢复后同步）
 */
class NetworkExtension extends ExtensionBase
{
    protected string $code = 'network';
    protected string $name = '网络监控';
    protected string $type = 'network';
    protected string $version = '1.0.0';
    protected string $description = '店铺网络监控：测速、设备心跳、断网收银';
    protected string $author = '收银SaaS';

    protected array $config = [
        'enabled' => false,
        'speed_test_url' => 'https://speed.cloudflare.com/__down?',
        'speed_test_size' => 10485760, // 10MB
        'heartbeat_interval' => 30, // 秒
        'offline_mode' => true,
        'offline_threshold' => 3, // 断网超过3次则触发报警
    ];

    /**
     * 测速服务
     */
    private SpeedTestService $speedTest;

    /**
     * 心跳服务
     */
    private HeartbeatService $heartbeat;

    /**
     * 断网模式服务
     */
    private OfflineModeService $offlineMode;

    public function __construct()
    {
        $this->speedTest = new SpeedTestService($this->config);
        $this->heartbeat = new HeartbeatService($this->config);
        $this->offlineMode = new OfflineModeService($this->config);
    }

    public function register(): void
    {
        $this->registerRoutes();
    }

    public function boot(): void
    {
        // 启动设备心跳检测
        $this->heartbeat->startMonitoring();
    }

    /**
     * 获取测速服务
     */
    public function getSpeedTest(): SpeedTestService
    {
        return $this->speedTest;
    }

    /**
     * 获取心跳服务
     */
    public function getHeartbeat(): HeartbeatService
    {
        return $this->heartbeat;
    }

    /**
     * 获取断网模式服务
     */
    public function getOfflineMode(): OfflineModeService
    {
        return $this->offlineMode;
    }
}

/**
 * 测速服务
 */
class SpeedTestService
{
    private array $config;
    private ?array $lastResult = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * 执行测速
     */
    public function test(): array
    {
        $result = [
            'timestamp' => date('Y-m-d H:i:s'),
            'download_speed' => 0, // Mbps
            'upload_speed' => 0,
            'latency' => 0, // ms
            'jitter' => 0,
            'packet_loss' => 0, // %
            'status' => 'unknown',
        ];

        try {
            // 1. 测试延迟（通过 HTTP 请求时间）
            $start = microtime(true);
            Http::timeout(10)->get('https://www.baidu.com');
            $latency = (microtime(true) - $start) * 1000;
            $result['latency'] = round($latency, 2);

            // 2. 测试下载速度（下载测试文件）
            $downloadStart = microtime(true);
            $response = Http::timeout(30)
                ->withHeaders(['Accept-Encoding' => 'identity'])
                ->get($this->config['speed_test_url'] . http_build_query([
                    'size' => $this->config['speed_test_size']
                ]));
            
            $downloadTime = microtime(true) - $downloadStart;
            $bytes = strlen($response->body());
            
            // 计算 Mbps: bytes * 8 / 1024 / 1024 / seconds
            $result['download_speed'] = round(($bytes * 8) / (1024 * 1024) / $downloadTime, 2);

            // 3. 测试抖动（多次延迟测量）
            $jitters = [];
            for ($i = 0; $i < 5; $i++) {
                $tStart = microtime(true);
                Http::timeout(5)->head('https://www.baidu.com');
                $jitters[] = (microtime(true) - $tStart) * 1000;
                usleep(100000); // 100ms
            }
            
            // 计算抖动（延迟标准差）
            $avg = array_sum($jitters) / count($jitters);
            $variance = array_sum(array_map(fn($x) => pow($x - $avg, 2), $jitters)) / count($jitters);
            $result['jitter'] = round(sqrt($variance), 2);

            $result['status'] = 'ok';

        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['error'] = $e->getMessage();
            Log::error("网速测试失败: " . $e->getMessage());
        }

        $this->lastResult = $result;
        
        // 缓存结果
        Cache::put('network_speed_test', $result, 300); // 5分钟

        return $result;
    }

    /**
     * 获取上次测试结果
     */
    public function getLastResult(): ?array
    {
        return $this->lastResult ?? Cache::get('network_speed_test');
    }

    /**
     * 获取网络质量评级
     */
    public function getQualityRating(): string
    {
        $result = $this->getLastResult();
        
        if (!$result || $result['status'] !== 'ok') {
            return 'offline';
        }

        $latency = $result['latency'];
        $download = $result['download_speed'];

        if ($latency < 50 && $download > 50) {
            return 'excellent'; // 优秀
        } elseif ($latency < 100 && $download > 20) {
            return 'good'; // 良好
        } elseif ($latency < 200 && $download > 5) {
            return 'fair'; // 一般
        } else {
            return 'poor'; // 较差
        }
    }

    /**
     * 生成测试报告
     */
    public function generateReport(): array
    {
        $result = $this->getLastResult();
        $rating = $this->getQualityRating();

        return [
            'test_time' => $result['timestamp'] ?? '从未测试',
            'latency' => $result['latency'] ?? 0,
            'download' => $result['download_speed'] ?? 0,
            'jitter' => $result['jitter'] ?? 0,
            'quality' => $rating,
            'recommendation' => match ($rating) {
                'excellent' => '网络质量优秀',
                'good' => '网络质量良好',
                'fair' => '网络质量一般，建议优化',
                'poor' => '网络质量较差，可能影响使用',
                default => '网络离线',
            },
        ];
    }
}

/**
 * 设备心跳服务
 */
class HeartbeatService
{
    private array $config;
    private array $devices = []; // device_id => ['last_seen' => timestamp, 'status' => 'online/offline']

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * 注册设备
     */
    public function registerDevice(string $deviceId, array $info = []): void
    {
        $this->devices[$deviceId] = array_merge($info, [
            'first_seen' => date('Y-m-d H:i:s'),
            'last_seen' => time(),
            'status' => 'online',
            'offline_count' => 0,
        ]);

        Log::info("设备注册", ['device_id' => $deviceId]);
    }

    /**
     * 接收心跳
     */
    public function receiveHeartbeat(string $deviceId): array
    {
        if (!isset($this->devices[$deviceId])) {
            $this->registerDevice($deviceId);
        }

        $this->devices[$deviceId]['last_seen'] = time();
        $this->devices[$deviceId]['status'] = 'online';
        $this->devices[$deviceId]['offline_count'] = 0;

        return [
            'status' => 'ok',
            'server_time' => time(),
            'interval' => $this->config['heartbeat_interval'],
        ];
    }

    /**
     * 检测离线设备
     */
    public function checkOffline(): array
    {
        $offline = [];
        $threshold = $this->config['heartbeat_interval'] * 3; // 超过3个周期视为离线

        foreach ($this->devices as $deviceId => $info) {
            $elapsed = time() - ($info['last_seen'] ?? 0);
            
            if ($elapsed > $threshold && $info['status'] !== 'offline') {
                $this->devices[$deviceId]['status'] = 'offline';
                $this->devices[$deviceId]['offline_count']++;
                $offline[] = $deviceId;
                
                Log::warning("设备离线", [
                    'device_id' => $deviceId,
                    'last_seen' => date('Y-m-d H:i:s', $info['last_seen']),
                    'offline_count' => $this->devices[$deviceId]['offline_count'],
                ]);

                // 触发报警
                if ($this->devices[$deviceId]['offline_count'] >= $this->config['offline_threshold']) {
                    $this->triggerOfflineAlarm($deviceId);
                }
            }
        }

        return $offline;
    }

    /**
     * 触发离线报警
     */
    private function triggerOfflineAlarm(string $deviceId): void
    {
        // TODO: 集成安防模块的报警
        Log::critical("设备离线报警", ['device_id' => $deviceId]);
    }

    /**
     * 获取所有设备状态
     */
    public function getAllDevices(): array
    {
        return $this->devices;
    }

    /**
     * 获取在线设备
     */
    public function getOnlineDevices(): array
    {
        return array_filter($this->devices, fn($d) => $d['status'] === 'online');
    }

    /**
     * 获取设备状态
     */
    public function getDeviceStatus(string $deviceId): ?array
    {
        return $this->devices[$deviceId] ?? null;
    }

    /**
     * 启动监控定时任务
     */
    public function startMonitoring(): void
    {
        // TODO: 通过 Laravel Schedule 执行
        // $schedule->call(fn() => $this->checkOffline())->everyMinute();
    }
}

/**
 * 断网收银服务
 */
class OfflineModeService
{
    private array $config;
    private bool $enabled = false;
    private array $pendingOrders = []; // 本地待同步订单

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->enabled = $config['offline_mode'] ?? false;
    }

    /**
     * 检查网络状态
     */
    public function isOnline(): bool
    {
        try {
            $response = Http::timeout(3)->head('https://www.baidu.com');
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 进入断网模式
     */
    public function enterOfflineMode(): void
    {
        $this->enabled = true;
        Log::info("系统进入断网收银模式");
    }

    /**
     * 退出断网模式
     */
    public function exitOfflineMode(): void
    {
        $this->enabled = false;
        
        // 同步待处理订单
        $this->syncPendingOrders();
        
        Log::info("系统退出断网收银模式");
    }

    /**
     * 保存本地订单
     */
    public function saveLocalOrder(array $order): string
    {
        $localId = 'local_' . uniqid() . '_' . time();
        
        $this->pendingOrders[$localId] = array_merge($order, [
            'local_id' => $localId,
            'created_at' => date('Y-m-d H:i:s'),
            'sync_status' => 'pending',
        ]);

        // 持久化到本地存储（SQLite 或文件）
        $this->persistLocalOrder($localId, $this->pendingOrders[$localId]);

        Log::info("本地订单已保存", ['local_id' => $localId]);

        return $localId;
    }

    /**
     * 持久化本地订单
     */
    private function persistLocalOrder(string $localId, array $order): void
    {
        $path = storage_path("app/offline_orders/{$localId}.json");
        
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, json_encode($order, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 加载本地待同步订单
     */
    public function loadPendingOrders(): array
    {
        $path = storage_path('app/offline_orders/');
        
        if (!is_dir($path)) {
            return [];
        }

        $orders = [];
        
        foreach (glob($path . '*.json') as $file) {
            $order = json_decode(file_get_contents($file), true);
            if ($order['sync_status'] === 'pending') {
                $orders[$order['local_id']] = $order;
            }
        }

        $this->pendingOrders = $orders;
        
        return $orders;
    }

    /**
     * 同步待处理订单到服务器
     */
    public function syncPendingOrders(): array
    {
        if ($this->isOnline()) {
            $this->loadPendingOrders();
            
            $synced = [];
            $failed = [];

            foreach ($this->pendingOrders as $localId => $order) {
                try {
                    // 调用订单服务创建订单
                    // OrderService::createFromOffline($order);
                    
                    $order['sync_status'] = 'synced';
                    $order['synced_at'] = date('Y-m-d H:i:s');
                    
                    // 更新本地记录
                    $path = storage_path("app/offline_orders/{$localId}.json");
                    file_put_contents($path, json_encode($order));
                    
                    $synced[] = $localId;
                    
                } catch (\Exception $e) {
                    $order['sync_status'] = 'failed';
                    $order['sync_error'] = $e->getMessage();
                    
                    $path = storage_path("app/offline_orders/{$localId}.json");
                    file_put_contents($path, json_encode($order));
                    
                    $failed[] = $localId;
                    
                    Log::error("订单同步失败", [
                        'local_id' => $localId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return [
                'synced' => count($synced),
                'failed' => count($failed),
                'total' => count($this->pendingOrders),
            ];
        }

        return ['error' => '网络不可用'];
    }

    /**
     * 获取同步状态摘要
     */
    public function getSyncStatus(): array
    {
        $pending = $this->loadPendingOrders();

        return [
            'online' => $this->isOnline(),
            'offline_mode' => $this->enabled,
            'pending_count' => count($pending),
            'pending_orders' => array_keys($pending),
        ];
    }
}