<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 库存更新事件 — 推送给所有客户端
 */
class StockUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public array $product,
        public int $storeId = 0,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("store.{$this->storeId}.stock")];
    }

    public function broadcastAs(): string
    {
        return 'stock.updated';
    }
}