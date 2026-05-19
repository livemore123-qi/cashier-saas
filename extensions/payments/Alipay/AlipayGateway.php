<?php

namespace App\Extensions\Payments\Alipay;

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
 * 支付宝网关
 * 支持：扫码支付（当面付）、JSAPI支付
 */
class AlipayGateway extends ExtensionBase implements PaymentGatewayInterface
{
    protected string $code = 'alipay';
    protected string $name = '支付宝';
    protected string $type = 'payments';
    protected string $version = '1.0.0';
    protected string $description = '支付宝扫码支付、JSAPI支付';
    protected string $author = '收银SaaS';

    /**
     * 支付宝配置
     */
    private array $config = [
        'app_id' => '',
        'private_key' => '',
        'alipay_public_key' => '',
        'notify_url' => '',
    ];

    private string $gatewayUrl = 'https://openapi.alipay.com/gateway.do';
    private string $format = 'JSON';
    private string $charset = 'UTF-8';
    private string $signType = 'RSA2';
    private string $version = '1.0';

    public function __construct()
    {
        $this->config = [
            'app_id' => env('ALIPAY_APP_ID', ''),
            'private_key' => env('ALIPAY_PRIVATE_KEY', ''),
            'alipay_public_key' => env('ALIPAY_PUBLIC_KEY', ''),
            'notify_url' => env('ALIPAY_NOTIFY_URL', ''),
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
        return !empty($this->config['app_id']) && !empty($this->config['private_key']);
    }

    public function pay(PaymentRequest $request): PaymentResponse
    {
        if (!$this->isEnabled()) {
            return PaymentResponse::fail('NOT_CONFIG', '支付宝未配置');
        }

        try {
            $payType = $request->extra['pay_type'] ?? 'precreate'; // 当面付扫码

            if ($payType === 'jsapi') {
                return $this->jsapiPay($request);
            } else {
                return $this->qrcodePay($request);
            }
        } catch (\Exception $e) {
            Log::error("支付宝支付失败", ['error' => $e->getMessage()]);
            return PaymentResponse::fail('ALIPAY_ERROR', $e->getMessage());
        }
    }

    /**
     * 扫码支付（当面付Precreate）
     */
    private function qrcodePay(PaymentRequest $request): PaymentResponse
    {
        $bizContent = [
            'out_trade_no' => $request->orderId,
            'total_amount' => (string)$request->amount, // 元
            'subject' => $request->subject,
            'body' => $request->body ?: $request->subject,
            'store_id' => $request->storeId ?? '',
            'timeout_express' => '5m',
        ];

        $params = $this->buildParams('alipay.trade.precreate', $bizContent);

        $response = Http::withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
            ->timeout(15)
            ->asForm()
            ->post($this->gatewayUrl, $params);

        $result = json_decode($response->body(), true);

        $responseNode = "alipay_trade_precreate_response";
        
        if (isset($result[$responseNode]['code']) && $result[$responseNode]['code'] === '10000') {
            return PaymentResponse::success([
                'payUrl' => $result[$responseNode]['qr_code'],
                'qrcode' => $result[$responseNode]['qr_code'],
                'extra' => ['wechat_trade_type' => 'precreate'],
            ]);
        } else {
            $error = $result[$responseNode] ?? $result;
            return PaymentResponse::fail(
                $error['code'] ?? 'FAIL',
                $error['msg'] ?? $error['sub_msg'] ?? '支付宝扫码失败'
            );
        }
    }

    /**
     * JSAPI支付
     */
    private function jsapiPay(PaymentRequest $request): PaymentResponse
    {
        if (empty($request->extra['buyer_logon_id'])) {
            return PaymentResponse::fail('PARAM_ERROR', 'JSAPI需要buyer_logon_id');
        }

        $bizContent = [
            'out_trade_no' => $request->orderId,
            'total_amount' => (string)$request->amount,
            'subject' => $request->subject,
            'buyer_id' => $request->extra['buyer_logon_id'],
            'timeout_express' => '5m',
        ];

        $params = $this->buildParams('alipay.trade.pay', $bizContent);

        $response = Http::withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
            ->timeout(15)
            ->asForm()
            ->post($this->gatewayUrl, $params);

        $result = json_decode($response->body(), true);
        $responseNode = "alipay_trade_pay_response";

        if (isset($result[$responseNode]['code']) && $result[$responseNode]['code'] === '10000') {
            return PaymentResponse::success([
                'transactionId' => $result[$responseNode]['trade_no'],
                'extra' => [
                    'trade_no' => $result[$responseNode]['trade_no'],
                    'buyer_logon_id' => $result[$responseNode]['buyer_logon_id'] ?? '',
                ],
            ]);
        } else {
            $error = $result[$responseNode] ?? $result;
            return PaymentResponse::fail($error['code'] ?? 'FAIL', $error['sub_msg'] ?? 'JSAPI支付失败');
        }
    }

    public function query(string $orderId): array
    {
        $bizContent = ['out_trade_no' => $orderId];
        $params = $this->buildParams('alipay.trade.query', $bizContent);

        $response = Http::timeout(10)->asForm()->post($this->gatewayUrl, $params);
        $result = json_decode($response->body(), true);
        $responseNode = "alipay_trade_query_response";

        if (isset($result[$responseNode]['trade_status']) && $result[$responseNode]['trade_status'] === 'TRADE_SUCCESS') {
            return [
                'status' => 'paid',
                'transactionId' => $result[$responseNode]['trade_no'] ?? '',
                'paidAt' => $result[$responseNode]['gmt_payment'] ?? '',
            ];
        } elseif (isset($result[$responseNode]['trade_status']) && $result[$responseNode]['trade_status'] === 'TRADE_CLOSED') {
            return ['status' => 'closed', 'message' => '交易关闭'];
        } else {
            return ['status' => 'unpaid', 'message' => $result[$responseNode]['sub_msg'] ?? '未支付'];
        }
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $bizContent = [
            'trade_no' => $request->transactionId ?? '',
            'out_trade_no' => $request->orderId,
            'refund_amount' => (string)$request->amount,
            'refund_reason' => $request->reason,
            'out_request_no' => $request->refundId,
        ];

        $params = $this->buildParams('alipay.trade.refund', $bizContent);

        $response = Http::timeout(15)->asForm()->post($this->gatewayUrl, $params);
        $result = json_decode($response->body(), true);
        $responseNode = "alipay_trade_refund_response";

        if (isset($result[$responseNode]['code']) && $result[$responseNode]['code'] === '10000') {
            return RefundResponse::success([
                'refundId' => $result[$responseNode]['trade_no'] ?? '',
                'refundChannel' => '',
                'settlementRefund' => $result[$responseNode]['refund_fee'] ?? '',
            ]);
        } else {
            $error = $result[$responseNode] ?? $result;
            return RefundResponse::fail($error['code'] ?? 'FAIL', $error['sub_msg'] ?? '退款失败');
        }
    }

    public function notify(PaymentNotify $notify): bool
    {
        // 支付宝异步通知验签
        $data = $_POST ?: json_decode(file_get_contents('php://input'), true);
        
        if (!$this->verifySignature($data)) {
            Log::warning("支付宝回调验签失败");
            return false;
        }

        if ($data['trade_status'] === 'TRADE_SUCCESS' || $data['trade_status'] === 'TRADE_FINISHED') {
            Log::info("支付宝回调成功", [
                'orderId' => $data['out_trade_no'],
                'tradeNo' => $data['trade_no'],
            ]);
            // TODO: 更新订单状态
        }

        return true;
    }

    public function verifySignature(array $data): bool
    {
        $sign = $data['sign'] ?? '';
        $signType = $data['sign_type'] ?? 'RSA2';
        
        unset($data['sign'], $data['sign_type']);
        
        ksort($data);
        $string = '';
        foreach ($data as $key => $value) {
            if ($value !== '') {
                $string .= "{$key}={$value}&";
            }
        }
        $string = rtrim($string, '&');

        $publicKey = $this->config['alipay_public_key'];
        
        // 移除 PEM 头尾
        $publicKey = str_replace([
            "-----BEGIN PUBLIC KEY-----",
            "-----END PUBLIC KEY-----",
            "\n",
            "\r",
        ], '', $publicKey);
        $publicKey = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($publicKey, 64, "\n") . "-----END PUBLIC KEY-----";

        $key = openssl_get_publickey($publicKey);
        if (!$key) {
            return false;
        }

        $result = openssl_verify($string, base64_decode($sign), $key, OPENSSL_ALGO_SHA256);
        openssl_free_key($key);

        return $result === 1;
    }

    /**
     * 构建公共参数 + 签名
     */
    private function buildParams(string $method, array $bizContent): array
    {
        $params = [
            'app_id' => $this->config['app_id'],
            'method' => $method,
            'format' => $this->format,
            'charset' => $this->charset,
            'sign_type' => $this->signType,
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => $this->version,
            'notify_url' => $this->config['notify_url'] ?: '',
            'biz_content' => json_encode($bizContent),
        ];

        $params['sign'] = $this->makeSign($params);

        return $params;
    }

    /**
     * RSA2 签名
     */
    private function makeSign(array $params): string
    {
        ksort($params);
        $string = '';
        foreach ($params as $key => $value) {
            if ($value !== '' && $key !== 'sign') {
                $string .= "{$key}=" . urlencode($value) . "&";
            }
        }
        $string = rtrim($string, '&');

        $privateKey = $this->config['private_key'];
        $privateKey = str_replace([
            "-----BEGIN RSA PRIVATE KEY-----",
            "-----END RSA PRIVATE KEY-----",
            "\n",
            "\r",
        ], '', $privateKey);
        $privateKey = "-----BEGIN RSA PRIVATE KEY-----\n" . chunk_split($privateKey, 64, "\n") . "-----END RSA PRIVATE KEY-----";

        $key = openssl_get_privatekey($privateKey);
        if (!$key) {
            throw new \Exception('无效的私钥');
        }

        openssl_sign($string, $sign, $key, OPENSSL_ALGO_SHA256);
        openssl_free_key($key);

        return base64_encode($sign);
    }
}