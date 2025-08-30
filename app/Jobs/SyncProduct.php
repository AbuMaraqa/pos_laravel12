<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\WooCommerceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SyncProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public int $userId) {}

    public function handle(): void
    {
        // 1) احضر subscription_id
        $subId = User::whereKey($this->userId)->value('subscription_id');
        if (!$subId) {
            Log::warning("User {$this->userId} has no subscription_id");
            return;
        }

        // 2) أنشئ السيرفس
        $woo = new WooCommerceService((int) $subId);

        // 3) لف على الصفحات
        $page = 1;

        do {
            $items = $woo->getProducts([
                'per_page' => 100,
                'page'     => $page,
            ])['data'];

            foreach ($items as $productData) {
                // ارسل كل منتج إلى مهمة منفصلة للمعالجة
                ProcessProduct::dispatch($productData, $this->userId);
            }

            $page++;
        } while (!empty($items) && count($items) === 100);

        Log::info("Sync coordination completed. All products dispatched for user {$this->userId}");
    }
}
