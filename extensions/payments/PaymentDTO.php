<?php

namespace App\Extensions\Payments;

/**
 * 支付请求对象
 */
class PaymentRequest
{
    public string $orderId;        // 订单号
    public float $amount;          // 支付金额（分）
    public string $subject;        // 订单标题
    public string $body;           // 订单描述
    public string $notifyUrl;      // 回调地址
    public string $returnUrl;      // 前端跳转地址
    public string $channel;        // 支付渠道 wechat|alipay|unionpay
    public string $tenantId;       // 租户ID
    public string $storeId;        // 门店ID
    public ?string $openid;       // 用户openid（JSAPI用）
    public ?string $authCode;     // 刷卡支付授权码（扫码付用）
    public array $extra;          // 扩展参数

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}

/**
 * 支付响应对象
 */
class PaymentResponse
{
    public bool $success;          // 是否成功
    public string $code;           // 错误码
    public string $message;       // 错误信息
    public string $payUrl;         // 支付链接（扫码付）
    public string $prepayId;      // 预支付ID（JSAPI）
    public string $qrcode;         // 二维码内容（扫码付）
    public ?string $transactionId;// 第三方交易号
    public array $extra;          // 扩展数据

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public static function success(array $data = []): self
    {
        $resp = new self($data);
        $resp->success = true;
        $resp->code = 'SUCCESS';
        $resp->message = '支付成功';
        return $resp;
    }

    public static function fail(string $code, string $message, array $extra = []): self
    {
        $resp = new self($extra);
        $resp->success = false;
        $resp->code = $code;
        $resp->message = $message;
        return $resp;
    }
}

/**
 * 支付回调通知对象
 */
class PaymentNotify
{
    public string $gateway;        // 网关标识
    public array $rawData;         // 原始数据
    public string $orderId;       // 订单号
    public float $amount;          // 支付金额
    public string $status;         // 状态 paid|refunded|closed
    public ?string $transactionId;// 第三方交易号
    public string $paidAt;         // 支付时间

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}

/**
 * 退款请求对象
 */
class RefundRequest
{
    public string $orderId;       // 订单号
    public string $refundId;     // 退款单号
    public float $amount;         // 退款金额
    public string $reason;       // 退款原因
    public ?string $transactionId;// 原交易号

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}

/**
 * 退款响应对象
 */
class RefundResponse
{
    public bool $success;
    public string $code;
    public string $message;
    public ?string $refundId;
    public ?string $refundChannel;
    public ?string $settlementRefund;
    public array $extra;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public static function success(array $data = []): self
    {
        $resp = new self($data);
        $resp->success = true;
        $resp->code = 'SUCCESS';
        return $resp;
    }

    public static function fail(string $code, string $message): self
    {
        $resp = new self();
        $resp->success = false;
        $resp->code = $code;
        $resp->message = $message;
        return $resp;
    }
}