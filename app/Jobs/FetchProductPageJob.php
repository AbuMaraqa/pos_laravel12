<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchProductPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes timeout
    public $tries = 3; // Retry up to 3 times

    private $page;
    private $perPage;

    public function __construct($page, $perPage = 100)
    {
        $this->page = $page;
        $this->perPage = $perPage;
    }

    public function handle()
    {
        try {
            $wooService = app('WooService'); // Adjust based on your service binding
            $pageProducts = [];

            // Fetch main products for this page
            $products = $wooService->getProducts([
                'per_page' => $this->perPage,
                'page' => $this->page,
                'status' => 'publish'
            ])['data'];

            foreach ($products as $product) {
                $pageProducts[] = $product;

                // Handle variations
                if ($product['type'] === 'variable' && !empty($product['variations'])) {
                    try {
                        $variations = $wooService->getProductVariations($product['id'])['data'] ?? [];

                        foreach ($variations as $variation) {
                            $variation['product_id'] = $product['id'];
                            $variation['type'] = 'variation';
                            $pageProducts[] = $variation;
                        }
                    } catch (\Exception $e) {
                        \Log::warning("Failed to fetch variations for product {$product['id']}: " . $e->getMessage());
                    }
                }
            }

            // Dispatch to storage if we have products
            if (!empty($pageProducts)) {
                event(new \App\Events\StoreProductsBatch($pageProducts, $this->page));
            }

            \Log::info("Successfully processed page {$this->page} with " . count($pageProducts) . " products");

        } catch (\Exception $e) {
            \Log::error("Failed to process page {$this->page}: " . $e->getMessage());

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception)
    {
        \Log::error("Job failed for page {$this->page} after {$this->tries} attempts", [
            'error' => $exception->getMessage(),
            'page' => $this->page
        ]);

        // You could dispatch a failure event here
        event(new \App\Events\ProductFetchPageFailed($this->page, $exception->getMessage()));
    }
}
