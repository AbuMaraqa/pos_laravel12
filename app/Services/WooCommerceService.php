<?php

namespace App\Services;

use App\Models\Subscription;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Curl;

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

        $this->baseUrl = env('WOOCOMMERCE_STORE_URL');
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

            logger()->error('WooCommerce API POST Error', [
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
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
        return $this->get('products/categories', $query);
    }

    public function getVariationsByProductId($productId): array
    {
        try {
            $response = $this->get("products/{$productId}/variations", [
                'per_page' => 100, // Get up to 100 variations
                'status' => 'publish'
            ]);

            // Log the response for debugging
            logger()->info('Retrieved variations for product', [
                'productId' => $productId,
                'count' => count($response)
            ]);

            return $response;
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

    public function getAttributes(array $query = []): array
    {
        return $this->get('products/attributes', $query);
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

    public function getAttributesWithTerms(): array
    {
        $attributes = $this->getAttributes();
        foreach ($attributes as &$attribute) {
            $attribute['terms'] = $this->getTermsForAttribute($attribute['id']);
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
        return $this->get("products/attributes/{$attributeId}/terms", $query);
    }

    public function getAttributeById($id): array
    {
        return $this->get("products/attributes/{$id}");
    }

    public function getTermsByAttributeId($attributeId, array $query = []): array
    {
        return $this->get("products/attributes/{$attributeId}/terms", $query);
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
        } catch (\Exception $e) {
            logger()->error('Failed to update product', [
                'productId' => $productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function updateProductAttributes($productId, array $data): array
    {
        try {
            logger()->info('Updating product attributes', [
                'productId' => $productId,
                'data' => $data
            ]);

            // First update the product attributes
            if (isset($data['attributes'])) {
                $productData = ['attributes' => $data['attributes']];
                $this->put("products/{$productId}", $productData);
            }

            // Then update or create variations
            if (isset($data['variations'])) {
                foreach ($data['variations'] as $variation) {
                    if (isset($variation['id'])) {
                        // Update existing variation
                        $this->put("products/{$productId}/variations/{$variation['id']}", $variation);
                    } else {
                        // Create new variation
                        $this->post("products/{$productId}/variations", $variation);
                    }
                }
            }

            return [
                'success' => true,
                'message' => 'Product attributes and variations updated successfully'
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
            logger()->info('Syncing variations for product', [
                'productId' => $productId,
                'variationsCount' => count($variations)
            ]);

            // 1. Get existing variations to compare
            $existingVariations = $this->getVariationsByProductId($productId);
            $existingVariationsMap = [];

            foreach ($existingVariations as $variation) {
                $existingVariationsMap[$variation['id']] = $variation;
            }

            $results = [
                'created' => 0,
                'updated' => 0,
                'deleted' => 0,
                'errors' => []
            ];

            // 2. Process each variation
            foreach ($variations as $variation) {
                try {
                    // تجهيز بيانات المتغيّر
                    $variationData = [
                        'regular_price' => $variation['regular_price'] ?? '',
                        'sale_price' => $variation['sale_price'] ?? '',
                        'stock_quantity' => $variation['stock_quantity'] ?? 0,
                        'description' => $variation['description'] ?? ''
                    ];

                    // إضافة الخصائص للمتغيّر
                    if (isset($variation['options']) && !empty($variation['options'])) {
                        $attributes = [];
                        foreach ($variation['options'] as $index => $option) {
                            if (isset($variation['attributes'][$index]) && isset($variation['attributes'][$index]['id'])) {
                                $attributes[] = [
                                    'id' => $variation['attributes'][$index]['id'],
                                    'option' => $option
                                ];
                            }
                        }
                        if (!empty($attributes)) {
                            $variationData['attributes'] = $attributes;
                        }
                    }

                    // إضافة صورة إذا وجدت
                    if (isset($variation['image']) && !empty($variation['image'])) {
                        if (is_string($variation['image'])) {
                            $variationData['image'] = ['src' => $variation['image']];
                        } else if (isset($variation['image']['src'])) {
                            $variationData['image'] = ['src' => $variation['image']['src']];
                        }
                    }

                    // تحديث أو إنشاء
                    if (isset($variation['id']) && !empty($variation['id'])) {
                        // Update existing variation
                        $this->put("products/{$productId}/variations/{$variation['id']}", $variationData);
                        $results['updated']++;

                        // Remove from map to track which variations need to be deleted
                        if (isset($existingVariationsMap[$variation['id']])) {
                            unset($existingVariationsMap[$variation['id']]);
                        }
                    } else {
                        // Create new variation
                        $this->post("products/{$productId}/variations", $variationData);
                        $results['created']++;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'message' => $e->getMessage(),
                        'variation' => $variation
                    ];

                    logger()->error('Error processing variation', [
                        'error' => $e->getMessage(),
                        'variation' => $variation
                    ]);
                }
            }

            // 3. Delete variations that no longer exist
            foreach ($existingVariationsMap as $variationId => $variation) {
                try {
                    // Only delete if the client requested management of all variations
                    $this->delete("products/{$productId}/variations/{$variationId}");
                    $results['deleted']++;
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'message' => $e->getMessage(),
                        'variation_id' => $variationId
                    ];

                    logger()->error('Error deleting variation', [
                        'error' => $e->getMessage(),
                        'variation_id' => $variationId
                    ]);
                }
            }

            logger()->info('Variations sync completed', $results);

            return [
                'success' => true,
                'results' => $results
            ];
        } catch (\Exception $e) {
            logger()->error('Failed to sync variations', [
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
        return $this->get('shipping/zones');
    }

    public function shippingZoneById($zoneId)
    {
        return $this->get("shipping/zones/{$zoneId}");
    }

    public function shippingZoneMethods($zoneId)
    {
        return $this->get("shipping/zones/{$zoneId}/methods");
    }

    public function updateShippingZoneMethod($zoneId, $methodId, $settings)
    {
        return $this->put("shipping/zones/{$zoneId}/methods/{$methodId}", [
            'settings' => $settings
        ]);
    }

    public function getProductVariations($productId)
    {
        return $this->get("products/{$productId}/variations");
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
                                $roleId => ucfirst($roleId),
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
                            $roleId => ucfirst($roleId),
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
}
