<?php

namespace App\Http\Controllers\Api;

use App\Enums\ErrorCodeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\BaseResource;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * 数据同步接口
 * 供客户端（Electron/Flutter）增量拉取和上传数据
 */
class SyncController extends Controller
{
    /**
     * 增量获取商品
     * GET /api/v1/sync/products?since=2024-01-01T00:00:00Z
     */
    public function products(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->checkRateLimit($request, 'sync:products');

        $since = $request->get('since', '1970-01-01T00:00:00Z');

        $products = Product::where('updated_at', '>', $since)
            ->orderBy('updated_at')
            ->get(['id', 'name', 'barcode', 'price', 'category_id', 'image_url', 'updated_at']);

        $latestVersion = $products->last()?->updated_at?->toIso8601String() ?? $since;

        return BaseResource::success([
            'data'    => $products,
            'version' => $latestVersion,
        ], '同步成功');
    }

    /**
     * 增量获取会员
     * GET /api/v1/sync/customers?since=timestamp
     */
    public function customers(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->checkRateLimit($request, 'sync:customers');

        $since = $request->get('since', '1970-01-01T00:00:00Z');

        $customers = Customer::where('updated_at', '>', $since)
            ->orderBy('updated_at')
            ->get(['id', 'name', 'phone', 'points', 'updated_at']);

        $latestVersion = $customers->last()?->updated_at?->toIso8601String() ?? $since;

        return BaseResource::success([
            'data'    => $customers,
            'version' => $latestVersion,
        ], '同步成功');
    }

    /**
     * 获取系统配置
     * GET /api/v1/sync/config
     */
    public function config(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->checkRateLimit($request, 'sync:config');

        $configs = [
            'tax_rate'      => ns()->option->get('ns_tax_rate', 0),
            'currency'      => ns()->option->get('ns_currency_symbol', '¥'),
            'payment_types' => ns()->option->get('ns_payment_types', []),
            'shop_name'     => ns()->option->get('ns_store_name', '收银系统'),
            'receipt_footer'=> ns()->option->get('ns_receipt_footer', ''),
        ];

        return BaseResource::success([
            'data'    => $configs,
            'version' => now()->toIso8601String(),
        ], '同步成功');
    }

    /**
     * 批量上传离线订单
     * POST /api/v1/sync/orders
     */
    public function uploadOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->checkRateLimit($request, 'sync:upload-orders');

        $request->validate([
            'orders' => 'required|array|max:50',
            'orders.*.items'       => 'required|array',
            'orders.*.total'       => 'required|numeric',
            'orders.*.payment'     => 'required|string',
            'orders.*.created_at'  => 'required|date',
        ]);

        $synced = [];
        $skipped = [];

        foreach ($request->orders as $order) {
            // 检查是否已存在（避免重复同步）
            $exists = \App\Models\Order::where('sync_id', $order['sync_id'] ?? null)
                ->orWhere(function ($q) use ($order) {
                    $q->where('total', $order['total'])
                      ->where('created_at', $order['created_at']);
                })->exists();

            if ($exists) {
                $skipped[] = $order['sync_id'] ?? $order['created_at'];
                continue;
            }

            // 创建订单（实际逻辑根据项目订单结构调整）
            $created = \App\Models\Order::create([
                'sync_id'     => $order['sync_id'] ?? null,
                'total'       => $order['total'],
                'discount'    => $order['discount'] ?? 0,
                'payment_status' => 'paid',
                'customer_id' => $order['customer_id'] ?? null,
                'payment_type' => $order['payment'],
                'created_at'  => $order['created_at'],
            ]);

            $synced[] = $created->id;

            // 触发订单创建事件
            \App\Events\OrderCreated::dispatch($created->toArray());
        }

        return BaseResource::success([
            'synced'  => $synced,
            'skipped' => $skipped,
            'total'   => count($synced) + count($skipped),
        ], count($synced) > 0 ? '同步成功' : '无新订单需同步');
    }

    /**
     * 限流检查
     */
    private function checkRateLimit(Request $request, string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, ErrorCodeEnum::SYSTEM_RATE_LIMIT->message());
        }
        RateLimiter::hit($key, 60);
    }
}