<?php

namespace App\Enums;

/**
 * 统一错误码枚举
 *
 * 格式：{模块}{类别}{序号}
 * 1000-1999: 认证相关
 * 2000-2999: 订单相关
 * 3000-3999: 商品相关
 * 4000-4999: 库存/采购相关
 * 5000-5999: 会员相关
 * 6000-6999: 支付相关
 * 7000-7999: 系统/配置相关
 * 8000-8999: 收银机相关
 * 9000-9999: 通用错误
 */
enum ErrorCodeEnum: int
{
    // ==================== 认证 1000-1999 ====================
    case AUTH_LOGIN_FAILED       = 1001; // 用户名或密码错误
    case AUTH_TOKEN_EXPIRED      = 1002; // Token 已过期
    case AUTH_TOKEN_INVALID      = 1003; // Token 无效
    case AUTH_ACCOUNT_DISABLED   = 1004; // 账户已禁用
    case AUTH_ACCOUNT_NOT_ACTIVE = 1005; // 账户未激活
    case AUTH_PERMISSION_DENIED   = 1006; // 无权限
    case AUTH_NOT_LOGGED_IN      = 1007; // 未登录

    // ==================== 订单 2000-2999 ====================
    case ORDER_NOT_FOUND         = 2001; // 订单不存在
    case ORDER_CREATE_FAILED     = 2002; // 订单创建失败
    case ORDER_UPDATE_FAILED     = 2003; // 订单更新失败
    case ORDER_DELETE_FAILED     = 2004; // 订单删除失败
    case ORDER_PAYMENT_FAILED    = 2005; // 支付失败
    case ORDER_REFUND_FAILED     = 2006; // 退款失败
    case ORDER_CONFLICT          = 2007; // 订单冲突（同步冲突）

    // ==================== 商品 3000-3999 ====================
    case PRODUCT_NOT_FOUND       = 3001; // 商品不存在
    case PRODUCT_STOCK_INSUFFICIENT = 3002; // 库存不足
    case PRODUCT_BARCODE_EXISTS  = 3003; // 条码已存在
    case PRODUCT_CATEGORY_NOT_FOUND = 3004; // 分类不存在

    // ==================== 库存/采购 4000-4999 ====================
    case STOCK_UPDATE_FAILED     = 4001; // 库存更新失败
    case PROCUREMENT_NOT_FOUND   = 4002; // 采购单不存在

    // ==================== 会员 5000-5999 ====================
    case CUSTOMER_NOT_FOUND      = 5001; // 会员不存在
    case CUSTOMER_PHONE_EXISTS   = 5002; // 手机号已注册
    case CUSTOMER_POINTS_INSUFFICIENT = 5003; // 积分不足

    // ==================== 支付 6000-6999 ====================
    case PAYMENT_METHOD_INVALID  = 6001; // 支付方式无效
    case PAYMENT_AMOUNT_MISMATCH = 6002; // 支付金额不匹配

    // ==================== 系统/配置 7000-7999 ====================
    case SYSTEM_ERROR            = 7001; // 系统错误
    case SYSTEM_MAINTENANCE      = 7002; // 系统维护中
    case SYSTEM_RATE_LIMIT       = 7003; // 请求频繁

    // ==================== 通用 9000-9999 ====================
    case VALIDATION_ERROR        = 9001; // 表单验证失败
    case NOT_FOUND               = 9002; // 资源不存在
    case INTERNAL_ERROR          = 9003; // 内部错误
    case BAD_REQUEST             = 9004; // 请求参数错误

    public function message(): string
    {
        return match ($this) {
            self::AUTH_LOGIN_FAILED       => '用户名或密码错误',
            self::AUTH_TOKEN_EXPIRED      => '登录已过期，请重新登录',
            self::AUTH_TOKEN_INVALID      => '登录凭证无效',
            self::AUTH_ACCOUNT_DISABLED   => '该账户已被禁用',
            self::AUTH_ACCOUNT_NOT_ACTIVE => '该账户未激活',
            self::AUTH_PERMISSION_DENIED   => '没有权限执行此操作',
            self::AUTH_NOT_LOGGED_IN      => '请先登录',
            self::ORDER_NOT_FOUND         => '订单不存在',
            self::ORDER_CREATE_FAILED     => '订单创建失败',
            self::ORDER_UPDATE_FAILED     => '订单更新失败',
            self::ORDER_DELETE_FAILED     => '订单删除失败',
            self::ORDER_PAYMENT_FAILED    => '支付处理失败',
            self::ORDER_REFUND_FAILED     => '退款处理失败',
            self::ORDER_CONFLICT          => '订单已存在，请勿重复提交',
            self::PRODUCT_NOT_FOUND       => '商品不存在',
            self::PRODUCT_STOCK_INSUFFICIENT => '商品库存不足',
            self::PRODUCT_BARCODE_EXISTS  => '商品条码已存在',
            self::PRODUCT_CATEGORY_NOT_FOUND => '商品分类不存在',
            self::STOCK_UPDATE_FAILED     => '库存更新失败',
            self::PROCUREMENT_NOT_FOUND   => '采购单不存在',
            self::CUSTOMER_NOT_FOUND      => '会员不存在',
            self::CUSTOMER_PHONE_EXISTS   => '该手机号已注册',
            self::CUSTOMER_POINTS_INSUFFICIENT => '会员积分不足',
            self::PAYMENT_METHOD_INVALID  => '支付方式无效',
            self::PAYMENT_AMOUNT_MISMATCH => '支付金额不匹配',
            self::SYSTEM_ERROR            => '系统错误，请稍后重试',
            self::SYSTEM_MAINTENANCE      => '系统维护中，请稍后重试',
            self::SYSTEM_RATE_LIMIT       => '操作过于频繁，请稍后重试',
            self::VALIDATION_ERROR        => '提交的数据不正确',
            self::NOT_FOUND               => '资源不存在',
            self::INTERNAL_ERROR          => '服务器内部错误',
            self::BAD_REQUEST             => '请求参数错误',
        };
    }
}