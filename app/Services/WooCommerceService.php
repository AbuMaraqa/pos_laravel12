<?php

namespace App\Services;

use App\Models\Subscription;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WooCommerceService
{
    protected Client $client;
    protected Client $wpClient;
    protected string $baseUrl;
    protected string $consumerKey;
    protected string $consumerSecret;

    public function __construct()
    {
        $user = Auth::user();
        if (!$user || !$user->subscription_id) {
            abort(403, "المستخدم غير مسجل الدخول أو لا يملك اشتراك");
        }

        $subscription = Subscription::find($user->subscription_id);

        if (!$subscription || !$subscription->consumer_key || !$subscription->consumer_secret) {
            abort(403, "لا توجد مفاتيح WooCommerce صالحة");
        }

        $this->baseUrl = env('WOOCOMMERCE_STORE_URL', 'https://veronastores.com/ar');
        $this->consumerKey = $subscription->consumer_key;
        $this->consumerSecret = $subscription->consumer_secret;

        // WooCommerce API Client
        $this->client = new Client([
            'base_uri' => $this->baseUrl . '/wp-json/wc/v3/',
            'auth' => [$this->consumerKey, $this->consumerSecret],
            'timeout' => 30.0,
            'verify' => false
        ]);

        // WordPress API Client
        $credentials = base64_encode(env('WORDPRESS_USERNAME') . ':' . env('WORDPRESS_APPLICATION_PASSWORD'));
        $this->wpClient = new Client([
            'base_uri' => $this->baseUrl . '/wp-json/wp/v2/',
            'headers' => [
                'Authorization' => 'Basic ' . $credentials
            ],
            'timeout' => 30.0,
            'verify' => false
        ]);
    }

    public function get(string $endpoint, array $query = []): array
    {
        try {
            $response = $this->client->get($endpoint, ['query' => $query]);
            $data = json_decode($response->getBody()->getContents(), true);

            // إضافة معلومات الصفحات
            $headers = $response->getHeaders();
            if (isset($headers['X-WP-Total'][0]) && isset($headers['X-WP-TotalPages'][0])) {
                return [
                    'data' => $data,
                    'total' => (int)$headers['X-WP-Total'][0],
                    'total_pages' => (int)$headers['X-WP-TotalPages'][0],
                    'headers' => $headers
                ];
            }

            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            Log::error("WooCommerce API Error: " . $e->getMessage());
            return [];
        }
    }

    public function getWithHeaders(string $endpoint, array $query = []): array
    {
        try {
            $response = $this->client->get($endpoint, ['query' => $query]);
            return [
                'body' => json_decode($response->getBody()->getContents(), true),
                'headers' => $response->getHeaders()
            ];
        } catch (\Exception $e) {
            Log::error("WooCommerce API Error: " . $e->getMessage());
            return ['body' => [], 'headers' => []];
        }
    }

    public function getProductsWithHeaders($query = [])
    {
        try {
            $response = $this->client->get('products', [
                'query' => $query,
            ]);

            return [
                'data' => json_decode($response->getBody()->getContents(), true),
                'headers' => $response->getHeaders(),
            ];
        } catch (\Exception $e) {
            Log::error("WooCommerce API Error: " . $e->getMessage());
            return ['data' => [], 'headers' => []];
        }
    }

    public function post(string $endpoint, array $data = []): array
    {
        try {
            $cleanData = $this->sanitizeData($data);

            $response = $this->client->post($endpoint, [
                'json' => $cleanData,
                'timeout' => 30.0,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('WooCommerce POST Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function put(string $endpoint, array $data = []): array
    {
        try {
            $response = $this->client->put($endpoint, [
                'json' => $data
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('WooCommerce PUT Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function delete(string $endpoint, array $data = []): array
    {
        try {
            $response = $this->client->delete($endpoint, [
                'json' => $data
            ]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('WooCommerce DELETE Error: ' . $e->getMessage());
            return [];
        }
    }

    private function sanitizeData(array $data): array
    {
        $cleanData = [];

        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                if (empty($value)) {
                    continue;
                }

                $cleanData[$key] = $this->sanitizeData($value);

                if (empty($cleanData[$key])) {
                    unset($cleanData[$key]);
                }
            } else if (is_string($value)) {
                $cleanValue = mb_convert_encoding(trim($value), 'UTF-8', 'UTF-8');

                if ($cleanValue !== '') {
                    $cleanData[$key] = $cleanValue;
                }
            } else {
                $cleanData[$key] = $value;
            }
        }

        return $cleanData;
    }

    public function getProducts(array $query = []): array
    {
        return $this->get('products', $query);
    }

    public function getCategories(array $query = []): array
    {
        $response = $this->get('products/categories', $query);
        return $response['data'] ?? $response;
    }

    public function getProductVariations($productId, $query = []): array
    {
        try {
            $response = $this->get("products/{$productId}/variations", array_merge([
                'per_page' => 100,
                'status' => 'publish'
            ], $query));

            return $response['data'] ?? $response;
        } catch (\Exception $e) {
            Log::error('Failed to get variations', [
                'productId' => $productId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function getCustomers(array $query = []): array
    {
        return $this->get('customers', $query);
    }

    public function getUserById($id)
    {
        try {
            $response = $this->wpClient->get('users/' . $id);
            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('Failed to get user: ' . $e->getMessage());
            return [];
        }
    }

    public function createOrder($query = []): array
    {
        return $this->post('orders', $query);
    }

    public function getShippingMethods(): array
    {
        $response = $this->get('shipping_methods');
        return $response['data'] ?? $response;
    }

    public function shippingZones(): array
    {
        $response = $this->get('shipping/zones');
        return $response['data'] ?? $response;
    }

    public function shippingZoneMethods($zoneId): array
    {
        $response = $this->get("shipping/zones/{$zoneId}/methods");
        return $response['data'] ?? $response;
    }

    // باقي الدوال المهمة...
    public function getOrders(array $query = []): array
    {
        return $this->get('orders', $query);
    }

    public function updateOrder($id, $query = []): array
    {
        return $this->put('orders/' . $id, $query);
    }

    public function getProductsById($id): array
    {
        return $this->get('products/' . $id);
    }

    public function deleteProductById($id): array
    {
        return $this->delete('products/' . $id);
    }

    public function updateProduct($productId, array $data): array
    {
        return $this->put("products/{$productId}", $data);
    }

    public function getVariationsByProductId($productId): array
    {
        return $this->getProductVariations($productId);
    }
}
