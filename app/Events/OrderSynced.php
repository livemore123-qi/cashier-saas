<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 离线订单同步完成事件
 */
class OrderSynced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public array $syncedOrders,
        public int $storeId = 0,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("store.{$this->storeId}.sync")];
    }

    public function broadcastAs(): string
    {
        return 'order.synced';
    }

    public function broadcastWith(): array
    {
        return [
            'count' => count($this->syncedOrders),
            'order_ids' => array_column($this->syncedOrders, 'id'),
        ];
    }
}