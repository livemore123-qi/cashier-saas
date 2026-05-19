<?php

namespace App\Extensions\Payments\WechatPay;

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
 * 微信支付网关
 */
class WechatPayGateway extends ExtensionBase implements PaymentGatewayInterface
{
    protected string $code = 'wechat';
    protected string $name = '微信支付';
    protected string $type = 'payments';
    protected string $version = '1.0.0';
    protected string $description = '微信支付 Native/JSAPI/小程序';
    protected string $author = '收银SaaS';

    /**
     * 微信支付配置
     */
    private array $config = [
        'appid' => '',
        'mchid' => '',
        'api_key' => '',
        'cert_path' => '',
        'key_path' => '',
        'notify_url' => '',
    ];

    public function __construct()
    {
        $this->config = [
            'appid' => env('WECHAT_PAY_APPID', ''),
            'mchid' => env('WECHAT_PAY_MCHID', ''),
            'api_key' => env('WECHAT_PAY_API_KEY', ''),
            'cert_path' => env('WECHAT_PAY_CERT_PATH', ''),
            'key_path' => env('WECHAT_PAY_KEY_PATH', ''),
            'notify_url' => env('WECHAT_PAY_NOTIFY_URL', ''),
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
        return !empty($this->config['appid']) && !empty($this->config['mchid']);
    }

    /**
     * 发起支付
     * Native: 返回支付二维码链接
     * JSAPI: 返回预支付ID
     */
    public function pay(PaymentRequest $request): PaymentResponse
    {
        if (!$this->isEnabled()) {
            return PaymentResponse::fail('NOT_CONFIG', '微信支付未配置');
        }

        try {
            // 根据支付类型选择不同的接口
            $payType = $request->extra['pay_type'] ?? 'NATIVE';
            
            if ($payType === 'JSAPI' && !empty($request->openid)) {
                return $this->jsapiPay($request);
            } elseif ($payType === 'NATIVE') {
                return $this->nativePay($request);
            } elseif ($payType === 'APP') {
                return $this->appPay($request);
            } else {
                return $this->nativePay($request);
            }
        } catch (\Exception $e) {
            Log::error("微信支付失败", ['error' => $e->getMessage()]);
            return PaymentResponse::fail('WECHAT_ERROR', $e->getMessage());
        }
    }

    /**
     * Native 扫码支付
     */
    private function nativePay(PaymentRequest $request): PaymentResponse
    {
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        
        $params = [
            'appid' => $this->config['appid'],
            'mch_id' => $this->config['mchid'],
            'nonce_str' => $this->generateNonceStr(),
            'body' => $request->subject,
            'out_trade_no' => $request->orderId,
            'total_fee' => (int)($request->amount * 100), // 分
            'spbill_create_ip' => $request->extra['client_ip'] ?? '127.0.0.1',
            'notify_url' => $request->notifyUrl ?: $this->config['notify_url'],
            'trade_type' => 'NATIVE',
        ];

        $params['sign'] = $this->makeSign($params);

        $xml = $this->arrayToXml($params);
        
        $response = Http::withHeaders(['Content-Type' => 'text/xml'])
            ->timeout(10)
            ->withOptions(['verify' => false])
            ->withBody($xml, 'text/xml')
            ->post($url);

        $result = $this->xmlToArray($response->body());

        if ($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'SUCCESS') {
            return PaymentResponse::success([
                'code' => $result['code_url'],
                'payUrl' => $result['code_url'],
                'qrcode' => $result['code_url'],
                'prepayId' => $result['prepay_id'] ?? '',
                'transactionId' => '',
                'extra' => ['wechat_trade_type' => 'NATIVE'],
            ]);
        } else {
            return PaymentResponse::fail(
                $result['err_code'] ?? 'FAIL',
                $result['err_code_des'] ?? $result['return_msg'] ?? '微信支付失败'
            );
        }
    }

    /**
     * JSAPI 支付
     */
    private function jsapiPay(PaymentRequest $request): PaymentResponse
    {
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        
        $params = [
            'appid' => $this->config['appid'],
            'mch_id' => $this->config['mchid'],
            'nonce_str' => $this->generateNonceStr(),
            'body' => $request->subject,
            'out_trade_no' => $request->orderId,
            'total_fee' => (int)($request->amount * 100),
            'spbill_create_ip' => $request->extra['client_ip'] ?? '127.0.0.1',
            'notify_url' => $request->notifyUrl ?: $this->config['notify_url'],
            'trade_type' => 'JSAPI',
            'openid' => $request->openid,
        ];

        $params['sign'] = $this->makeSign($params);

        $xml = $this->arrayToXml($params);
        
        $response = Http::withHeaders(['Content-Type' => 'text/xml'])
            ->timeout(10)
            ->withOptions(['verify' => false])
            ->withBody($xml, 'text/xml')
            ->post($url);

        $result = $this->xmlToArray($response->body());

        if ($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'SUCCESS') {
            // JSAPI 需要前端签名
            $prepayId = $result['prepay_id'];
            $sign = $this->makeJsApiSign($prepayId);
            
            return PaymentResponse::success([
                'prepayId' => $prepayId,
                'payUrl' => '',
                'extra' => [
                    'appId' => $this->config['appid'],
                    'timeStamp' => (string)time(),
                    'nonceStr' => $this->generateNonceStr(),
                    'package' => "prepay_id={$prepayId}",
                    'signType' => 'MD5',
                    'paySign' => $sign,
                ],
            ]);
        } else {
            return PaymentResponse::fail(
                $result['err_code'] ?? 'FAIL',
                $result['err_code_des'] ?? 'JSAPI支付失败'
            );
        }
    }

    /**
     * APP 支付
     */
    private function appPay(PaymentRequest $request): PaymentResponse
    {
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        
        $params = [
            'appid' => $this->config['appid'],
            'mch_id' => $this->config['mchid'],
            'nonce_str' => $this->generateNonceStr(),
            'body' => $request->subject,
            'out_trade_no' => $request->orderId,
            'total_fee' => (int)($request->amount * 100),
            'spbill_create_ip' => $request->extra['client_ip'] ?? '127.0.0.1',
            'notify_url' => $request->notifyUrl ?: $this->config['notify_url'],
            'trade_type' => 'APP',
        ];

        $params['sign'] = $this->makeSign($params);

        $xml = $this->arrayToXml($params);
        
        $response = Http::withHeaders(['Content-Type' => 'text/xml'])
            ->timeout(10)
            ->withOptions(['verify' => false])
            ->withBody($xml, 'text/xml')
            ->post($url);

        $result = $this->xmlToArray($response->body());

        if ($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'SUCCESS') {
            $prepayId = $result['prepay_id'];
            $sign = $this->makeAppSign($prepayId);
            
            return PaymentResponse::success([
                'prepayId' => $prepayId,
                'extra' => [
                    'appid' => $this->config['appid'],
                    'partnerid' => $this->config['mchid'],
                    'prepayid' => $prepayId,
                    'package' => 'Sign=WXPay',
                    'noncestr' => $this->generateNonceStr(),
                    'timestamp' => (string)time(),
                    'sign' => $sign,
                ],
            ]);
        } else {
            return PaymentResponse::fail($result['err_code'] ?? 'FAIL', 'APP支付失败');
        }
    }

    public function query(string $orderId): array
    {
        $url = "https://api.mch.weixin.qq.com/pay/orderquery";
        
        $params = [
            'appid' => $this->config['appid'],
            'mch_id' => $this->config['mchid'],
            'out_trade_no' => $orderId,
            'nonce_str' => $this->generateNonceStr(),
        ];
        
        $params['sign'] = $this->makeSign($params);

        $response = Http::withHeaders(['Content-Type' => 'text/xml'])
            ->timeout(10)
            ->withBody($this->arrayToXml($params), 'text/xml')
            ->post($url);

        $result = $this->xmlToArray($response->body());

        if ($result['trade_state'] === 'SUCCESS') {
            return [
                'status' => 'paid',
                'transactionId' => $result['transaction_id'] ?? '',
                'paidAt' => $result['time_end'] ?? '',
            ];
        } else {
            return [
                'status' => $result['trade_state'] ?? 'unpaid',
                'message' => $result['trade_state_desc'] ?? '',
            ];
        }
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $url = "https://api.mch.weixin.qq.com/secapi/pay/refund";
        
        $params = [
            'appid' => $this->config['appid'],
            'mch_id' => $this->config['mchid'],
            'nonce_str' => $this->generateNonceStr(),
            'out_trade_no' => $request->orderId,
            'out_refund_no' => $request->refundId,
            'total_fee' => (int)($request->amount * 100),
            'refund_fee' => (int)($request->amount * 100),
            'refund_desc' => $request->reason,
        ];

        $params['sign'] = $this->makeSign($params);

        // 需要证书
        $response = Http::withHeaders(['Content-Type' => 'text/xml'])
            ->timeout(30)
            ->withOptions([
                'verify' => false,
                'cert' => $this->config['cert_path'],
                'ssl_key' => $this->config['key_path'],
            ])
            ->withBody($this->arrayToXml($params), 'text/xml')
            ->post($url);

        $result = $this->xmlToArray($response->body());

        if ($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'SUCCESS') {
            return RefundResponse::success([
                'refundId' => $result['refund_id'] ?? '',
                'refundChannel' => $result['refund_channel'] ?? '',
                'settlementRefund' => $result['settlement_refund_fee'] ?? '',
            ]);
        } else {
            return RefundResponse::fail($result['err_code'] ?? 'FAIL', $result['err_msg'] ?? '退款失败');
        }
    }

    public function notify(PaymentNotify $notify): bool
    {
        // 验证签名
        if (!$this->verifySignature($notify->rawData)) {
            Log::warning("微信支付回调签名验证失败");
            return false;
        }

        // 处理支付成功逻辑
        if ($notify->status === 'paid') {
            Log::info("微信支付回调成功", [
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
     * 生成签名
     */
    private function makeSign(array $data): string
    {
        ksort($data);
        $string = '';
        foreach ($data as $key => $value) {
            if ($value !== '' && $key !== 'sign') {
                $string .= "{$key}={$value}&";
            }
        }
        $string .= "key=" . $this->config['api_key'];
        return strtoupper(md5($string));
    }

    /**
     * JSAPI 签名
     */
    private function makeJsApiSign(string $prepayId): string
    {
        $data = [
            'appId' => $this->config['appid'],
            'timeStamp' => (string)time(),
            'nonceStr' => $this->generateNonceStr(),
            'package' => "prepay_id={$prepayId}",
            'signType' => 'MD5',
        ];
        ksort($data);
        $string = '';
        foreach ($data as $key => $value) {
            $string .= "{$key}={$value}&";
        }
        $string .= "key=" . $this->config['api_key'];
        return strtoupper(md5($string));
    }

    /**
     * APP 签名
     */
    private function makeAppSign(string $prepayId): string
    {
        $data = [
            'appid' => $this->config['appid'],
            'partnerid' => $this->config['mchid'],
            'prepayid' => $prepayId,
            'package' => 'Sign=WXPay',
            'noncestr' => $this->generateNonceStr(),
            'timestamp' => (string)time(),
        ];
        ksort($data);
        $string = '';
        foreach ($data as $key => $value) {
            $string .= "{$key}={$value}&";
        }
        $string .= "key=" . $this->config['api_key'];
        return strtoupper(md5($string));
    }

    private function generateNonceStr(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    private function arrayToXml(array $data): string
    {
        $xml = '<xml>';
        foreach ($data as $key => $value) {
            $xml .= "<{$key}><![CDATA[{$value}]]></{$key}>";
        }
        $xml .= '</xml>';
        return $xml;
    }

    private function xmlToArray(string $xml): array
    {
        $obj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $data = json_decode(json_encode($obj), true);
        return $data ?: [];
    }
}