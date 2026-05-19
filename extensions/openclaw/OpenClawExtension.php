<?php

namespace App\Extensions\openclaw;

use App\Extensions\ExtensionBase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenClaw 集成模块
 * 
 * 功能：
 * - 每店铺一个 OpenClaw Agent
 * - AI 智能客服（解答顾客问题）
 * - 自动补货建议（根据库存）
 * - AI 经营分析报告
 * - 与店铺管理系统打通
 */
class OpenClawExtension extends ExtensionBase
{
    protected string $code = 'openclaw';
    protected string $name = 'OpenClaw 集成';
    protected string $type = 'openclaw';
    protected string $version = '1.0.0';
    protected string $description = 'OpenClaw Agent 集成：AI客服、智能补货、经营分析';
    protected string $author = '收银SaaS';

    protected array $config = [
        'enabled' => false,
        'gateway_url' => '',           // OpenClaw Gateway 地址
        'api_key' => '',               // API 密钥
        'agent_id' => '',              // Agent ID（每个店铺一个）
        'auto_order' => false,         // 自动补货
        'auto_order_threshold' => 20,  // 库存低于此值触发补货建议
    ];

    private ?OpenClawClient $client = null;

    public function __construct()
    {
        $this->config = [
            'enabled' => env('OPENCLAW_ENABLED', false),
            'gateway_url' => env('OPENCLAW_GATEWAY_URL', 'http://localhost:8080'),
            'api_key' => env('OPENCLAW_API_KEY', ''),
            'agent_id' => env('OPENCLAW_AGENT_ID', ''),
            'auto_order' => env('OPENCLAW_AUTO_ORDER', false),
            'auto_order_threshold' => env('OPENCLAW_AUTO_ORDER_THRESHOLD', 20),
        ];
    }

    public function register(): void
    {
        if ($this->isEnabled()) {
            $this->client = new OpenClawClient(
                $this->config['gateway_url'],
                $this->config['api_key']
            );
        }
    }

    public function isEnabled(): bool
    {
        return !empty($this->config['gateway_url']) && !empty($this->config['api_key']);
    }

    /**
     * 获取 OpenClaw Client
     */
    public function getClient(): ?OpenClawClient
    {
        return $this->client;
    }

    /**
     * 发送消息给 Agent
     */
    public function sendMessage(string $message, array $context = []): array
    {
        if (!$this->client) {
            return ['error' => 'OpenClaw 未启用'];
        }

        try {
            return $this->client->sendMessage($this->config['agent_id'], $message, $context);
        } catch (\Exception $e) {
            Log::error("OpenClaw 消息发送失败: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * 获取 AI 客服回复
     */
    public function getCustomerServiceReply(string $question, array $customerInfo = []): string
    {
        $context = array_merge($customerInfo, [
            'type' => 'customer_service',
            'store_id' => $this->config['agent_id'],
        ]);

        $response = $this->sendMessage($question, $context);

        return $response['message'] ?? $response['error'] ?? '抱歉，AI客服暂时无法响应';
    }

    /**
     * 获取库存补货建议
     */
    public function getRestockRecommendations(array $inventory): array
    {
        if (!$this->client) {
            return [];
        }

        try {
            $prompt = "根据以下库存数据，生成补货建议（返回JSON数组，每项包含product_name, current_stock, recommended_quantity, urgency）：\n\n";
            $prompt .= json_encode($inventory, JSON_UNESCAPED_UNICODE);

            $response = $this->client->generate(
                $this->config['agent_id'],
                $prompt,
                ['task' => 'restock_recommendation']
            );

            $recommendations = json_decode($response['text'] ?? '[]', true);
            
            return is_array($recommendations) ? $recommendations : [];

        } catch (\Exception $e) {
            Log::error("补货建议生成失败: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 生成经营分析报告
     */
    public function generateBusinessReport(array $salesData, array $inventoryData): string
    {
        if (!$this->client) {
            return '';
        }

        try {
            $prompt = "作为店铺经营顾问，分析以下数据并生成报告（包含：销售趋势、问题诊断、改进建议）：\n\n";
            $prompt .= "销售数据: " . json_encode($salesData, JSON_UNESCAPED_UNICODE) . "\n\n";
            $prompt .= "库存数据: " . json_encode($inventoryData, JSON_UNESCAPED_UNICODE);

            $response = $this->client->generate(
                $this->config['agent_id'],
                $prompt,
                ['task' => 'business_report']
            );

            return $response['text'] ?? '';

        } catch (\Exception $e) {
            Log::error("经营报告生成失败: " . $e->getMessage());
            return '';
        }
    }

    /**
     * 检查 Agent 状态
     */
    public function checkAgentStatus(): array
    {
        if (!$this->client) {
            return ['status' => 'disabled', 'online' => false];
        }

        try {
            $response = $this->client->status($this->config['agent_id']);
            
            return [
                'status' => $response['status'] ?? 'unknown',
                'online' => $response['online'] ?? false,
                'last_seen' => $response['last_seen'] ?? null,
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'online' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 执行 Agent 命令
     */
    public function executeCommand(string $command, array $args = []): array
    {
        if (!$this->client) {
            return ['error' => 'OpenClaw 未启用'];
        }

        try {
            return $this->client->execute(
                $this->config['agent_id'],
                $command,
                $args
            );
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * 注册 OpenClaw 到店铺
     */
    public function registerAgent(string $storeName, string $storeId): array
    {
        // TODO: 调用 OpenClaw 注册接口
        return [
            'agent_id' => 'agent_' . $storeId . '_' . time(),
            'status' => 'registered',
        ];
    }
}

/**
 * OpenClaw API Client
 */
class OpenClawClient
{
    private string $gatewayUrl;
    private string $apiKey;
    private int $timeout = 30;

    public function __construct(string $gatewayUrl, string $apiKey)
    {
        $this->gatewayUrl = rtrim($gatewayUrl, '/');
        $this->apiKey = $apiKey;
    }

    /**
     * 发送消息
     */
    public function sendMessage(string $agentId, string $message, array $context = []): array
    {
        $url = "{$this->gatewayUrl}/api/agents/{$agentId}/messages";
        
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post($url, [
                'message' => $message,
                'context' => $context,
            ]);

        if ($response->successful()) {
            return $response->json();
        } else {
            throw new \Exception("OpenClaw API 错误: " . $response->status());
        }
    }

    /**
     * 生成内容
     */
    public function generate(string $agentId, string $prompt, array $options = []): array
    {
        $url = "{$this->gatewayUrl}/api/agents/{$agentId}/generate";
        
        $response = Http::timeout(60)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post($url, array_merge([
                'prompt' => $prompt,
            ], $options));

        if ($response->successful()) {
            return $response->json();
        } else {
            throw new \Exception("OpenClaw 生成失败: " . $response->status());
        }
    }

    /**
     * 获取 Agent 状态
     */
    public function status(string $agentId): array
    {
        $url = "{$this->gatewayUrl}/api/agents/{$agentId}/status";
        
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])
            ->get($url);

        return $response->successful() ? $response->json() : [];
    }

    /**
     * 执行命令
     */
    public function execute(string $agentId, string $command, array $args = []): array
    {
        $url = "{$this->gatewayUrl}/api/agents/{$agentId}/execute";
        
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post($url, [
                'command' => $command,
                'args' => $args,
            ]);

        return $response->successful() ? $response->json() : ['error' => '请求失败'];
    }
}