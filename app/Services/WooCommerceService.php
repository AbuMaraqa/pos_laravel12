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

    // الدوال المفقودة المطلوبة
    public function getCustomersCount(): int
    {
        try {
            $response = $this->get('customers', ['per_page' => 1]);
            return $response['total'] ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to get customers count: ' . $e->getMessage());
            return 0;
        }
    }

    public function getProductsCount(): int
    {
        try {
            $response = $this->get('products', ['per_page' => 1]);
            return $response['total'] ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to get products count: ' . $e->getMessage());
            return 0;
        }
    }

    public function getLowStockProducts(): array
    {
        try {
            $response = $this->get('products', [
                'per_page' => 100,
                'status' => 'publish',
                'stock_status' => 'outofstock'
            ]);
            return $response['data'] ?? $response;
        } catch (\Exception $e) {
            Log::error('Failed to get low stock products: ' . $e->getMessage());
            return [];
        }
    }

    public function getVariableProductsPaginated(int $page = 1, int $perPage = 100): array
    {
        try {
            return $this->get('products', [
                'type' => 'variable',
                'status' => 'publish',
                'per_page' => $perPage,
                'page' => $page,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get variable products: ' . $e->getMessage());
            return ['data' => [], 'total_pages' => 0];
        }
    }

    public function getAllVariations(): array
    {
        $allVariations = [];

        try {
            $page = 1;
            do {
                $response = $this->get('products', [
                    'type' => 'variable',
                    'per_page' => 100,
                    'page' => $page,
                    'status' => 'publish'
                ]);

                $products = $response['data'] ?? $response;

                foreach ($products as $product) {
                    $productId = $product['id'];
                    $variations = $this->getVariationsByProductId($productId);

                    foreach ($variations as &$variation) {
                        $variation['product_id'] = $productId;
                    }

                    $allVariations = array_merge($allVariations, $variations);
                }

                $totalPages = $response['total_pages'] ?? 1;
                $page++;
            } while ($page <= $totalPages && !empty($products));

            Log::info("All variations fetched", ['total' => count($allVariations)]);
            return $allVariations;
        } catch (\Exception $e) {
            Log::error('Error fetching all variations', [
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function createUser($data): array
    {
        try {
            return $this->post('customers', $data);
        } catch (\Exception $e) {
            Log::error('Failed to create user: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getCustomerById($id): array
    {
        try {
            return $this->get('customers/' . $id);
        } catch (\Exception $e) {
            Log::error('Failed to get customer: ' . $e->getMessage());
            return [];
        }
    }

    public function updateCustomer($id, $query = []): array
    {
        try {
            return $this->put('customers/' . $id, $query);
        } catch (\Exception $e) {
            Log::error('Failed to update customer: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getOrdersById($id, $query = []): array
    {
        try {
            return $this->get('orders/' . $id, $query);
        } catch (\Exception $e) {
            Log::error('Failed to get order: ' . $e->getMessage());
            return [];
        }
    }

    public function getCustomerOrders($customerId, $query = []): array
    {
        try {
            return $this->get('orders', array_merge($query, ['customer' => $customerId]));
        } catch (\Exception $e) {
            Log::error('Failed to get customer orders: ' . $e->getMessage());
            return [];
        }
    }

    public function updateOrderStatus($id, $status): array
    {
        try {
            return $this->put("orders/{$id}", ['status' => $status]);
        } catch (\Exception $e) {
            Log::error('Failed to update order status: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getAttributes(array $query = []): array
    {
        try {
            $response = $this->get('products/attributes', $query);
            return $response['data'] ?? $response;
        } catch (\Exception $e) {
            Log::error('Failed to get attributes: ' . $e->getMessage());
            return [];
        }
    }

    public function getTerms(int $attributeId): array
    {
        try {
            return $this->get("products/attributes/{$attributeId}/terms");
        } catch (\Exception $e) {
            Log::error('Failed to get terms: ' . $e->getMessage());
            return [];
        }
    }

    // دالة helper لاستخراج headers من response
    public function getLastPageFromHeaders(): int
    {
        // يمكن استخدامها في المستقبل إذا احتجت لمعرفة آخر صفحة
        return 1;
    }

    // دالة لجلب الإحصائيات العامة
    public function getOrdersReportData(): array
    {
        try {
            return $this->get('reports/orders/totals');
        } catch (\Exception $e) {
            Log::error('Failed to get orders report: ' . $e->getMessage());
            return [];
        }
    }
}
