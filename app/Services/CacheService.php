<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * 缓存服务 — 统—缓存策略
 */
class CacheService
{
    /** 商品缓存过期时间：10分钟 */
    const TTL_PRODUCT = 600;

    /** 会员缓存过期时间：5分钟 */
    const TTL_CUSTOMER = 300;

    /** 配置缓存过期时间：30分钟 */
    const TTL_CONFIG = 1800;

    /**
     * 缓存商品查询结果
     */
    public static function rememberProducts(string $key, callable $callback, array $tags = ['products']): mixed
    {
        return Cache::tags($tags)->remember("products:{$key}", self::TTL_PRODUCT, $callback);
    }

    /**
     * 缓存会员查询结果
     */
    public static function rememberCustomer(string $key, callable $callback): mixed
    {
        return Cache::tags(['customers'])->remember("customer:{$key}", self::TTL_CUSTOMER, $callback);
    }

    /**
     * 缓存系统配置
     */
    public static function rememberConfig(string $key, callable $callback): mixed
    {
        return Cache::tags(['config'])->remember("config:{$key}", self::TTL_CONFIG, $callback);
    }

    /**
     * 清除商品缓存
     */
    public static function forgetProducts(?int $productId = null): void
    {
        if ($productId) {
            Cache::tags(['products'])->forget("product:{$productId}");
        }
        Cache::tags(['products'])->flush();
    }

    /**
     * 清除会员缓存
     */
    public static function forgetCustomer(?int $customerId = null): void
    {
        if ($customerId) {
            Cache::tags(['customers'])->forget("customer:{$customerId}");
        }
        Cache::tags(['customers'])->flush();
    }

    /**
     * 清除配置缓存
     */
    public static function forgetConfig(): void
    {
        Cache::tags(['config'])->flush();
    }
}