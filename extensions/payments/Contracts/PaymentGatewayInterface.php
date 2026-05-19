<?php

namespace App\Extensions\Payments\Contracts;

use App\Extensions\Payments\PaymentRequest;
use App\Extensions\Payments\PaymentResponse;
use App\Extensions\Payments\PaymentNotify;
use App\Extensions\Payments\RefundRequest;
use App\Extensions\Payments\RefundResponse;

/**
 * 支付网关接口
 * 所有支付渠道需实现此接口
 */
interface PaymentGatewayInterface
{
    /**
     * 获取网关唯一标识
     */
    public function getCode(): string;

    /**
     * 获取网关名称
     */
    public function getName(): string;

    /**
     * 是否启用
     */
    public function isEnabled(): bool;

    /**
     * 发起支付
     * @param PaymentRequest $request
     * @return PaymentResponse
     */
    public function pay(PaymentRequest $request): PaymentResponse;

    /**
     * 查询支付状态
     * @param string $orderId 订单号
     * @return array ['status' => 'paid|unpaid|refunded', 'message' => string]
     */
    public function query(string $orderId): array;

    /**
     * 申请退款
     * @param RefundRequest $request
     * @return RefundResponse
     */
    public function refund(RefundRequest $request): RefundResponse;

    /**
     * 处理回调通知
     * @param PaymentNotify $notify
     * @return bool 是否成功处理
     */
    public function notify(PaymentNotify $notify): bool;

    /**
     * 验证签名
     * @param array $data 回调数据
     * @return bool
     */
    public function verifySignature(array $data): bool;
}