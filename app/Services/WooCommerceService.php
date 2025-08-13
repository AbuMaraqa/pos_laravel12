<?php

namespace App\Services;

use App\Models\Subscription;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WooCommerceService
{
    protected Client $client;
    protected Client $wpClient;
    protected string $baseUrl;
    protected string $consumerKey;
    protected string $consumerSecret;

    // إعدادات الأداء
    private $requestTimeout = 30;
    private $maxRetries = 3;
    private $cacheMinutes = 5;

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

        // WooCommerce API Client مع تحسينات الأداء
        $this->client = new Client([
            'base_uri' => $this->baseUrl . '/wp-json/wc/v3/',
            'auth' => [$this->consumerKey, $this->consumerSecret],
            'timeout' => $this->requestTimeout,
            'connect_timeout' => 10,
            'verify' => false,
            'http_errors' => false, // للتحكم الأفضل في الأخطاء
            'headers' => [
                'User-Agent' => 'POS-System/1.0',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]
        ]);

        // WordPress API Client with Basic Auth
        $credentials = base64_encode(env('WORDPRESS_USERNAME') . ':' . env('WORDPRESS_APPLICATION_PASSWORD'));
        $this->wpClient = new Client([
            'base_uri' => $this->baseUrl . '/wp-json/wp/v2/',
            'headers' => [
                'Authorization' => 'Basic ' . $credentials,
                'User-Agent' => 'POS-System/1.0'
            ],
            'timeout' => $this->requestTimeout,
            'verify' => false
        ]);
    }

    // 📍 طلب محسن مع إعادة المحاولة والتخزين المؤقت
    public function get(string $endpoint, array $query = []): array
    {
        $cacheKey = 'woo_' . md5($endpoint . serialize($query));

        // محاولة الحصول من الكاش أولاً
        if (!empty($query['no_cache']) || env('APP_ENV') !== 'production') {
            // تجاهل الكاش في التطوير أو عند طلب البيانات الفورية
            unset($query['no_cache']);
        } else {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            try {
                $response = $this->client->get($endpoint, ['query' => $query]);
                $statusCode = $response->getStatusCode();

                if ($statusCode >= 200 && $statusCode < 300) {
                    $data = json_decode($response->getBody()->getContents(), true);

                    // إضافة معلومات الصفحات إذا كانت موجودة في الهيدر
                    $headers = $response->getHeaders();
                    if (isset($headers['X-WP-Total'][0]) && isset($headers['X-WP-TotalPages'][0])) {
                        $result = [
                            'data' => $data,
                            'total' => (int)$headers['X-WP-Total'][0],
                            'total_pages' => (int)$headers['X-WP-TotalPages'][0]
                        ];
                    } else {
                        $result = $data;
                    }

                    // حفظ في الكاش
                    Cache::put($cacheKey, $result, now()->addMinutes($this->cacheMinutes));
                    return $result;
                } else {
                    throw new \Exception("HTTP Error: " . $statusCode);
                }

            } catch (\Exception $e) {
                $attempts++;
                $lastException = $e;

                if ($attempts < $this->maxRetries) {
                    // انتظار متزايد بين المحاولات
                    sleep($attempts);
                    Log::warning("Retrying request to {$endpoint}, attempt {$attempts}: " . $e->getMessage());
                }
            }
        }

        // إذا فشلت كل المحاولات
        Log::error("Failed to get data from {$endpoint} after {$this->maxRetries} attempts", [
            'error' => $lastException->getMessage(),
            'query' => $query
        ]);

        throw new \Exception("فشل في الاتصال مع WooCommerce بعد {$this->maxRetries} محاولات: " . $lastException->getMessage());
    }

    // 📍 طلب محسن للبيانات مع Headers
    public function getWithHeaders(string $endpoint, array $query = []): array
    {
        try {
            $response = $this->client->get($endpoint, ['query' => $query]);
            return [
                'body' => json_decode($response->getBody()->getContents(), true),
                'headers' => $response->getHeaders(),
                'status_code' => $response->getStatusCode()
            ];
        } catch (\Exception $e) {
            Log::error("Error in getWithHeaders for {$endpoint}: " . $e->getMessage());
            throw $e;
        }
    }

    public function getProductsWithHeaders($query = [])
    {
        return $this->getWithHeaders('products', $query);
    }

    // 📍 طلب PUT محسن
    public function put(string $endpoint, array $data = []): array
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            try {
                $cleanData = $this->sanitizeData($data);

                $response = $this->client->put($endpoint, [
                    'json' => $cleanData,
                    'timeout' => $this->requestTimeout
                ]);

                if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                    $result = json_decode($response->getBody()->getContents(), true);

                    // مسح الكاش المرتبط
                    $this->clearRelatedCache($endpoint);

                    return $result;
                } else {
                    throw new \Exception("HTTP Error: " . $response->getStatusCode());
                }

            } catch (\Exception $e) {
                $attempts++;
                $lastException = $e;

                if ($attempts < $this->maxRetries) {
                    sleep($attempts);
                    Log::warning("Retrying PUT request to {$endpoint}, attempt {$attempts}: " . $e->getMessage());
                }
            }
        }

        Log::error('WooCommerce PUT Error after retries: ' . $lastException->getMessage(), [
            'endpoint' => $endpoint,
            'data' => $data
        ]);
        throw $lastException;
    }

    public function delete(string $endpoint, array $data = []): array
    {
        try {
            $response = $this->client->delete($endpoint, [
                'json' => $data,
                'timeout' => $this->requestTimeout
            ]);

            // مسح الكاش المرتبط
            $this->clearRelatedCache($endpoint);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('WooCommerce DELETE Error: ' . $e->getMessage());
            throw $e;
        }
    }

    // 📍 طلب POST محسن مع معالجة أفضل للأخطاء
    public function post(string $endpoint, array $data = []): array
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            try {
                $cleanData = $this->sanitizeData($data);

                Log::info('Sending POST request to WooCommerce API', [
                    'endpoint' => $endpoint,
                    'data_size' => strlen(json_encode($cleanData)),
                    'attempt' => $attempts + 1
                ]);

                $response = $this->client->post($endpoint, [
                    'json' => $cleanData,
                    'timeout' => $this->requestTimeout,
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json'
                    ]
                ]);

                $statusCode = $response->getStatusCode();

                if ($statusCode >= 200 && $statusCode < 300) {
                    $result = json_decode($response->getBody()->getContents(), true);

                    Log::info('Successful response from WooCommerce API', [
                        'endpoint' => $endpoint,
                        'status_code' => $statusCode
                    ]);

                    // مسح الكاش المرتبط
                    $this->clearRelatedCache($endpoint);

                    return $result;
                } else {
                    $responseBody = $response->getBody()->getContents();
                    throw new \Exception("HTTP Error {$statusCode}: " . $this->formatApiError($responseBody, $statusCode));
                }

            } catch (\Exception $e) {
                $attempts++;
                $lastException = $e;

                if ($attempts < $this->maxRetries) {
                    sleep($attempts);
                    Log::warning("Retrying POST request to {$endpoint}, attempt {$attempts}: " . $e->getMessage());
                } else {
                    $errorMessage = $e->getMessage();
                    $responseBody = '';

                    if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->getResponse()) {
                        $responseBody = $e->getResponse()->getBody()->getContents();
                    }

                    Log::error('WooCommerce API POST Error after all retries', [
                        'endpoint' => $endpoint,
                        'attempts' => $attempts,
                        'sent_data' => $cleanData,
                        'error' => $errorMessage,
                        'response' => $responseBody
                    ]);

                    throw new \Exception('خطأ في حفظ المنتج: ' . $this->formatApiError($responseBody, 'unknown'));
                }
            }
        }

        throw $lastException;
    }

    // 📍 مسح الكاش المرتبط بالنقطة النهائية
    private function clearRelatedCache(string $endpoint): void
    {
        $patterns = [
            'woo_*products*',
            'woo_*categories*',
            'woo_*variations*'
        ];

        foreach ($patterns as $pattern) {
            if (strpos($endpoint, 'products') !== false) {
                Cache::flush(); // مسح شامل للكاش المرتبط بالمنتجات
                break;
            }
        }
    }

    /**
     * تنظيف البيانات قبل إرسالها إلى واجهة برمجة التطبيقات
     */
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

    /**
     * تنسيق خطأ واجهة برمجة التطبيقات بشكل مقروء
     */
    private function formatApiError(string $responseBody, $statusCode): string
    {
        try {
            $error = json_decode($responseBody, true);

            if (isset($error['message'])) {
                return "({$statusCode}) " . $error['message'];
            }

            if (isset($error['code']) && isset($error['data']['status'])) {
                return "({$error['data']['status']}) {$error['message']}";
            }

            return "خطأ غير معروف (كود: {$statusCode})";
        } catch (\Exception $e) {
            return "خطأ في الاتصال مع الخادم (كود: {$statusCode})";
        }
    }

    // 📍 دوال المنتجات المحسنة
    public function getProducts(array $query = []): array
    {
        // إضافة معاملات افتراضية لتحسين الأداء
        $defaultQuery = [
            'per_page' => 50,
            'page' => 1,
            'status' => 'publish'
        ];

        $query = array_merge($defaultQuery, $query);
        return $this->get('products', $query);
    }

    // 📍 دالة محسنة لجلب المنتجات بكميات كبيرة
    public function getProductsBatch(int $page = 1, int $perPage = 100): array
    {
        $query = [
            'per_page' => min($perPage, 100), // حد أقصى 100
            'page' => $page,
            'status' => 'publish',
            'no_cache' => true // تجاهل الكاش للبيانات الحية
        ];

        return $this->get('products', $query);
    }

    public function deleteProductById($id): array
    {
        return $this->delete('products/' . $id);
    }

    public function getProductsById($id): array
    {
        return $this->get('products/' . $id);
    }

    public function getCategories(array $query = []): array
    {
        $defaultQuery = [
            'per_page' => 100,
            'hide_empty' => false
        ];

        $query = array_merge($defaultQuery, $query);
        $result = $this->get('products/categories', $query);

        return $result['data'] ?? $result;
    }

    // 📍 دالة محسنة لجلب المتغيرات
    public function getVariationsByProductId($productId): array
    {
        try {
            $cacheKey = "variations_product_{$productId}";

            // محاولة الحصول من الكاش
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $response = $this->get("products/{$productId}/variations", [
                'per_page' => 100,
                'status' => 'publish'
            ]);

            $variations = is_array($response) && isset($response['data']) ? $response['data'] : $response;

            // حفظ في الكاش لمدة قصيرة
            Cache::put($cacheKey, $variations, now()->addMinutes(2));

            Log::info('Retrieved variations for product', [
                'productId' => $productId,
                'count' => count($variations)
            ]);

            return $variations;
        } catch (\Exception $e) {
            Log::error('Failed to get variations', [
                'productId' => $productId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function getOrders(array $query = []): array
    {
        return $this->get('orders', $query);
    }

    public function getOrdersById($id, $query = []): array
    {
        return $this->get('orders/' . $id, $query);
    }

    public function updateOrder($id, $query = []): array
    {
        return $this->put('orders/' . $id, $query);
    }

    public function getCustomerOrders($customerId, $query = []): array
    {
        return $this->get('orders', array_merge($query, ['customer' => $customerId]));
    }

    public function updateCustomer($id, $query = []): array
    {
        return $this->put('customers/' . $id, $query);
    }

    public function createOrder($query = []): array
    {
        return $this->post('orders', $query);
    }

    public function getAttributes(array $query = []): array
    {
        $response = $this->get('products/attributes', $query);
        return $response['data'] ?? [];
    }

    // 📍 دالة محسنة للحصول على متغير بواسطة ID
    public function getVariationById($id): array
    {
        try {
            $cacheKey = "variation_{$id}";

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            // البحث عن المنتج الأب الذي يحتوي على هذا المتغير
            $products = $this->getProducts(['per_page' => 50, 'type' => 'variable']);
            $productsData = $products['data'] ?? $products;

            foreach ($productsData as $product) {
                if (isset($product['variations']) && is_array($product['variations']) && in_array($id, $product['variations'])) {
                    $productId = $product['id'];
                    $variation = $this->get("products/{$productId}/variations/{$id}");

                    // حفظ في الكاش
                    Cache::put($cacheKey, $variation, now()->addMinutes(2));

                    return $variation;
                }
            }

            throw new \Exception("Parent product not found for variation ID: {$id}");
        } catch (\Exception $e) {
            Log::error('Failed to get variation', [
                'variationId' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getTerms(int $attributeId): array
    {
        return $this->get("products/attributes/{$attributeId}/terms");
    }

    public function getAttributesWithTerms(array $query = []): array
    {
        $attributes = $this->getAttributes($query);
        foreach ($attributes as &$attribute) {
            $attribute['terms'] = $this->getTermsForAttribute($attribute['id'], $query);
        }
        return $attributes;
    }

    public function getAttributeBySlug($slug): ?array
    {
        $response = $this->get('products/attributes', [
            'slug' => $slug,
        ]);

        return !empty($response) ? $response[0] : null;
    }

    public function deleteAttribute($id): array
    {
        return $this->delete('products/attributes/' . $id);
    }

    public function getTermsForAttribute($attributeId, array $query = []): array
    {
        try {
            $response = $this->get("products/attributes/{$attributeId}/terms", $query);
            $terms = $response['data'] ?? $response;

            $filteredTerms = $this->filterUniqueTerms($terms, 'en');

            Log::info('Retrieved and filtered terms for attribute', [
                'attribute_id' => $attributeId,
                'original_terms_count' => count($terms),
                'filtered_terms_count' => count($filteredTerms)
            ]);

            return $filteredTerms;
        } catch (\Exception $e) {
            Log::error("Failed to get terms for attribute {$attributeId}: " . $e->getMessage());
            return [];
        }
    }

    public function getAttributeById($id): array
    {
        $response = $this->get("products/attributes/{$id}");
        return $response['data'] ?? [];
    }

    public function getAttribute($attributeId): array
    {
        try {
            $response = $this->get("products/attributes/{$attributeId}");
            return $response;
        } catch (\Exception $e) {
            Log::error("Failed to get attribute {$attributeId}: " . $e->getMessage());
            return [];
        }
    }

    public function getTermsByAttributeId($attributeId, array $query = []): array
    {
        try {
            $response = $this->get("products/attributes/{$attributeId}/terms", $query);
            $terms = $response['data'] ?? $response;

            $filteredTerms = $this->filterUniqueTerms($terms, 'en');

            return $filteredTerms;
        } catch (\Exception $e) {
            Log::error("Failed to get terms by attribute ID {$attributeId}: " . $e->getMessage());
            return [];
        }
    }

    public function getTermsForAttributeByLang($attributeId, string $lang = 'en', array $query = []): array
    {
        try {
            $query['lang'] = $lang;

            $response = $this->get("products/attributes/{$attributeId}/terms", $query);
            $terms = $response['data'] ?? $response;

            $langSpecificTerms = array_filter($terms, function($term) use ($lang) {
                return ($term['lang'] ?? 'en') === $lang;
            });

            if (empty($langSpecificTerms)) {
                $langSpecificTerms = $this->filterUniqueTerms($terms, $lang);
            }

            usort($langSpecificTerms, function($a, $b) {
                $nameA = $a['name'] ?? '';
                $nameB = $b['name'] ?? '';

                if (is_numeric($nameA) && is_numeric($nameB)) {
                    return (int)$nameA - (int)$nameB;
                }

                return strcmp($nameA, $nameB);
            });

            Log::info('Retrieved terms for specific language', [
                'attribute_id' => $attributeId,
                'language' => $lang,
                'terms_count' => count($langSpecificTerms)
            ]);

            return array_values($langSpecificTerms);
        } catch (\Exception $e) {
            Log::error("Failed to get terms for attribute {$attributeId} in language {$lang}: " . $e->getMessage());
            return [];
        }
    }

    private function getPreferredLanguage(): string
    {
        $locale = app()->getLocale();

        $langMap = [
            'ar' => 'ar',
            'en' => 'en',
            'he' => 'he'
        ];

        return $langMap[$locale] ?? 'en';
    }

    public function deleteTerm($attributeId, $termId, array $query = []): array
    {
        return $this->delete("products/attributes/{$attributeId}/terms/{$termId}", $query);
    }

    public function updateProduct($productId, array $data, array $queryParams = []): array
    {
        try {
            Log::info('Updating product', [
                'productId' => $productId,
                'data' => $data
            ]);

            return $this->put("products/{$productId}", $data);
        } catch (\Exception $e) {
            Log::error('WooCommerce PUT Error: ' . $e->getMessage());
            throw $e;
        }
    }

    // باقي الدوال مع تحسينات مشابهة...
    public function updateProductAttributes($productId, array $data): array
    {
        try {
            Log::info('Updating product attributes', [
                'productId' => $productId,
                'attributes_count' => count($data['attributes'] ?? []),
                'variations_count' => count($data['variations'] ?? [])
            ]);

            if (isset($data['attributes']) && !empty($data['attributes'])) {
                $productData = ['attributes' => $data['attributes']];
                $attributeResponse = $this->put("products/{$productId}", $productData);

                Log::info('Product attributes updated', [
                    'response' => $attributeResponse
                ]);
            }

            $variationUpdates = [
                'create' => [],
                'update' => []
            ];
            $errors = [];

            if (isset($data['variations']) && !empty($data['variations'])) {
                foreach ($data['variations'] as $index => $variation) {
                    try {
                        $variation['manage_stock'] = true;

                        if (
                            empty($variation['regular_price']) ||
                            !isset($variation['stock_quantity']) ||
                            empty($variation['sku'])
                        ) {
                            $missing = [];
                            if (empty($variation['regular_price'])) $missing[] = 'regular_price';
                            if (!isset($variation['stock_quantity'])) $missing[] = 'stock_quantity';
                            if (empty($variation['sku'])) $missing[] = 'sku';

                            Log::warning('Incomplete variation data', [
                                'missing_fields' => $missing,
                                'variation_index' => $index
                            ]);

                            $errors[] = "Variation at index {$index} has missing required fields: " . implode(', ', $missing);
                            continue;
                        }

                        $cleanVariation = $this->sanitizeVariationData($variation);

                        if (isset($cleanVariation['id']) && !empty($cleanVariation['id'])) {
                            $variationUpdates['update'][] = $cleanVariation;
                        } else {
                            $variationUpdates['create'][] = $cleanVariation;
                        }
                    } catch (\Exception $ve) {
                        Log::error('Failed to process variation', [
                            'variation_index' => $index,
                            'error' => $ve->getMessage()
                        ]);
                        $errors[] = "Failed to process variation at index {$index}: " . $ve->getMessage();
                    }
                }

                $batchResults = null;
                if (!empty($variationUpdates['update']) || !empty($variationUpdates['create'])) {
                    try {
                        $batchResults = $this->batchUpdateVariations($productId, $variationUpdates);

                        Log::info('Batch variation update completed', [
                            'updated_count' => count($batchResults['update'] ?? []),
                            'created_count' => count($batchResults['create'] ?? [])
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Batch variation update failed', [
                            'error' => $e->getMessage()
                        ]);
                        $errors[] = "Batch update failed: " . $e->getMessage();
                    }
                }
            }

            if (!empty($errors)) {
                return [
                    'success' => $batchResults !== null,
                    'message' => 'Product attributes updated with some errors. ' . count($errors) . ' variations failed.',
                    'updated_count' => count($batchResults['update'] ?? []),
                    'created_count' => count($batchResults['create'] ?? []),
                    'errors' => $errors,
                    'batch_results' => $batchResults
                ];
            }

            return [
                'success' => true,
                'message' => "Product attributes and variations updated successfully via batch API.",
                'updated_count' => count($batchResults['update'] ?? []),
                'created_count' => count($batchResults['create'] ?? []),
                'batch_results' => $batchResults
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update product attributes', [
                'productId' => $productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    // 📍 رفع الصور محسن
    public function uploadImage($file)
    {
        try {
            if (!$file || !$file->isValid()) {
                Log::error('Invalid file: ' . ($file ? $file->getClientOriginalName() : 'No file'));
                throw new \Exception('الملف غير صالح');
            }

            Log::info('Starting file upload: ' . $file->getClientOriginalName());

            $fileName = $file->getClientOriginalName();
            $fileContent = file_get_contents($file->getRealPath());

            $url = $this->baseUrl . '/wp-json/wp/v2/media';

            $ch = curl_init();

            $credentials = base64_encode(env('WORDPRESS_USERNAME') . ':' . env('WORDPRESS_APPLICATION_PASSWORD'));

            $headers = [
                'Authorization: Basic ' . $credentials,
                'Content-Disposition: form-data; name="file"; filename="' . $fileName . '"',
                'Content-Type: ' . $file->getMimeType(),
            ];

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $fileContent,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => $this->requestTimeout,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                Log::error('CURL Error: ' . $error);
                throw new \Exception($error);
            }

            curl_close($ch);

            if ($httpCode !== 201) {
                Log::error('Upload failed with status: ' . $httpCode);
                Log::error('Response: ' . $response);
                throw new \Exception('فشل في رفع الصورة. رمز الحالة: ' . $httpCode);
            }

            $responseData = json_decode($response, true);

            if (!isset($responseData['id'])) {
                Log::error('Invalid response data: ' . json_encode($responseData));
                throw new \Exception('استجابة غير صالحة من الخادم');
            }

            Log::info('Upload successful: ' . json_encode($responseData));

            return [
                'id' => $responseData['id'],
                'src' => $responseData['source_url'] ?? '',
                'name' => $fileName
            ];
        } catch (\Exception $e) {
            Log::error('Upload Error: ' . $e->getMessage());
            throw new \Exception('فشل في رفع الصورة: ' . $e->getMessage());
        }
    }

    public function uploadMedia($file)
    {
        return $this->uploadImage($file);
    }

    // باقي الدوال (سأكملها في التعليق التالي لتوفير المساحة)
    public function getRoles()
    {
        try {
            $response = $this->wpClient->get('roles');
            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('WP API Error getting roles: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // 📍 دوال متنوعة محسنة
    public function getProduct($id): array
    {
        return $this->get('products/' . $id);
    }

    public function shippingMethods()
    {
        return $this->get('shipping_methods');
    }

    public function shippingZones()
    {
        $result = $this->get('shipping/zones');
        return $result['data'] ?? $result;
    }

    public function shippingZoneMethods($zoneId)
    {
        $result = $this->get("shipping/zones/{$zoneId}/methods");
        return $result['data'] ?? $result;
    }

    public function getProductVariations($productId, $query = []): array
    {
        $defaultQuery = [
            'per_page' => 100,
            'status' => 'publish'
        ];

        $query = array_merge($defaultQuery, $query);
        $result = $this->get("products/{$productId}/variations", $query);
        return $result['data'] ?? $result;
    }

    public function getShippingMethods(): array
    {
        return $this->get('shipping_methods');
    }

    public function getCustomers(array $query = []): array
    {
        $defaultQuery = [
            'per_page' => 100,
            'orderby' => 'registered_date',
            'order' => 'desc'
        ];

        $query = array_merge($defaultQuery, $query);
        return $this->get('customers', $query);
    }

    public function getUserById($id)
    {
        $response = $this->wpClient->get('users/' . $id);
        return json_decode($response->getBody()->getContents(), true);
    }

    public function createUser($data)
    {
        return $this->post('customers', $data);
    }

    // 📍 دوال مساعدة
    private function filterUniqueTerms(array $terms, string $preferredLang = 'en'): array
    {
        if (empty($terms)) {
            return [];
        }

        Log::info('بدء فلترة المصطلحات:', [
            'total_terms' => count($terms),
            'preferred_lang' => $preferredLang,
            'sample_terms' => array_slice($terms, 0, 5)
        ]);

        $uniqueTerms = [];
        $seenNames = [];
        $duplicatesLog = [];

        foreach ($terms as $index => $term) {
            $termName = $term['name'] ?? '';
            $termId = $term['id'] ?? null;
            $termLang = $term['lang'] ?? $preferredLang;

            if (empty($termName)) {
                Log::warning("تجاهل مصطلح بدون اسم:", ['term' => $term]);
                continue;
            }

            if (!isset($seenNames[$termName])) {
                $uniqueTerms[] = $term;
                $seenNames[$termName] = [
                    'id' => $termId,
                    'lang' => $termLang,
                    'index' => count($uniqueTerms) - 1
                ];
            } else {
                $existingInfo = $seenNames[$termName];

                $duplicatesLog[] = [
                    'name' => $termName,
                    'existing_id' => $existingInfo['id'],
                    'existing_lang' => $existingInfo['lang'],
                    'new_id' => $termId,
                    'new_lang' => $termLang
                ];

                if ($termLang === $preferredLang && $existingInfo['lang'] !== $preferredLang) {
                    $uniqueTerms[$existingInfo['index']] = $term;
                    $seenNames[$termName] = [
                        'id' => $termId,
                        'lang' => $termLang,
                        'index' => $existingInfo['index']
                    ];
                }
            }
        }

        usort($uniqueTerms, function($a, $b) {
            $nameA = $a['name'] ?? '';
            $nameB = $b['name'] ?? '';

            if (is_numeric($nameA) && is_numeric($nameB)) {
                return (int)$nameA - (int)$nameB;
            }

            return strcmp($nameA, $nameB);
        });

        Log::info('انتهاء فلترة المصطلحات:', [
            'original_count' => count($terms),
            'filtered_count' => count($uniqueTerms),
            'removed_count' => count($terms) - count($uniqueTerms),
            'duplicates_found' => count($duplicatesLog)
        ]);

        return array_values($uniqueTerms);
    }

    private function sanitizeVariationData(array $variation): array
    {
        $cleanData = [];

        if (isset($variation['regular_price'])) {
            $cleanData['regular_price'] = (string)$variation['regular_price'];
        }

        $stockQuantity = null;
        if (isset($variation['stock_quantity'])) {
            if (is_numeric($variation['stock_quantity'])) {
                $stockQuantity = (int)$variation['stock_quantity'];
            } else if ($variation['stock_quantity'] === '') {
                $stockQuantity = null;
            } else if (is_null($variation['stock_quantity'])) {
                $stockQuantity = null;
            } else {
                $stockQuantity = $variation['stock_quantity'];
            }
        }

        $cleanData['manage_stock'] = true;

        if (is_null($stockQuantity)) {
            $stockQuantity = 0;
        }

        $cleanData['stock_quantity'] = $stockQuantity;

        $stockStatus = 'instock';
        if ($cleanData['stock_quantity'] <= 0) {
            $stockStatus = 'outofstock';
        }
        $cleanData['stock_status'] = $stockStatus;

        if (isset($variation['sku'])) {
            $cleanData['sku'] = (string)$variation['sku'];
        }

        if (isset($variation['id'])) {
            $cleanData['id'] = (int)$variation['id'];
        }

        if (!empty($variation['sale_price'])) {
            $cleanData['sale_price'] = (string)$variation['sale_price'];
        }

        if (!empty($variation['description'])) {
            $cleanData['description'] = (string)$variation['description'];
        }

        if (isset($variation['attributes']) && is_array($variation['attributes'])) {
            $cleanData['attributes'] = [];
            foreach ($variation['attributes'] as $attribute) {
                if (isset($attribute['id']) && isset($attribute['option'])) {
                    $cleanData['attributes'][] = [
                        'id' => (int)$attribute['id'],
                        'option' => (string)$attribute['option']
                    ];
                }
            }
        }

        if (isset($variation['image']) && !empty($variation['image'])) {
            if (is_string($variation['image'])) {
                $cleanData['image'] = ['src' => $variation['image']];
            } else if (is_array($variation['image']) && isset($variation['image']['src'])) {
                $cleanData['image'] = ['src' => $variation['image']['src']];
            }
        }

        return $cleanData;
    }

    public function batchUpdateVariations($productId, array $data): array
    {
        try {
            Log::info('Sending batch variation update payload.', [
                'productId' => $productId,
                'payload_data' => $data
            ]);

            return $this->post("products/{$productId}/variations/batch", $data);
        } catch (\Exception $e) {
            Log::error('Failed to process batch variation update', [
                'productId' => $productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    // 📍 دوال إضافية مع تحسينات الأداء
    public function getAllVariations(): array
    {
        $allVariations = [];

        try {
            $page = 1;
            do {
                $response = $this->get('products', [
                    'type' => 'variable',
                    'per_page' => 50, // تقليل الحجم لتحسين الأداء
                    'page' => $page,
                    'status' => 'publish'
                ]);

                $products = is_array($response) && isset($response['data']) ? $response['data'] : $response;

                foreach ($products as $product) {
                    $productId = $product['id'];

                    try {
                        $variations = $this->getVariationsByProductId($productId);

                        foreach ($variations as &$variation) {
                            $variation['product_id'] = $productId;
                        }

                        $allVariations = array_merge($allVariations, $variations);
                    } catch (\Exception $e) {
                        Log::warning("Failed to get variations for product {$productId}: " . $e->getMessage());
                        continue;
                    }
                }

                $totalPages = $response['total_pages'] ?? 1;
                $page++;
            } while ($page <= $totalPages);

            Log::info("✅ All variations fetched", ['total' => count($allVariations)]);
            return $allVariations;
        } catch (\Exception $e) {
            Log::error('❌ Error fetching all variations', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    public function getVariableProductsPaginated(int $page = 1, int $perPage = 50): array
    {
        return $this->get('products', [
            'type' => 'variable',
            'status' => 'publish',
            'per_page' => $perPage,
            'page' => $page,
        ]);
    }

    // 📍 دوال الإحصائيات والمراقبة
    public function getProductsCount()
    {
        try {
            $response = $this->get('products', [
                'per_page' => 1,
                'no_cache' => true
            ]);

            return is_array($response) && isset($response['total'])
                ? $response['total']
                : count($response);
        } catch (\Exception $e) {
            Log::error('Error getting products count: ' . $e->getMessage());
            return 0;
        }
    }

    public function getCustomersCount()
    {
        try {
            $response = $this->get('customers', [
                'per_page' => 1,
                'no_cache' => true
            ]);

            return is_array($response) && isset($response['total'])
                ? $response['total']
                : count($response);
        } catch (\Exception $e) {
            Log::error('Error getting customers count: ' . $e->getMessage());
            return 0;
        }
    }

    public function getLowStockProducts()
    {
        try {
            $response = $this->get('products', [
                'per_page' => 100,
                'status' => 'publish',
                'low_in_stock' => true
            ]);

            return $response['data'] ?? $response;
        } catch (\Exception $e) {
            Log::error('Error getting low stock products: ' . $e->getMessage());
            return [];
        }
    }

    // 📍 دوال الطلبات والتقارير
    public function getOrdersReportData()
    {
        try {
            return $this->get('reports/orders/totals');
        } catch (\Exception $e) {
            Log::error('Error getting orders report: ' . $e->getMessage());
            return [];
        }
    }

    // 📍 دوال متنوعة أخرى
    public function addCategory($name, $parentId, $description)
    {
        return $this->post('products/categories', [
            'name' => $name,
            'parent' => $parentId,
            'description' => $description
        ]);
    }

    public function getUsers()
    {
        try {
            $response = $this->wpClient->get('users');
            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('Error getting users: ' . $e->getMessage());
            return [];
        }
    }

    public function updateUser($id, $query = [])
    {
        try {
            $response = $this->wpClient->put("users/{$id}", [
                'json' => $query,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            Log::info('Update user success', $result);
            return $result;
        } catch (\Exception $e) {
            Log::error('Update user failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function getCustomerById($id)
    {
        try {
            $response = $this->client->get('customers/' . $id);
            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('Error getting customer by ID: ' . $e->getMessage());
            return null;
        }
    }

    public function updateOrderStatus($id, $status)
    {
        return $this->put("orders/{$id}", [
            'status' => $status
        ]);
    }

    // 📍 دوال MRBP (إذا كانت مطلوبة)
    public function getMrbpRoleById($id)
    {
        try {
            $product = $this->getProductsById($id);

            if (!$product || empty($product['meta_data'])) {
                return '';
            }

            foreach ($product['meta_data'] as $meta) {
                if ($meta['key'] == 'mrbp_role') {
                    return json_encode($meta['value']);
                }
            }

            return '';
        } catch (\Exception $e) {
            Log::error('Error getting MRBP role: ' . $e->getMessage());
            return '';
        }
    }

    public function getMrbpData($productId): ?array
    {
        try {
            $product = $this->getProduct($productId);

            if (!$product || empty($product['meta_data'])) {
                return null;
            }

            foreach ($product['meta_data'] as $meta) {
                if ($meta['key'] === 'mrbp_role') {
                    $mrbpData = [];
                    foreach ($meta['value'] as $roleData) {
                        $role = array_key_first($roleData);
                        if (!$role) continue;

                        $mrbpData[$role] = [
                            'regularPrice' => $roleData['mrbp_regular_price'] ?? '',
                            'salePrice' => $roleData['mrbp_sale_price'] ?? ''
                        ];
                    }
                    return $mrbpData;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error getting MRBP data: ' . $e->getMessage());
            return null;
        }
    }

    public function updateMrbpData($productId, array $mrbpData): array
    {
        $formattedData = [];
        foreach ($mrbpData as $role => $prices) {
            $formattedData[] = [
                $role => ucfirst($role),
                'mrbp_regular_price' => $prices['regularPrice'] ?? '',
                'mrbp_sale_price' => $prices['salePrice'] ?? '',
                'mrbp_make_empty_price' => ''
            ];
        }

        return $this->put("products/{$productId}", [
            'meta_data' => [
                [
                    'key' => 'mrbp_role',
                    'value' => $formattedData
                ]
            ]
        ]);
    }

    // 📍 دوال التشحيل
    public function shippingZoneById($zoneId)
    {
        return $this->get("shipping/zones/{$zoneId}");
    }

    public function updateShippingMethod($methodId, $settings)
    {
        return $this->put("shipping/methods/{$methodId}", [
            'settings' => $settings
        ]);
    }

    public function updateShippingZoneMethod($zoneId, $methodId, $settings)
    {
        $result = $this->put("shipping/zones/{$zoneId}/methods/{$methodId}", [
            'settings' => $settings
        ]);
        return $result['data'] ?? $result;
    }

    // 📍 دوال أخرى مهمة
    public function syncVariations($productId, array $variations): array
    {
        try {
            $product = $this->getProduct($productId);

            if (!$product) {
                return [
                    'success' => false,
                    'message' => 'Product not found',
                    'updated' => 0,
                    'created' => 0,
                    'deleted' => 0
                ];
            }

            $existingVariations = $this->getVariationsByProductId($productId);
            $existingVariationsMap = [];

            foreach ($existingVariations as $variation) {
                if (isset($variation['id'])) {
                    $existingVariationsMap[$variation['id']] = $variation;
                }
            }

            $batchData = [
                'create' => [],
                'update' => [],
                'delete' => []
            ];

            $results = [
                'updated' => 0,
                'created' => 0,
                'deleted' => 0
            ];

            foreach ($variations as $variation) {
                try {
                    $variationData = $this->sanitizeVariationData($variation);

                    if (isset($variationData['id']) && !empty($variationData['id'])) {
                        $batchData['update'][] = $variationData;

                        if (isset($existingVariationsMap[$variationData['id']])) {
                            unset($existingVariationsMap[$variationData['id']]);
                        }
                    } else {
                        $batchData['create'][] = $variationData;
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to prepare variation for batch operation', [
                        'variation' => $variation,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            foreach ($existingVariationsMap as $id => $variation) {
                $batchData['delete'][] = $id;
            }

            Log::info('Prepared batch operation for variations', [
                'productId' => $productId,
                'create_count' => count($batchData['create']),
                'update_count' => count($batchData['update']),
                'delete_count' => count($batchData['delete'])
            ]);

            if (!empty($batchData['create']) || !empty($batchData['update']) || !empty($batchData['delete'])) {
                $batchResult = $this->batchUpdateVariations($productId, $batchData);

                $results['created'] = count($batchResult['create'] ?? []);
                $results['updated'] = count($batchResult['update'] ?? []);
                $results['deleted'] = count($batchResult['delete'] ?? []);

                Log::info('Batch operation completed', [
                    'created' => $results['created'],
                    'updated' => $results['updated'],
                    'deleted' => $results['deleted']
                ]);
            } else {
                Log::info('No variations to process in batch operation');
            }

            return [
                'success' => true,
                'message' => sprintf(
                    'Variations synced successfully via batch API: updated %d, created %d, deleted %d',
                    $results['updated'],
                    $results['created'],
                    $results['deleted']
                ),
                'updated' => $results['updated'],
                'created' => $results['created'],
                'deleted' => $results['deleted']
            ];
        } catch (\Exception $e) {
            Log::error('Failed to sync variations', [
                'productId' => $productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'updated' => 0,
                'created' => 0,
                'deleted' => 0
            ];
        }
    }

    public function updateProductFeatured($productId, $featured)
    {
        return $this->put("products/{$productId}", [
            'featured' => $featured
        ]);
    }

    public function updateProductVariation($productId, $variationId, $query = [])
    {
        return $this->put("products/{$productId}/variations/{$variationId}", $query);
    }

    public function updateMainProductPrice($productId, $price)
    {
        return $this->put("products/{$productId}", [
            'regular_price' => $price
        ]);
    }

    public function updateMainSalePrice($productId, $price)
    {
        return $this->put("products/{$productId}", [
            'sale_price' => $price
        ]);
    }

    public function updateProductStatus($productId, $status)
    {
        return $this->put("products/{$productId}", [
            'status' => $status
        ]);
    }

    public function getProductTranslations($productId)
    {
        return $this->get('products/' . $productId . '/translations');
    }

    // 📍 دوال إضافية للدعم
    public function getLastPageFromHeaders(): int
    {
        return 1; // سيتم تحديثها حسب الحاجة
    }

    public function batchUpdateProducts(array $data): array
    {
        try {
            Log::info('Sending batch update to WooCommerce API', [
                'create_count' => count($data['create'] ?? []),
                'update_count' => count($data['update'] ?? []),
                'delete_count' => count($data['delete'] ?? [])
            ]);

            return $this->post('products/batch', $data);
        } catch (\Exception $e) {
            Log::error('Failed to process batch update', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    // 📍 دوال النظافة والصيانة
    public function clearCache(): void
    {
        Cache::flush();
        Log::info('WooCommerce service cache cleared');
    }

    public function getServiceStatus(): array
    {
        try {
            // اختبار الاتصال
            $testResponse = $this->get('products', ['per_page' => 1]);

            return [
                'status' => 'connected',
                'message' => 'WooCommerce API متصل بنجاح',
                'last_tested' => now()->toDateTimeString(),
                'products_available' => isset($testResponse['total']) ? $testResponse['total'] : 'unknown'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'خطأ في الاتصال: ' . $e->getMessage(),
                'last_tested' => now()->toDateTimeString()
            ];
        }
    }
}

