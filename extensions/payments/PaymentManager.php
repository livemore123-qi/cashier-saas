<?php

namespace App\Extensions\Payments;

use App\Extensions\Payments\Contracts\PaymentGatewayInterface;
use App\Extensions\Payments\WechatPay\WechatPayGateway;
use App\Extensions\Payments\Alipay\AlipayGateway;
use App\Extensions\Payments\Aggregator\AggregatorGateway;
use Illuminate\Support\Facades\Log;

/**
 * 支付网关管理器
 * 统一管理所有支付渠道
 */
class PaymentManager
{
    /**
     * 已注册的支付网关
     */
    private array $gateways = [];

    /**
     * 默认网关
     */
    private string $defaultGateway = 'wechat';

    public function __construct()
    {
        $this->registerBuiltInGateways();
    }

    /**
     * 注册内置网关
     */
    private function registerBuiltInGateways(): void
    {
        $this->registerGateway(new WechatPayGateway());
        $this->registerGateway(new AlipayGateway());
        $this->registerGateway(new AggregatorGateway());
    }

    /**
     * 注册支付网关
     */
    public function registerGateway(PaymentGatewayInterface $gateway): void
    {
        $this->gateways[$gateway->getCode()] = $gateway;
        Log::info("支付网关注册: {$gateway->getCode()} - {$gateway->getName()}");
    }

    /**
     * 获取网关
     */
    public function getGateway(string $code = null): ?PaymentGatewayInterface
    {
        $code = $code ?? $this->defaultGateway;
        return $this->gateways[$code] ?? null;
    }

    /**
     * 获取所有网关
     */
    public function getAllGateways(): array
    {
        return $this->gateways;
    }

    /**
     * 获取启用的网关
     */
    public function getEnabledGateways(): array
    {
        return array_filter($this->gateways, fn($g) => $g->isEnabled());
    }

    /**
     * 设置默认网关
     */
    public function setDefaultGateway(string $code): void
    {
        $this->defaultGateway = $code;
    }

    /**
     * 发起支付
     */
    public function pay(PaymentRequest $request, string $channel = null): PaymentResponse
    {
        $gateway = $this->getGateway($channel ?? $request->channel ?? $this->defaultGateway);
        
        if (!$gateway) {
            return PaymentResponse::fail('GATEWAY_NOT_FOUND', "支付网关不存在: {$channel}");
        }

        if (!$gateway->isEnabled()) {
            return PaymentResponse::fail('GATEWAY_DISABLED', "支付网关未启用: {$gateway->getName()}");
        }

        try {
            Log::info("发起支付", [
                'gateway' => $gateway->getCode(),
                'orderId' => $request->orderId,
                'amount' => $request->amount,
            ]);
            
            return $gateway->pay($request);
        } catch (\Exception $e) {
            Log::error("支付失败", [
                'gateway' => $gateway->getCode(),
                'orderId' => $request->orderId,
                'error' => $e->getMessage(),
            ]);
            return PaymentResponse::fail('PAY_ERROR', $e->getMessage());
        }
    }

    /**
     * 查询支付状态
     */
    public function query(string $orderId, string $channel = null): array
    {
        $gateway = $this->getGateway($channel ?? $this->defaultGateway);
        
        if (!$gateway) {
            return ['status' => 'error', 'message' => '网关不存在'];
        }

        try {
            return $gateway->query($orderId);
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * 申请退款
     */
    public function refund(RefundRequest $request, string $channel = null): RefundResponse
    {
        $gateway = $this->getGateway($channel ?? $this->defaultGateway);
        
        if (!$gateway) {
            return RefundResponse::fail('GATEWAY_NOT_FOUND', '网关不存在');
        }

        try {
            return $gateway->refund($request);
        } catch (\Exception $e) {
            Log::error("退款失败", [
                'gateway' => $gateway->getCode(),
                'orderId' => $request->orderId,
                'error' => $e->getMessage(),
            ]);
            return RefundResponse::fail('REFUND_ERROR', $e->getMessage());
        }
    }

    /**
     * 处理回调
     */
    public function handleNotify(string $gatewayCode, array $data): bool
    {
        $gateway = $this->getGateway($gatewayCode);
        
        if (!$gateway) {
            Log::warning("回调网关不存在: {$gatewayCode}");
            return false;
        }

        $notify = new PaymentNotify($data);
        return $gateway->notify($notify);
    }
}