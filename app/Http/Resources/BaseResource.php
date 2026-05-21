<?php

namespace App\Http\Resources;

use App\Enums\ErrorCodeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 统一 API 响应基类
 *
 * 成功响应:
 * { "code": 0, "message": "ok", "data": {...} }
 *
 * 错误响应:
 * { "code": 1001, "message": "用户名或密码错误", "data": null }
 */
class BaseResource extends JsonResource
{
    /**
     * 成功响应
     */
    public static function success($data = null, string $message = '操作成功'): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'code'    => 0,
            'message' => $message,
            'data'    => $data,
        ]);
    }

    /**
     * 成功响应（带分页）
     */
    public static function paginated($data, string $message = '查询成功'): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'code'    => 0,
            'message' => $message,
            'data'    => $data->items(),
            'meta'    => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
            ],
        ]);
    }

    /**
     * 错误响应
     */
    public static function error(
        ErrorCodeEnum|int $code,
        string $message = '',
        $data = null,
        int $httpStatus = 200
    ): \Illuminate\Http\JsonResponse {
        if ($code instanceof ErrorCodeEnum) {
            $message = $message ?: $code->message();
            $code = $code->value;
        }

        return response()->json([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $httpStatus);
    }

    /**
     * 验证失败响应
     */
    public static function validationError($errors, string $message = ''): \Illuminate\Http\JsonResponse
    {
        return self::error(
            ErrorCodeEnum::VALIDATION_ERROR,
            $message ?: ErrorCodeEnum::VALIDATION_ERROR->message(),
            $errors
        );
    }

    /**
     * @inheritDoc
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}