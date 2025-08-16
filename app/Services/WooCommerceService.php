<?php

namespace App\Services;

use App\Models\Subscription;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Curl;
use Illuminate\Support\Facades\Log;

// تأكد من استيراد Log

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
        try {
            $response = $this->get('products', $query);

            // إذا كانت الاستجابة تحتوي على مفتاح 'data'، فهذا يعني أن البيانات مغلفة
            if (is_array($response) && isset($response['data'])) {
                return $response; // إرجاع الاستجابة كاملة مع metadata
            }

            // إذا كانت الاستجابة مجرد array من المنتجات
            return $response;
        } catch (\Exception $e) {
            logger()->error('Error fetching products', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    public function deleteProductById($id): array
    {
        return $this->delete('products/' . $id);
    }

    public function findProductForPOSWithVariations(string $term): ?array
    {
        try {
            $foundProduct = null;

            // البحث بالـ ID
            if (is_numeric($term)) {
                $foundProduct = $this->getProductsById((int)$term);
            }

            // البحث بـ SKU
            if (!$foundProduct) {
                $bySku = $this->getProducts(['sku' => $term, 'per_page' => 1]);
                $skuData = isset($bySku['data']) ? $bySku['data'] : $bySku;
                if (!empty($skuData)) {
                    $foundProduct = $this->getProductsById($skuData[0]['id']);
                }
            }

            // البحث بالاسم
            if (!$foundProduct) {
                $bySearch = $this->getProducts(['search' => $term, 'per_page' => 5]);
                $searchData = isset($bySearch['data']) ? $bySearch['data'] : $bySearch;
                if (!empty($searchData)) {
                    $foundProduct = $this->getProductsById($searchData[0]['id']);
                }
            }

            // البحث في المتغيرات
            if (!$foundProduct) {
                $foundProduct = $this->searchProductByVariation($term);
            }

            if ($foundProduct) {
                return $this->normalizeProductForPOS($foundProduct);
            }

            return null;
        } catch (\Exception $e) {
            logger()->error('Error in findProductForPOSWithVariations', [
                'term' => $term,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * ✅ دالة البحث في المتغيرات (Variations)
     * تقوم بالبحث عن المنتج الأب بناءً على SKU أو ID الخاص بالمتغير
     */
    private function searchProductByVariation(string $term): ?array
    {
        try {
            $variableProducts = $this->getProducts([
                'type' => 'variable',
                'per_page' => 50,
                'status' => 'publish'
            ]);

            $products = isset($variableProducts['data']) ? $variableProducts['data'] : $variableProducts;

            foreach ($products as $product) {
                if (!empty($product['variations'])) {
                    $variations = $this->getProductVariations($product['id']);

                    foreach ($variations as $variation) {
                        $skuMatch = !empty($variation['sku']) && strcasecmp($variation['sku'], $term) === 0;
                        $idMatch = ctype_digit($term) && $variation['id'] == (int)$term;

                        if ($skuMatch || $idMatch) {
                            // إرجاع المنتج الأب مع تفاصيل المتغيرات
                            $parentProduct = $this->getProductsById($product['id']);
                            return $parentProduct;
                        }
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            logger()->error('Error searching by variation', [
                'term' => $term,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function getProductsById($id): ?array
    {
        try {
            $response = $this->get('products/' . $id);

            if (isset($response['id'])) {
                // إذا كان المنتج متغير، نجلب المتغيرات مباشرة
                if ($response['type'] === 'variable' && !empty($response['variations'])) {
                    $variations = $this->getProductVariations($response['id']);
                    $response['variations_details'] = $variations;
                }

                return $response;
            }

            return null;
        } catch (\Exception $e) {
            logger()->error('Error fetching product by ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return null;
        }
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
            $langSpecificTerms = array_filter($terms, function ($term) use ($lang) {
                return ($term['lang'] ?? 'en') === $lang;
            });

            // إذا لم نجد مصطلحات باللغة المطلوبة، استخدم الفلترة العامة
            if (empty($langSpecificTerms)) {
                $langSpecificTerms = $this->filterUniqueTerms($terms, $lang);
            }

            // ترتيب المصطلحات
            usort($langSpecificTerms, function ($a, $b) {
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

    public function getCustomerById($id): ?array
    {
        try {
            logger()->info('Fetching customer from WooCommerce', ['customer_id' => $id]);

            $response = $this->get('customers/' . $id);

            if (!$response || !isset($response['id'])) {
                logger()->warning('Customer not found in WooCommerce', [
                    'customer_id' => $id,
                    'response' => $response
                ]);
                return null;
            }

            logger()->info('Customer found in WooCommerce', [
                'customer_id' => $response['id'],
                'customer_email' => $response['email'] ?? 'no_email'
            ]);

            // إضافة بيانات افتراضية إذا لم تكن موجودة
            if (!isset($response['billing'])) {
                $response['billing'] = [
                    'first_name' => $response['first_name'] ?? '',
                    'last_name' => $response['last_name'] ?? '',
                    'email' => $response['email'] ?? '',
                    'phone' => '',
                    'address_1' => '',
                    'city' => '',
                    'state' => '',
                    'postcode' => '',
                    'country' => 'PS'
                ];
            }

            if (!isset($response['shipping'])) {
                $response['shipping'] = $response['billing'];
            }

            return $response;

        } catch (\Exception $e) {
            logger()->error('Error fetching customer from WooCommerce', [
                'customer_id' => $id,
                'error' => $e->getMessage()
            ]);

            return null;
        }
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
}
