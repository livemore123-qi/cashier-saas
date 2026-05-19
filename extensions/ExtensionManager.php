<?php

namespace App\Extensions;

use App\Extensions\Payments\PaymentManager;
use App\Extensions\安防\SecurityExtension;
use App\Extensions\network\NetworkExtension;
use App\Extensions\openclaw\OpenClawExtension;
use App\Extensions\CLI\CLIExtension;
use Illuminate\Support\Facades\Log;

/**
 * 扩展模块管理器
 * 统一管理所有扩展模块的加载、配置、状态
 */
class ExtensionManager
{
    /**
     * 已注册的扩展
     */
    private array $extensions = [];

    /**
     * 扩展配置缓存
     */
    private array $config = [];

    public function __construct()
    {
        $this->loadExtensions();
    }

    /**
     * 加载所有扩展
     */
    private function loadExtensions(): void
    {
        // 支付模块（始终加载）
        $this->extensions['payments'] = [
            'manager' => new PaymentManager(),
            'enabled' => true,
        ];

        // 安防模块
        if (class_exists(SecurityExtension::class)) {
            $this->extensions['安防'] = [
                'instance' => new SecurityExtension(),
                'enabled' => env('EXTENSION_SECURITY_ENABLED', false),
            ];
        }

        // 网络监控模块
        if (class_exists(NetworkExtension::class)) {
            $this->extensions['network'] = [
                'instance' => new NetworkExtension(),
                'enabled' => env('EXTENSION_NETWORK_ENABLED', false),
            ];
        }

        // OpenClaw 集成模块
        if (class_exists(OpenClawExtension::class)) {
            $this->extensions['openclaw'] = [
                'instance' => new OpenClawExtension(),
                'enabled' => env('EXTENSION_OPENCLAW_ENABLED', false),
            ];
        }

        // CLI 模块
        if (class_exists(CLIExtension::class)) {
            $this->extensions['cli'] = [
                'instance' => new CLIExtension(),
                'enabled' => true,
            ];
        }
    }

    /**
     * 获取扩展
     */
    public function getExtension(string $name): ?object
    {
        return $this->extensions[$name]['instance'] ?? null;
    }

    /**
     * 获取支付管理器
     */
    public function getPaymentManager(): ?PaymentManager
    {
        return $this->extensions['payments']['manager'] ?? null;
    }

    /**
     * 获取所有扩展列表
     */
    public function getAllExtensions(): array
    {
        $list = [];

        foreach ($this->extensions as $name => $data) {
            $list[$name] = [
                'name' => $name,
                'enabled' => $data['enabled'] ?? false,
                'has_instance' => isset($data['instance']),
                'has_manager' => isset($data['manager']),
            ];

            if (isset($data['instance'])) {
                $list[$name]['info'] = $data['instance']->getInfo();
            }
        }

        return $list;
    }

    /**
     * 获取已启用的扩展
     */
    public function getEnabledExtensions(): array
    {
        return array_filter($this->extensions, fn($e) => $e['enabled']);
    }

    /**
     * 启用扩展
     */
    public function enable(string $name): bool
    {
        if (isset($this->extensions[$name])) {
            $this->extensions[$name]['enabled'] = true;
            
            if (isset($this->extensions[$name]['instance'])) {
                $this->extensions[$name]['instance']->enable();
            }

            Log::info("扩展已启用: {$name}");
            
            return true;
        }
        return false;
    }

    /**
     * 禁用扩展
     */
    public function disable(string $name): bool
    {
        if (isset($this->extensions[$name])) {
            $this->extensions[$name]['enabled'] = false;
            
            if (isset($this->extensions[$name]['instance'])) {
                $this->extensions[$name]['instance']->disable();
            }

            Log::info("扩展已禁用: {$name}");

            return true;
        }
        return false;
    }

    /**
     * 检查扩展是否启用
     */
    public function isEnabled(string $name): bool
    {
        return $this->extensions[$name]['enabled'] ?? false;
    }

    /**
     * 初始化所有扩展
     */
    public function boot(): void
    {
        foreach ($this->extensions as $name => $data) {
            if ($data['enabled'] && isset($data['instance'])) {
                try {
                    $data['instance']->boot();
                    Log::info("扩展启动: {$name}");
                } catch (\Exception $e) {
                    Log::error("扩展启动失败: {$name}", ['error' => $e->getMessage()]);
                }
            }
        }
    }

    /**
     * 获取扩展配置
     */
    public function getConfig(string $extension, string $key = null, $default = null)
    {
        if (!isset($this->config[$extension])) {
            $this->config[$extension] = config("extensions.{$extension}", []);
        }

        if ($key === null) {
            return $this->config[$extension];
        }

        return $this->config[$extension][$key] ?? $default;
    }

    /**
     * 设置扩展配置
     */
    public function setConfig(string $extension, string $key, $value): void
    {
        if (!isset($this->config[$extension])) {
            $this->config[$extension] = [];
        }

        $this->config[$extension][$key] = $value;
    }

    /**
     * 获取扩展状态汇总
     */
    public function getStatusSummary(): array
    {
        $summary = [
            'total' => count($this->extensions),
            'enabled' => 0,
            'disabled' => 0,
            'extensions' => [],
        ];

        foreach ($this->extensions as $name => $data) {
            $status = $data['enabled'] ? 'enabled' : 'disabled';
            
            if ($data['enabled']) {
                $summary['enabled']++;
            } else {
                $summary['disabled']++;
            }

            $summary['extensions'][$name] = [
                'status' => $status,
                'type' => $data['instance']->type ?? 'unknown',
            ];
        }

        return $summary;
    }
}