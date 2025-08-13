<?php

namespace App\Services;

use App\Models\Subscription;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Curl;
use Illuminate\Support\Facades\Log; // تأكد من استيراد Log

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
            'timeout' => 10.0,
            'verify' => false // تجاهل شهادة SSL في بيئة التطوير
        ]);

        // WordPress API Client with Basic Auth
        $credentials = base64_encode(env('WORDPRESS_USERNAME') . ':' . env('WORDPRESS_APPLICATION_PASSWORD'));
        $this->wpClient = new Client([
            'base_uri' => $this->baseUrl . '/wp-json/wp/v2/',
            'headers' => [
                'Authorization' => 'Basic ' . $credentials
            ],
            'timeout' => 30.0,
            'verify' => false // تجاهل شهادة SSL في بيئة التطوير
        ]);
    }

    public function get(string $endpoint, array $query = []): array
    {
        $response = $this->client->get($endpoint, ['query' => $query]);
        $data = json_decode($response->getBody()->getContents(), true);

        // إضافة معلومات الصفحات إذا كانت موجودة في الهيدر
        $headers = $response->getHeaders();
        if (isset($headers['X-WP-Total'][0]) && isset($headers['X-WP-TotalPages'][0])) {
            return [
                'data' => $data,
                'total' => (int)$headers['X-WP-Total'][0],
                'total_pages' => (int)$headers['X-WP-TotalPages'][0]
            ];
        }

        return $data;
    }

    public function getWithHeaders(string $endpoint, array $query = []): array
    {
        $response = $this->client->get($endpoint, ['query' => $query]);
        return [
            'body' => json_decode($response->getBody()->getContents(), true),
            'headers' => $response->getHeaders()
        ];
    }

    public function getProductsWithHeaders($query = [])
    {
        $response = $this->client->get('products', [
            'query' => $query,
        ]);

        return [
            'data' => json_decode($response->getBody()->getContents(), true),
            'headers' => $response->getHeaders(),
        ];
    }

    public function put(string $endpoint, array $data = []): array
    {
        try {
            $response = $this->client->put($endpoint, [
                'json' => $data
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            logger()->error('WooCommerce PUT Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function delete(string $endpoint, array $data = []): array
    {
        $response = $this->client->delete($endpoint, [
            'json' => $data
        ]);
        return json_decode($response->getBody()->getContents(), true);
    }

    public function post(string $endpoint, array $data = []): array
    {
        try {
            // تنظيف البيانات للتأكد من صلاحيتها
            $cleanData = $this->sanitizeData($data);

            logger()->info('Sending POST request to WooCommerce API', [
                'endpoint' => $endpoint,
                'data_size' => strlen(json_encode($cleanData))
            ]);

            // Log the actual data being sent after sanitization
            logger()->debug('Data being sent to WooCommerce API after sanitization', [
                'endpoint' => $endpoint,
                'data' => $cleanData
            ]);

            $response = $this->client->post($endpoint, [
                'json' => $cleanData,
                'timeout' => 30.0, // زيادة مهلة الانتظار
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            logger()->info('Successful response from WooCommerce API', [
                'endpoint' => $endpoint,
                'status_code' => $response->getStatusCode()
            ]);

            return $result;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorMessage = $e->getMessage();
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 'unknown';
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : '';

            // Log the cleaned data along with the error
            logger()->error('WooCommerce API POST Error', [
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'sent_data' => $cleanData, // Log data that caused the error
                'error' => $errorMessage,
                'response' => $responseBody
            ]);

            // رمي خطأ أكثر وضوحا
            throw new \Exception('خطأ في حفظ المنتج: ' . $this->formatApiError($responseBody, $statusCode));
        }
    }

    /**
     * تنظيف البيانات قبل إرسالها إلى واجهة برمجة التطبيقات
     */
    private function sanitizeData(array $data): array
    {
        $cleanData = [];

        // تنظيف البيانات بشكل متكرر
        foreach ($data as $key => $value) {
            // إذا كانت قيمة فارغة، نتخطاها
            if ($value === null || $value === '') {
                continue;
            }

            // إذا كانت مصفوفة، نطبق التنظيف بشكل متكرر
            if (is_array($value)) {
                // إذا كانت مصفوفة فارغة، نتخطاها
                if (empty($value)) {
                    continue;
                }

                $cleanData[$key] = $this->sanitizeData($value);

                // إذا أصبحت المصفوفة فارغة بعد التنظيف، نتخطاها
                if (empty($cleanData[$key])) {
                    unset($cleanData[$key]);
                }
            } else if (is_string($value)) {
                // تنظيف النص وتحويله إلى UTF-8
                $cleanValue = mb_convert_encoding(trim($value), 'UTF-8', 'UTF-8');

                // إذا كانت سلسلة فارغة بعد التنظيف، نتخطاها
                if ($cleanValue !== '') {
                    $cleanData[$key] = $cleanValue;
                }
            } else {
                // قيم أخرى (رقمية، بولينية، إلخ)
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

    public function getProducts(array $query = []): array
    {
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
        return $this->get('products/categories', $query)['data'];
    }

    public function getVariationsByProductId($productId): array
    {
        try {
            $response = $this->get("products/{$productId}/variations", [
                'per_page' => 100, // Get up to 100 variations
                'status' => 'publish'
            ]);

            // التحقق مما إذا كانت الاستجابة مصفوفة ارتباطية تحتوي على مفتاح 'data'
            // هذا يتعامل مع الحالة التي تقوم فيها دالة get() بتغليف البيانات الفعلية
            $variations = is_array($response) && isset($response['data']) ? $response['data'] : $response;

            // تسجيل الاستجابة لأغراض التصحيح
            logger()->info('Retrieved variations for product', [
                'productId' => $productId,
                'count' => count($variations)
            ]);

            return $variations;
        } catch (\Exception $e) {
            logger()->error('Failed to get variations', [
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

    public function getVariationById($id): array
    {
        // For variations, we need to find the parent product first by searching all products for this variation
        try {
            // Search for parent product containing this variation
            $products = $this->getProducts(['per_page' => 50]);

            foreach ($products as $product) {
                if (isset($product['variations']) && is_array($product['variations']) && in_array($id, $product['variations'])) {
                    // Found the parent product
                    $productId = $product['id'];
                    // Now get the variation details
                    return $this->get("products/{$productId}/variations/{$id}");
                }
            }

            throw new \Exception("Parent product not found for variation ID: {$id}");
        } catch (\Exception $e) {
            logger()->error('Failed to get variation', [
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

            // فلترة المصطلحات لإزالة التكرارات
            $filteredTerms = $this->filterUniqueTerms($terms, 'en'); // تفضيل الإنجليزية

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

            // فلترة المصطلحات لإزالة التكرارات
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
            // إضافة معامل اللغة للاستعلام إذا كان مدعوماً
            $query['lang'] = $lang;

            $response = $this->get("products/attributes/{$attributeId}/terms", $query);
            $terms = $response['data'] ?? $response;

            // فلترة المصطلحات بناءً على اللغة المطلوبة
            $langSpecificTerms = array_filter($terms, function($term) use ($lang) {
                return ($term['lang'] ?? 'en') === $lang;
            });

            // إذا لم نجد مصطلحات باللغة المطلوبة، استخدم الفلترة العامة
            if (empty($langSpecificTerms)) {
                $langSpecificTerms = $this->filterUniqueTerms($terms, $lang);
            }

            // ترتيب المصطلحات
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
        // يمكنك تخصيص هذا حسب إعدادات موقعك
        $locale = app()->getLocale();

        // تحويل locale إلى رمز لغة WooCommerce
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
            logger()->info('Updating product', [
                'productId' => $productId,
                'data' => $data
            ]);

            return $this->put("products/{$productId}", $data);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            logger()->error('WooCommerce PUT Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateProductAttributes($productId, array $data): array
    {
        try {
            logger()->info('Updating product attributes', [
                'productId' => $productId,
                'attributes_count' => count($data['attributes'] ?? []),
                'variations_count' => count($data['variations'] ?? [])
            ]);

            // First update the product attributes
            if (isset($data['attributes']) && !empty($data['attributes'])) {
                $productData = ['attributes' => $data['attributes']];
                $attributeResponse = $this->put("products/{$productId}", $productData);

                logger()->info('Product attributes updated', [
                    'response' => $attributeResponse
                ]);
            }

            // Prepare batch update for variations
            $variationUpdates = [
                'create' => [],
                'update' => []
            ];
            $errors = [];

            if (isset($data['variations']) && !empty($data['variations'])) {
                foreach ($data['variations'] as $index => $variation) {
                    try {
                        // ✅ إجباري: تأكد من تفعيل إدارة المخزون
                        $variation['manage_stock'] = true;

                        // Validate required fields
                        if (
                            empty($variation['regular_price']) ||
                            !isset($variation['stock_quantity']) ||
                            empty($variation['sku'])
                        ) {
                            $missing = [];
                            if (empty($variation['regular_price'])) $missing[] = 'regular_price';
                            if (!isset($variation['stock_quantity'])) $missing[] = 'stock_quantity';
                            if (empty($variation['sku'])) $missing[] = 'sku';

                            logger()->warning('Incomplete variation data', [
                                'missing_fields' => $missing,
                                'variation_index' => $index
                            ]);

                            $errors[] = "Variation at index {$index} has missing required fields: " . implode(', ', $missing);
                            continue;
                        }

                        // Clean and prepare variation data
                        $cleanVariation = $this->sanitizeVariationData($variation);

                        // Add to appropriate batch operation
                        if (isset($cleanVariation['id']) && !empty($cleanVariation['id'])) {
                            $variationUpdates['update'][] = $cleanVariation;
                        } else {
                            $variationUpdates['create'][] = $cleanVariation;
                        }
                    } catch (\Exception $ve) {
                        logger()->error('Failed to process variation', [
                            'variation_index' => $index,
                            'error' => $ve->getMessage()
                        ]);
                        $errors[] = "Failed to process variation at index {$index}: " . $ve->getMessage();
                    }
                }

                // Execute batch update if there are variations to update/create
                $batchResults = null;
                if (!empty($variationUpdates['update']) || !empty($variationUpdates['create'])) {
                    try {
                        $batchResults = $this->batchUpdateVariations($productId, $variationUpdates);

                        logger()->info('Batch variation update completed', [
                            'updated_count' => count($batchResults['update'] ?? []),
                            'created_count' => count($batchResults['create'] ?? [])
                        ]);
                    } catch (\Exception $e) {
                        logger()->error('Batch variation update failed', [
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
            logger()->error('Failed to update product attributes', [
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
    public function uploadImage($file)
    {
        try {
            if (!$file || !$file->isValid()) {
                logger()->error('Invalid file: ' . ($file ? $file->getClientOriginalName() : 'No file'));
                throw new \Exception('الملف غير صالح');
            }

            logger()->info('Starting file upload: ' . $file->getClientOriginalName());

            // تجهيز البيانات
            $fileName = $file->getClientOriginalName();
            $fileContent = file_get_contents($file->getRealPath());

            // تجهيز URL
            $url = $this->baseUrl . '/wp-json/wp/v2/media';

            // إعداد CURL
            $ch = curl_init();

            // تجهيز المصادقة
            $credentials = base64_encode(env('WORDPRESS_USERNAME') . ':' . env('WORDPRESS_APPLICATION_PASSWORD'));

            // إعداد الهيدرز
            $headers = [
                'Authorization: Basic ' . $credentials,
                'Content-Disposition: form-data; name="file"; filename="' . $fileName . '"',
                'Content-Type: ' . $file->getMimeType(),
            ];

            // إعداد خيارات CURL
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $fileContent,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);

            // تنفيذ الطلب
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            // التحقق من الأخطاء
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                logger()->error('CURL Error: ' . $error);
                throw new \Exception($error);
            }

            curl_close($ch);

            // التحقق من الاستجابة
            if ($httpCode !== 201) {
                logger()->error('Upload failed with status: ' . $httpCode);
                logger()->error('Response: ' . $response);
                throw new \Exception('فشل في رفع الصورة. رمز الحالة: ' . $httpCode);
            }

            $responseData = json_decode($response, true);

            if (!isset($responseData['id'])) {
                logger()->error('Invalid response data: ' . json_encode($responseData));
                throw new \Exception('استجابة غير صالحة من الخادم');
            }

            logger()->info('Upload successful: ' . json_encode($responseData));

            return [
                'id' => $responseData['id'],
                'src' => $responseData['source_url'] ?? '',
                'name' => $fileName
            ];
        } catch (\Exception $e) {
            logger()->error('Upload Error: ' . $e->getMessage());
            throw new \Exception('فشل في رفع الصورة: ' . $e->getMessage());
        }
    }

    public function uploadMedia($file)
    {
        return $this->uploadImage($file); // نستخدم نفس دالة uploadImage لأنها تقوم بنفس المهمة
    }

    public function getRoles()
    {
        try {
            $response = $this->wpClient->get('roles');
            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            logger()->error('WP API Error getting roles: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function getMrbpRoleById($id)
    {
        $product = $this->getProductsById($id);

        if (!$product || empty($product['meta_data'])) {
            return '';
        }

        foreach ($product['meta_data'] as $meta) {
            if ($meta['key'] == 'mrbp_role') {
                // Devolvemos una representación en texto del array
                return json_encode($meta['value']);
            }
        }

        return '';
    }

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
        $response = $this->wpClient->get('users');
        return json_decode($response->getBody()->getContents(), true);
    }

    public function getUserById($id)
    {
        $response = $this->wpClient->get('users/' . $id);
        return json_decode($response->getBody()->getContents(), true);
    }

    public function updateUser($id, $query = [])
    {
        try {
            $response = $this->wpClient->put("users/{$id}", [
                'json' => $query,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            logger()->info('Update user success', $result);
            return $result;
        } catch (\Exception $e) {
            logger()->error('Update user failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function getCustomerById($id)
    {
        $response = $this->client->get('customers/' . $id);
        return json_decode($response->getBody()->getContents(), true);
    }

    public function updateOrderStatus($id, $status)
    {
        return $this->put("orders/{$id}", [
            'status' => $status
        ]);
    }

    public function getProduct($id): array
    {
        return $this->get('products/' . $id);
    }

    public function getMrbpData($productId): ?array
    {
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

                    // Formato directo
                    $mrbpData[$role] = [
                        'regularPrice' => $roleData['mrbp_regular_price'] ?? '',
                        'salePrice' => $roleData['mrbp_sale_price'] ?? ''
                    ];
                }
                return $mrbpData;
            }
        }

        return null;
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

    public function syncVariations($productId, array $variations): array
    {
        try {
            // Get the product first
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

            // Get existing variations to determine which ones to update/create/delete
            $existingVariations = $this->getVariationsByProductId($productId);
            $existingVariationsMap = [];

            foreach ($existingVariations as $variation) {
                if (isset($variation['id'])) {
                    $existingVariationsMap[$variation['id']] = $variation;
                }
            }

            // Prepare batch data
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

            // Process variations for update/create
            foreach ($variations as $variation) {
                try {
                    // تجهيز بيانات المتغيّر
                    $variationData = $this->sanitizeVariationData($variation);

                    // تحديث أو إنشاء
                    if (isset($variationData['id']) && !empty($variationData['id'])) {
                        // Add to update batch
                        $batchData['update'][] = $variationData;

                        // Remove from existingVariationsMap to track which ones should be deleted
                        if (isset($existingVariationsMap[$variationData['id']])) {
                            unset($existingVariationsMap[$variationData['id']]);
                        }
                    } else {
                        // Add to create batch
                        $batchData['create'][] = $variationData;
                    }
                } catch (\Exception $e) {
                    logger()->error('Failed to prepare variation for batch operation', [
                        'variation' => $variation,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Add remaining variations to delete batch
            foreach ($existingVariationsMap as $id => $variation) {
                $batchData['delete'][] = $id;
            }

            logger()->info('Prepared batch operation for variations', [
                'productId' => $productId,
                'create_count' => count($batchData['create']),
                'update_count' => count($batchData['update']),
                'delete_count' => count($batchData['delete'])
            ]);

            // Execute batch operation if there's anything to do
            if (!empty($batchData['create']) || !empty($batchData['update']) || !empty($batchData['delete'])) {
                $batchResult = $this->batchUpdateVariations($productId, $batchData);

                // Count results
                $results['created'] = count($batchResult['create'] ?? []);
                $results['updated'] = count($batchResult['update'] ?? []);
                $results['deleted'] = count($batchResult['delete'] ?? []);

                logger()->info('Batch operation completed', [
                    'created' => $results['created'],
                    'updated' => $results['updated'],
                    'deleted' => $results['deleted']
                ]);
            } else {
                logger()->info('No variations to process in batch operation');
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
            logger()->error('Failed to sync variations', [
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

    public function shippingMethods()
    {
        return $this->get('shipping_methods');
    }

    public function updateShippingMethod($methodId, $settings)
    {
        return $this->put("shipping/methods/{$methodId}", [
            'settings' => $settings
        ]);
    }

    public function shippingZones()
    {
        return $this->get('shipping/zones')['data'];
    }

    public function shippingZoneById($zoneId)
    {
        return $this->get("shipping/zones/{$zoneId}");
    }

    public function shippingZoneMethods($zoneId)
    {
        return $this->get("shipping/zones/{$zoneId}/methods")['data'];
    }

    public function updateShippingZoneMethod($zoneId, $methodId, $settings)
    {
        return $this->put("shipping/zones/{$zoneId}/methods/{$methodId}", [
            'settings' => $settings
        ])['data'];
    }

    public function getProductVariations($productId , $query = []): array
    {
        // جلب المتغيرات للمنتج المحدد
        return $this->get("products/{$productId}/variations", $query)['data'];
    }

    private function filterUniqueTerms(array $terms, string $preferredLang = 'en'): array
    {
        if (empty($terms)) {
            return [];
        }

        // تسجيل البيانات الأولية
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

            Log::debug("معالجة المصطلح {$index}:", [
                'id' => $termId,
                'name' => $termName,
                'lang' => $termLang
            ]);

            // إذا كان الاسم فارغ، تجاهله
            if (empty($termName)) {
                Log::warning("تجاهل مصطلح بدون اسم:", ['term' => $term]);
                continue;
            }

            // إذا لم نر هذا الاسم من قبل
            if (!isset($seenNames[$termName])) {
                $uniqueTerms[] = $term;
                $seenNames[$termName] = [
                    'id' => $termId,
                    'lang' => $termLang,
                    'index' => count($uniqueTerms) - 1
                ];

                Log::debug("✅ تمت إضافة مصطلح جديد:", [
                    'name' => $termName,
                    'id' => $termId,
                    'lang' => $termLang
                ]);
            } else {
                // المصطلح موجود، تحقق من اللغة
                $existingInfo = $seenNames[$termName];

                $duplicatesLog[] = [
                    'name' => $termName,
                    'existing_id' => $existingInfo['id'],
                    'existing_lang' => $existingInfo['lang'],
                    'new_id' => $termId,
                    'new_lang' => $termLang
                ];

                // إذا كانت اللغة الجديدة هي المفضلة، استبدل الموجود
                if ($termLang === $preferredLang && $existingInfo['lang'] !== $preferredLang) {
                    $uniqueTerms[$existingInfo['index']] = $term;
                    $seenNames[$termName] = [
                        'id' => $termId,
                        'lang' => $termLang,
                        'index' => $existingInfo['index']
                    ];

                    Log::info("🔄 تم استبدال المصطلح باللغة المفضلة:", [
                        'name' => $termName,
                        'old_id' => $existingInfo['id'],
                        'new_id' => $termId,
                        'preferred_lang' => $preferredLang
                    ]);
                } else {
                    Log::debug("تجاهل مصطلح مكرر:", [
                        'name' => $termName,
                        'existing_id' => $existingInfo['id'],
                        'duplicate_id' => $termId
                    ]);
                }
            }
        }

        // ترتيب المصطلحات حسب الاسم (رقمياً إذا كانت أرقام)
        usort($uniqueTerms, function($a, $b) {
            $nameA = $a['name'] ?? '';
            $nameB = $b['name'] ?? '';

            // إذا كانت الأسماء أرقام، قارن رقمياً
            if (is_numeric($nameA) && is_numeric($nameB)) {
                return (int)$nameA - (int)$nameB;
            }

            // وإلا قارن أبجدياً
            return strcmp($nameA, $nameB);
        });

        // تسجيل النتائج
        Log::info('انتهاء فلترة المصطلحات:', [
            'original_count' => count($terms),
            'filtered_count' => count($uniqueTerms),
            'removed_count' => count($terms) - count($uniqueTerms),
            'duplicates_found' => count($duplicatesLog),
            'final_terms' => array_column($uniqueTerms, 'name')
        ]);

        if (!empty($duplicatesLog)) {
            Log::info('المصطلحات المكررة التي تم معالجتها:', $duplicatesLog);
        }

        return array_values($uniqueTerms);
    }
    public function updateVariationMrbpRole($variationId, $roleId, $value)
    {
        // For variations, we need to update directly on the target product/variation
        try {
            // Get all products (limited to 50 for performance)
            $products = $this->getProducts(['per_page' => 50]);

            // Find the parent product containing this variation
            $parentProductId = null;
            foreach ($products as $product) {
                if (isset($product['variations']) && is_array($product['variations']) && in_array($variationId, $product['variations'])) {
                    $parentProductId = $product['id'];
                    break;
                }
            }

            if (!$parentProductId) {
                throw new \Exception("Parent product not found for variation ID: {$variationId}");
            }

            // Get the current variation data
            $variation = $this->get("products/{$parentProductId}/variations/{$variationId}");

            // Prepare meta data update
            $metaData = $variation['meta_data'] ?? [];

            // Check if mrbp_role exists in meta_data
            $mrbpRoleFound = false;
            foreach ($metaData as &$meta) {
                if ($meta['key'] === 'mrbp_role') {
                    $mrbpRoleFound = true;

                    // تحديث القيم بنفس التنسيق الجديد
                    if (!is_array($meta['value'])) {
                        $meta['value'] = [];
                    }

                    // أولاً نزيل أي إدخال موجود لهذا الدور
                    $newRoleValues = [];
                    $roleEntryExists = false;

                    foreach ($meta['value'] as $roleEntry) {
                        if (isset($roleEntry[$roleId])) {
                            $roleEntryExists = true;
                            // تحديث القيم
                            $newRoleValues[] = [
                                $roleId => ucfirst($roleId),
                                'mrbp_regular_price' => $value,
                                'mrbp_sale_price' => $value,
                                'mrbp_make_empty_price' => ""
                            ];
                        } else {
                            $newRoleValues[] = $roleEntry;
                        }
                    }

                    if (!$roleEntryExists) {
                        $newRoleValues[] = [
                            $roleId => ucfirst($roleId),
                            'mrbp_regular_price' => $value,
                            'mrbp_sale_price' => $value,
                            'mrbp_make_empty_price' => ""
                        ];
                    }

                    $meta['value'] = $newRoleValues;
                    break;
                }
            }

            // If mrbp_role doesn't exist, add it
            if (!$mrbpRoleFound) {
                $metaData[] = [
                    'key' => 'mrbp_role',
                    'value' => [
                        [
                            $roleId => ucfirst($roleId),
                            'mrbp_regular_price' => $value,
                            'mrbp_sale_price' => $value,
                            'mrbp_make_empty_price' => ""
                        ]
                    ]
                ];
            }

            // Log the update for debugging
            logger()->info('Updating variation meta_data', [
                'variationId' => $variationId,
                'parentProductId' => $parentProductId,
                'metaData' => $metaData
            ]);

            // Update the variation
            $result = $this->put("products/{$parentProductId}/variations/{$variationId}", [
                'meta_data' => $metaData
            ]);

            return $result;
        } catch (\Exception $e) {
            logger()->error('Error updating variation price role', [
                'variationId' => $variationId,
                'roleId' => $roleId,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * الحصول على متغيرات المنتج مع قيم الـ roles
     */
    public function getProductVariationsWithRoles($productId)
    {
        try {
            // جلب جميع متغيرات المنتج مرة واحدة
            $variations = $this->get("products/{$productId}/variations", [
                'per_page' => 100 // الحد الأقصى للمتغيرات
            ]);

            // إضافة قيم roles لكل متغير
            foreach ($variations as &$variation) {
                // تهيئة قيم roles
                $variation['role_values'] = [];

                // البحث عن meta_data للـ mrbp_role
                if (isset($variation['meta_data']) && is_array($variation['meta_data'])) {
                    foreach ($variation['meta_data'] as $meta) {
                        if ($meta['key'] === 'mrbp_role' && is_array($meta['value'])) {
                            // استخراج قيم الـ roles
                            foreach ($meta['value'] as $roleEntry) {
                                if (is_array($roleEntry)) {
                                    $roleKey = array_key_first($roleEntry);
                                    if ($roleKey) {
                                        // Formato directo
                                        $variation['role_values'][$roleKey] = $roleEntry['mrbp_regular_price'] ?? '';
                                    }
                                }
                            }
                        }
                    }
                }
            }

            logger()->info('Retrieved variations with roles', [
                'productId' => $productId,
                'count' => count($variations)
            ]);

            return $variations;
        } catch (\Exception $e) {
            logger()->error('Error getting variations with roles', [
                'productId' => $productId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * تحديث سعر الدور للمنتج الأساسي
     */
    public function updateProductMrbpRole($productId, $roleId, $value)
    {
        try {
            // الحصول على بيانات المنتج الحالية
            $product = $this->getProduct($productId);

            // تحضير بيانات meta_data
            $metaData = $product['meta_data'] ?? [];

            // التحقق مما إذا كانت القيمة فارغة أو صفر - في هذه الحالة سنقوم بحذف الدور
            $shouldRemoveRole = empty($value) || $value == '0' || $value === 0;

            // إذا كنا بحاجة إلى حذف الدور ولا توجد بيانات meta، نعود
            if ($shouldRemoveRole && empty($metaData)) {
                logger()->info('No meta data to remove role from', [
                    'productId' => $productId,
                    'roleId' => $roleId
                ]);
                return ['success' => true, 'message' => 'No role to remove'];
            }

            // البحث عن mrbp_role في meta_data
            $mrbpRoleFound = false;
            $roleRemoved = false;

            foreach ($metaData as $index => &$meta) {
                if ($meta['key'] === 'mrbp_role') {
                    $mrbpRoleFound = true;

                    // إذا كان يجب إزالة الدور
                    if ($shouldRemoveRole) {
                        // إذا كانت القيمة مصفوفة مباشرة من الأدوار
                        if (isset($meta['value']) && is_array($meta['value'])) {
                            $newRoleValues = [];
                            foreach ($meta['value'] as $roleEntry) {
                                // تخطي الدور الذي نريد إزالته
                                if (!isset($roleEntry[$roleId])) {
                                    $newRoleValues[] = $roleEntry;
                                } else {
                                    $roleRemoved = true;
                                }
                            }

                            // تحديث الأدوار المصفاة أو إزالتها تمامًا إذا كانت فارغة
                            if (!empty($newRoleValues)) {
                                $meta['value'] = $newRoleValues;
                            } else {
                                // إزالة mrbp_role بالكامل إذا لم تكن هناك أدوار أخرى
                                unset($metaData[$index]);
                                $metaData = array_values($metaData); // إعادة فهرسة المصفوفة
                            }
                        }
                    } else {
                        // عدم الإزالة - منطق التحديث
                        // التحويل إلى التنسيق المناسب إذا لم تكن مصفوفة بالفعل
                        if (!is_array($meta['value'])) {
                            $meta['value'] = [];
                        }

                        // أولاً نزيل أي إدخال موجود لهذا الدور
                        $newRoleValues = [];
                        $roleEntryExists = false;

                        foreach ($meta['value'] as $roleEntry) {
                            if (isset($roleEntry[$roleId])) {
                                $roleEntryExists = true;

                                // تحديث بالتنسيق الجديد المباشر
                                $newRoleValues[] = [
                                    $roleId => ucfirst($roleId),
                                    'mrbp_regular_price' => $value,
                                    'mrbp_sale_price' => $value,
                                    'mrbp_make_empty_price' => ""
                                ];
                            } else {
                                $newRoleValues[] = $roleEntry;
                            }
                        }

                        // إذا لم يتم العثور على الدور، أضفه
                        if (!$roleEntryExists) {
                            // إنشاء قيمة جديدة بالتنسيق المباشر
                            $newRoleValues[] = [
                                'id' => $roleId, // إضافة ID للدور
                                'name' => ucfirst($roleId),
                                'mrbp_regular_price' => $value,
                                'mrbp_sale_price' => $value,
                                'mrbp_make_empty_price' => ""
                            ];
                        }

                        $meta['value'] = $newRoleValues;
                    }
                    break;
                }
            }

            // إذا كان mrbp_role غير موجود ولسنا نحاول إزالته، نضيفه
            if (!$mrbpRoleFound && !$shouldRemoveRole) {
                $metaData[] = [
                    'key' => 'mrbp_role',
                    'value' => [
                        [
                            'id' => $roleId, // إضافة ID للدور
                            'name' => ucfirst($roleId),
                            'mrbp_regular_price' => $value,
                            'mrbp_sale_price' => $value,
                            'mrbp_make_empty_price' => ""
                        ]
                    ]
                ];
            }

            // تسجيل التحديث للتصحيح
            logger()->info('Updating product meta_data', [
                'productId' => $productId,
                'shouldRemoveRole' => $shouldRemoveRole,
                'roleRemoved' => $roleRemoved,
                'metaData' => $metaData
            ]);

            // تحديث المنتج
            $result = $this->put("products/{$productId}", [
                'meta_data' => $metaData
            ]);

            return $result;
        } catch (\Exception $e) {
            logger()->error('Error updating product price role', [
                'productId' => $productId,
                'roleId' => $roleId,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * تحديث سعر الدور للمنتج - واجهة بديلة لـ updateProductMrbpRole
     */
    public function updateProductRolePrice($productId, $roleId, $value)
    {
        return $this->updateProductMrbpRole($productId, $roleId, $value);
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

    public function updateMrbpMetaboxUserRoleEnable($productId, $yes)
    {
        return $this->put("products/{$productId}", [
            'meta_data' => [
                [
                    'key' => 'mrbp_metabox_user_role_enable',
                    'value' => $yes
                ]
            ]
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

    private function sanitizeVariationData(array $variation): array
    {
        $cleanData = [];

        // Required fields
        if (isset($variation['regular_price'])) {
            $cleanData['regular_price'] = (string)$variation['regular_price'];
        }

        // --- تعديل هنا لمعالجة stock_quantity كـ integer أو null ---
        $stockQuantity = null;
        if (isset($variation['stock_quantity'])) {
            // إذا كانت القيمة رقمية (بما في ذلك الصفر كرقم أو كنص)، حولها إلى integer
            if (is_numeric($variation['stock_quantity'])) {
                $stockQuantity = (int)$variation['stock_quantity'];
            }
            // إذا كانت موجودة ولكنها فارغة (سلسلة نصية فارغة)، اجعلها null
            else if ($variation['stock_quantity'] === '') {
                $stockQuantity = null;
            }
            // إذا كانت null، اجعلها null
            else if (is_null($variation['stock_quantity'])) {
                $stockQuantity = null;
            }
            // لأي حالات أخرى غير متوقعة، استخدم القيمة كما هي
            else {
                $stockQuantity = $variation['stock_quantity'];
            }
        }

        // ✅ إجباري: تفعيل إدارة المخزون للمتغيرات
        $cleanData['manage_stock'] = true;

        // Crucial: If stock is managed, send 0 instead of null for empty quantities
        if (is_null($stockQuantity)) {
            $stockQuantity = 0; // تحويل null إلى 0 عند تفعيل إدارة المخزون
            Log::info('WooCommerceService->sanitizeVariationData: Converted null stock_quantity to 0 (manage_stock is true).', [
                'final_stock_quantity_after_conversion' => $stockQuantity
            ]);
        }

        $cleanData['stock_quantity'] = $stockQuantity;

        // --- إضافة stock_status بناءً على الكمية ---
        $stockStatus = 'instock';
        if ($cleanData['stock_quantity'] <= 0) {
            $stockStatus = 'outofstock';
        }
        $cleanData['stock_status'] = $stockStatus;

        // باقي الحقول
        if (isset($variation['sku'])) {
            $cleanData['sku'] = (string)$variation['sku'];
        }

        // Optional fields
        if (isset($variation['id'])) {
            $cleanData['id'] = (int)$variation['id'];
        }

        if (!empty($variation['sale_price'])) {
            $cleanData['sale_price'] = (string)$variation['sale_price'];
        }

        if (!empty($variation['description'])) {
            $cleanData['description'] = (string)$variation['description'];
        }

        // Process attributes
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

        // Process image
        if (isset($variation['image']) && !empty($variation['image'])) {
            if (is_string($variation['image'])) {
                $cleanData['image'] = ['src' => $variation['image']];
            } else if (is_array($variation['image']) && isset($variation['image']['src'])) {
                $cleanData['image'] = ['src' => $variation['image']['src']];
            }
        }

        Log::info('WooCommerceService->sanitizeVariationData: Final cleaned data with manage_stock=true.', [
            'final_cleaned_data' => $cleanData
        ]);

        return $cleanData;
    }
    private function sanitizeAttributes(array $attributes): array
    {
        $sanitized = [];
        foreach ($attributes as $attribute) {
            if (isset($attribute['id']) && isset($attribute['option'])) {
                $sanitized[] = [
                    'id' => (int)$attribute['id'],
                    'option' => (string)$attribute['option']
                ];
            }
        }
        return $sanitized;
    }

    private function sanitizeImage($image): array
    {
        if (is_string($image)) {
            return ['src' => $image];
        } else if (is_array($image) && isset($image['src'])) {
            return ['src' => $image['src']];
        }
        return [];
    }

    public function batchUpdateProducts(array $data): array
    {
        try {
            logger()->info('Sending batch update to WooCommerce API', [
                'create_count' => count($data['create'] ?? []),
                'update_count' => count($data['update'] ?? []),
                'delete_count' => count($data['delete'] ?? [])
            ]);

            return $this->post('products/batch', $data);
        } catch (\Exception $e) {
            logger()->error('Failed to process batch update', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function batchUpdateVariations($productId, array $data): array
    {
        try {
            Log::info('WooCommerceService->batchUpdateVariations: Sending batch variation update payload.', [
                'productId' => $productId,
                'payload_data' => $data // Log the full payload being sent
            ]);

            return $this->post("products/{$productId}/variations/batch", $data);
        } catch (\Exception $e) {
            logger()->error('Failed to process batch variation update', [
                'productId' => $productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getOrdersReportData()
    {
        return $this->get('reports/orders/totals');
    }

    public function getAllVariations(): array
    {
        $allVariations = [];

        try {
            // 1. جلب كل المنتجات القابلة للتغيير (variable)
            $page = 1;
            do {
                $response = $this->get('products', [
                    'type' => 'variable',
                    'per_page' => 100,
                    'page' => $page,
                    'status' => 'publish'
                ]);

                $products = is_array($response) && isset($response['data']) ? $response['data'] : $response;

                foreach ($products as $product) {
                    $productId = $product['id'];

                    // 2. جلب المتغيرات الخاصة بهذا المنتج
                    $variations = $this->getVariationsByProductId($productId);

                    foreach ($variations as &$variation) {
                        $variation['product_id'] = $productId; // نضيف معرف المنتج لسهولة الاستخدام
                    }

                    $allVariations = array_merge($allVariations, $variations);
                }

                // 3. تحقق من وجود صفحات أخرى
                $totalPages = $response['total_pages'] ?? 1;
                $page++;
            } while ($page <= $totalPages);

            logger()->info("✅ All variations fetched", ['total' => count($allVariations)]);
            return $allVariations;
        } catch (\Exception $e) {
            logger()->error('❌ Error fetching all variations', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    public function getVariableProductsPaginated(int $page = 1, int $perPage = 100): array
    {
        return $this->get('products', [
            'type' => 'variable',
            'status' => 'publish',
            'per_page' => $perPage,
            'page' => $page,
        ]);
    }

    public function getShippingMethods(): array
    {
        return $this->get('shipping_methods');
    }

    public function getCustomers(array $query = []): array
    {
        return $this->get('customers', $query);
    }

    public function getLastPageFromHeaders(): int
    {
        $headers = $this->client->getHeaders();
        $lastPage = 1;
        if (isset($headers['X-WP-TotalPages'])) {
            $lastPage = (int)$headers['X-WP-TotalPages'][0];
        }
        return $lastPage;
    }

    public function getCustomersCount()
    {
        $response = $this->get('customers', [
            'per_page' => 1 // لا داعي لجلب 100 عنصر
        ]);

        // إذا كانت استجابة فيها total => نرجع العدد من الهيدر
        return is_array($response) && isset($response['total'])
            ? $response['total']
            : count($response);
    }

    public function getProductsCount()
    {
        $response = $this->get('products', [
            'per_page' => 1 // فقط لمعرفة العدد
        ]);

        return is_array($response) && isset($response['total'])
            ? $response['total']
            : count($response);
    }

    public function getLowStockProducts()
    {
        $response = $this->get('products', [
            'per_page' => 100,
            'status' => 'publish',
            'stock_quantity' => [
                'lte' => 5
            ]
        ]);

        return $response['data'] ?? $response;
    }

    public function createUser($data)
    {
        return $this->post('customers', $data);
    }
}
