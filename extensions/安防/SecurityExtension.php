<?php

namespace App\Extensions\安防;

use App\Extensions\ExtensionBase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 店铺安防模块
 * 
 * 功能：
 * - 摄像头实时监控（RTSP/GB28181）
 * - 报警系统（异常交易、设备离线）
 * - 安防录像存储与回放
 */
class SecurityExtension extends ExtensionBase
{
    protected string $code = 'security';
    protected string $name = '店铺安防';
    protected string $type = '安防';
    protected string $version = '1.0.0';
    protected string $description = '店铺安防：摄像头监控、报警系统、网络监测';
    protected string $author = '收银SaaS';

    protected array $config = [
        'enabled' => false,
        'cameras' => [],      // 摄像头列表
        'alarm_enabled' => true,
        'alarm_webhook' => '',
        'storage_days' => 7, // 录像保留天数
    ];

    /**
     * 摄像头管理器
     */
    private CameraManager $cameraManager;

    /**
     * 报警管理器
     */
    private AlarmManager $alarmManager;

    public function __construct()
    {
        $this->cameraManager = new CameraManager($this->config);
        $this->alarmManager = new AlarmManager($this->config);
    }

    public function register(): void
    {
        // 注册路由
        $this->registerRoutes();
        
        // 注册菜单
        $this->registerMenu();
    }

    public function boot(): void
    {
        // 启动定时任务：心跳检测、设备状态监控
        $this->scheduleTasks();
    }

    /**
     * 注册安防相关路由
     */
    private function registerRoutes(): void
    {
        // 路由由 RouteServiceProvider 管理
        // 前缀: /api/extensions/security/
    }

    /**
     * 注册菜单项
     */
    private function registerMenu(): void
    {
        // 菜单项由 MenuService 管理
    }

    /**
     * 定时任务
     */
    private function scheduleTasks(): void
    {
        // - 每分钟：摄像头心跳检测
        // - 每5分钟：设备在线状态检测
        // - 每天：清理过期录像
    }

    /**
     * 获取摄像头管理器
     */
    public function getCameraManager(): CameraManager
    {
        return $this->cameraManager;
    }

    /**
     * 获取报警管理器
     */
    public function getAlarmManager(): AlarmManager
    {
        return $this->alarmManager;
    }
}

/**
 * 摄像头管理器
 * 支持：RTSP 直接流、GB28181 国标协议
 */
class CameraManager
{
    private array $config;
    private array $cameras = [];

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->loadCameras();
    }

    /**
     * 加载摄像头配置
     */
    private function loadCameras(): void
    {
        $cameras = $this->config['cameras'] ?? [];
        
        foreach ($cameras as $camera) {
            $this->cameras[$camera['id']] = new Camera($camera);
        }
    }

    /**
     * 添加摄像头
     */
    public function addCamera(array $config): Camera
    {
        $camera = new Camera($config);
        $this->cameras[$camera->getId()] = $camera;
        return $camera;
    }

    /**
     * 获取摄像头
     */
    public function getCamera(string $id): ?Camera
    {
        return $this->cameras[$id] ?? null;
    }

    /**
     * 获取所有摄像头
     */
    public function getAllCameras(): array
    {
        return $this->cameras;
    }

    /**
     * 获取在线摄像头
     */
    public function getOnlineCameras(): array
    {
        return array_filter($this->cameras, fn($c) => $c->isOnline());
    }

    /**
     * 检测摄像头状态
     */
    public function checkStatus(): array
    {
        $status = [];
        
        foreach ($this->cameras as $id => $camera) {
            $status[$id] = [
                'online' => $camera->ping(),
                'last_check' => date('Y-m-d H:i:s'),
                'rtsp_url' => $camera->getRtspUrl(),
            ];
        }

        return $status;
    }

    /**
     * 获取实时流地址
     * 返回可用于 video 标签的流地址
     */
    public function getStreamUrl(string $cameraId, string $type = 'hls'): ?string
    {
        $camera = $this->getCamera($cameraId);
        
        if (!$camera) {
            return null;
        }

        // 根据摄像头类型和协议返回对应流地址
        // RTSP -> 转码 -> HLS 流
        // 或者直接返回 RTSP (部分浏览器支持)
        
        return $camera->getStreamUrl($type);
    }
}

/**
 * 摄像头实体
 */
class Camera
{
    private string $id;
    private string $name;
    private string $ip;
    private int $port;
    private string $username;
    private string $password;
    private string $protocol = 'rtsp'; // rtsp | gb28181
    private string $channel; // 通道号
    private bool $enabled = true;
    private ?string $rtspUrl;
    private bool $online = false;
    private ?string $manufacturer; // 海康/大华/宇视等

    public function __construct(array $config)
    {
        $this->id = $config['id'] ?? '';
        $this->name = $config['name'] ?? '';
        $this->ip = $config['ip'] ?? '';
        $this->port = $config['port'] ?? 554;
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->protocol = $config['protocol'] ?? 'rtsp';
        $this->channel = $config['channel'] ?? '1';
        $this->enabled = $config['enabled'] ?? true;
        $this->manufacturer = $config['manufacturer'] ?? '';
        
        // 构建 RTSP URL
        if (!empty($config['rtsp_url'])) {
            $this->rtspUrl = $config['rtsp_url'];
        } else {
            $this->rtspUrl = $this->buildRtspUrl();
        }
    }

    /**
     * 构建 RTSP URL
     */
    private function buildRtspUrl(): string
    {
        $user = !empty($this->username) ? "{$this->username}:{$this->password}@" : '';
        $port = $this->port !== 554 ? ":{$this->port}" : '';
        
        // 根据厂商选择默认路径
        $path = match ($this->manufacturer) {
            'hikvision' => "/Streaming/Channels/101",
            'dahua' => "/cam/realmonitor?channel=1&subtype=0",
            'uniview' => "/Streaming/Channels/101",
            default => "/live/av0",
        };
        
        return "rtsp://{$user}{$this->ip}{$port}{$path}";
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRtspUrl(): ?string
    {
        return $this->rtspUrl;
    }

    public function isOnline(): bool
    {
        return $this->online;
    }

    /**
     * 检测摄像头是否在线
     */
    public function ping(): bool
    {
        // 尝试通过 HTTP 检测摄像头状态
        $url = "http://{$this->ip}/ISAPI/System/deviceInfo";
        
        try {
            $response = Http::timeout(3)
                ->withBasicAuth($this->username, $this->password)
                ->get($url);
            
            $this->online = $response->successful();
        } catch (\Exception $e) {
            $this->online = false;
            Log::debug("摄像头 {$this->id} 检测失败: " . $e->getMessage());
        }

        return $this->online;
    }

    /**
     * 获取流地址
     * type: hls | rtsp | webrtc
     */
    public function getStreamUrl(string $type = 'hls'): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        switch ($type) {
            case 'rtsp':
                return $this->rtspUrl;
            case 'hls':
                // 需要通过转码服务生成 HLS 流
                // TODO: 集成转码服务（如 nginx-rtmp 或 FFmpeg）
                return "/api/extensions/security/stream/{$this->id}/hls.m3u8";
            case 'webrtc':
                // TODO: WebRTC 直连或代理
                return "/api/extensions/security/stream/{$this->id}/webrtc";
            default:
                return $this->rtspUrl;
        }
    }

    /**
     * 截图
     */
    public function snapshot(): ?string
    {
        // 调用摄像头截图 API
        $url = "http://{$this->ip}/ISAPI/Streaming/Channels/101/picture";
        
        try {
            $response = Http::timeout(5)
                ->withBasicAuth($this->username, $this->password)
                ->withHeaders(['Accept' => 'image/jpeg'])
                ->get($url);
            
            if ($response->successful()) {
                // 保存到临时文件或云存储
                $filename = "snapshot_{$this->id}_" . time() . ".jpg";
                $path = storage_path("app/temp/{$filename}");
                
                \File::put($path, $response->body());
                
                return $path;
            }
        } catch (\Exception $e) {
            Log::error("摄像头截图失败: " . $e->getMessage());
        }

        return null;
    }
}

/**
 * 报警管理器
 */
class AlarmManager
{
    private array $config;
    private array $alarms = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * 触发报警
     */
    public function trigger(string $type, array $data): void
    {
        if (!($this->config['alarm_enabled'] ?? false)) {
            return;
        }

        $alarm = [
            'id' => uniqid('alarm_'),
            'type' => $type,
            'data' => $data,
            'created_at' => date('Y-m-d H:i:s'),
            'acknowledged' => false,
        ];

        $this->alarms[] = $alarm;

        Log::warning("安防报警", $alarm);

        // 发送 webhook 通知
        $this->sendWebhook($alarm);

        // TODO: 推送通知到商户
    }

    /**
     * 发送 Webhook
     */
    private function sendWebhook(array $alarm): void
    {
        $webhook = $this->config['alarm_webhook'] ?? '';
        
        if (empty($webhook)) {
            return;
        }

        try {
            Http::timeout(5)->post($webhook, $alarm);
        } catch (\Exception $e) {
            Log::error("报警 webhook 发送失败: " . $e->getMessage());
        }
    }

    /**
     * 确认报警
     */
    public function acknowledge(string $alarmId): bool
    {
        foreach ($this->alarms as &$alarm) {
            if ($alarm['id'] === $alarmId) {
                $alarm['acknowledged'] = true;
                $alarm['acknowledged_at'] = date('Y-m-d H:i:s');
                return true;
            }
        }
        return false;
    }

    /**
     * 获取未确认报警
     */
    public function getUnacknowledged(): array
    {
        return array_filter($this->alarms, fn($a) => !$a['acknowledged']);
    }

    /**
     * 报警类型
     */
    public static function typeOffline(): string { return 'device_offline'; }
    public static function typeAbnormal(): string { return 'transaction_abnormal'; }
    public static function typeMotion(): string { return 'motion_detected'; }
    public static function typeNetwork(): string { return 'network_error'; }
}