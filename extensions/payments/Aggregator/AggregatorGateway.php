<?php

namespace App\Extensions\Payments\Aggregator;

use App\Extensions\ExtensionBase;
use App\Extensions\Payments\Contracts\PaymentGatewayInterface;
use App\Extensions\Payments\PaymentRequest;
use App\Extensions\Payments\PaymentResponse;
use App\Extensions\Payments\PaymentNotify;
use App\Extensions\Payments\RefundRequest;
use App\Extensions\Payments\RefundResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 聚合支付网关
 * 支持第三方聚合支付平台（如 PayJS、优签约等）
 * 提供统一接口，方便后续扩展
 */
class AggregatorGateway extends ExtensionBase implements PaymentGatewayInterface
{
    protected string $code = 'aggregator';
    protected string $name = '聚合支付';
    protected string $type = 'payments';
    protected string $version = '1.0.0';
    protected string $description = '聚合支付（支持PayJS、优签约等第三方平台）';
    protected string $author = '收银SaaS';

    /**
     * 聚合支付配置
     */
    private array $config = [
        'api_key' => '',
        'mch_id' => '',
        'base_url' => '',
        'notify_url' => '',
    ];

    public function __construct()
    {
        $this->config = [
            'api_key' => env('AGGREGATOR_API_KEY', ''),
            'mch_id' => env('AGGREGATOR_MCH_ID', ''),
            'base_url' => env('AGGREGATOR_BASE_URL', 'https://pay.example.com/api'),
            'notify_url' => env('AGGREGATOR_NOTIFY_URL', ''),
        ];
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isEnabled(): bool
    {
        return !empty($this->config['api_key']) && !empty($this->config['mch_id']);
    }

    public function pay(PaymentRequest $request): PaymentResponse
    {
        if (!$this->isEnabled()) {
            return PaymentResponse::fail('NOT_CONFIG', '聚合支付未配置');
        }

        try {
            // 根据 extra 中的 channel 决定具体渠道
            $channel = $request->extra['channel'] ?? 'wechat';

            if ($channel === 'wechat') {
                return $this->payWechat($request);
            } elseif ($channel === 'alipay') {
                return $this->payAlipay($request);
            } else {
                return $this->payWechat($request); // 默认微信
            }
        } catch (\Exception $e) {
            Log::error("聚合支付失败", ['error' => $e->getMessage()]);
            return PaymentResponse::fail('AGGREGATOR_ERROR', $e->getMessage());
        }
    }

    /**
     * 微信支付
     */
    private function payWechat(PaymentRequest $request): PaymentResponse
    {
        $endpoint = $this->config['base_url'] . '/wechat/native';

        $params = [
            'mch_id' => $this->config['mch_id'],
            'out_trade_no' => $request->orderId,
            'total_fee' => (int)($request->amount * 100), // 分
            'body' => $request->subject,
            'notify_url' => $request->notifyUrl ?: $this->config['notify_url'],
            'spbill_create_ip' => $request->extra['client_ip'] ?? '127.0.0.1',
        ];

        $params['sign'] = $this->makeSign($params);

        $response = Http::timeout(15)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($endpoint, $params);

        $result = $response->json();

        if ($result['code'] === 0 || $result['status'] === 'success') {
            return PaymentResponse::success([
                'payUrl' => $result['code_url'] ?? $result['qrcode'] ?? '',
                'qrcode' => $result['code_url'] ?? $result['qrcode'] ?? '',
                'extra' => ['aggregator_trade_type' => 'wechat'],
            ]);
        } else {
            return PaymentResponse::fail($result['code'] ?? 'FAIL', $result['message'] ?? '聚合微信支付失败');
        }
    }

    /**
     * 支付宝支付
     */
    private function payAlipay(PaymentRequest $request): PaymentResponse
    {
        $endpoint = $this->config['base_url'] . '/alipay/precreate';

        $params = [
            'mch_id' => $this->config['mch_id'],
            'out_trade_no' => $request->orderId,
            'total_amount' => (string)$request->amount,
            'subject' => $request->subject,
            'notify_url' => $request->notifyUrl ?: $this->config['notify_url'],
        ];

        $params['sign'] = $this->makeSign($params);

        $response = Http::timeout(15)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($endpoint, $params);

        $result = $response->json();

        if ($result['code'] === 0 || $result['status'] === 'success') {
            return PaymentResponse::success([
                'payUrl' => $result['qrcode'] ?? '',
                'qrcode' => $result['qrcode'] ?? '',
                'extra' => ['aggregator_trade_type' => 'alipay'],
            ]);
        } else {
            return PaymentResponse::fail($result['code'] ?? 'FAIL', $result['message'] ?? '聚合支付宝失败');
        }
    }

    public function query(string $orderId): array
    {
        $endpoint = $this->config['base_url'] . '/query';

        $params = [
            'mch_id' => $this->config['mch_id'],
            'out_trade_no' => $orderId,
        ];
        $params['sign'] = $this->makeSign($params);

        try {
            $response = Http::timeout(10)->get($endpoint, $params);
            $result = $response->json();

            if ($result['trade_state'] === 'SUCCESS' || $result['trade_state'] === 'PAID') {
                return [
                    'status' => 'paid',
                    'transactionId' => $result['transaction_id'] ?? '',
                    'paidAt' => $result['paid_at'] ?? '',
                ];
            } else {
                return [
                    'status' => $result['trade_state'] ?? 'unpaid',
                    'message' => $result['message'] ?? '',
                ];
            }
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $endpoint = $this->config['base_url'] . '/refund';

        $params = [
            'mch_id' => $this->config['mch_id'],
            'out_trade_no' => $request->orderId,
            'out_refund_no' => $request->refundId,
            'refund_fee' => (int)($request->amount * 100),
            'refund_desc' => $request->reason,
        ];
        $params['sign'] = $this->makeSign($params);

        try {
            $response = Http::timeout(15)->post($endpoint, $params);
            $result = $response->json();

            if ($result['code'] === 0) {
                return RefundResponse::success([
                    'refundId' => $result['refund_id'] ?? '',
                    'settlementRefund' => $result['refund_fee'] ?? '',
                ]);
            } else {
                return RefundResponse::fail($result['code'] ?? 'FAIL', $result['message'] ?? '退款失败');
            }
        } catch (\Exception $e) {
            return RefundResponse::fail('REFUND_ERROR', $e->getMessage());
        }
    }

    public function notify(PaymentNotify $notify): bool
    {
        // 聚合支付回调验签
        if (!$this->verifySignature($notify->rawData)) {
            Log::warning("聚合支付回调验签失败");
            return false;
        }

        if ($notify->status === 'paid') {
            Log::info("聚合支付回调成功", [
                'orderId' => $notify->orderId,
                'transactionId' => $notify->transactionId,
            ]);
            // TODO: 更新订单状态
        }

        return true;
    }

    public function verifySignature(array $data): bool
    {
        $sign = $data['sign'] ?? '';
        unset($data['sign']);

        $expectedSign = $this->makeSign($data);

        return $sign === $expectedSign;
    }

    /**
     * 生成签名（MD5）
     */
    private function makeSign(array $data): string
    {
        ksort($data);
        $string = '';
        foreach ($data as $key => $value) {
            if ($value !== '' && $key !== 'sign' && $key !== 'sign_type') {
                $string .= "{$key}={$value}&";
            }
        }
        $string .= "key=" . $this->config['api_key'];
        return strtoupper(md5($string));
    }
}