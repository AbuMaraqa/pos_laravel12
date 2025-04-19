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
        array_walk_recursive($data, function (&$value) {
            if (is_string($value)) {
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }
        });

        $response = $this->client->post($endpoint, [
            'json' => $data
        ]);

        return json_decode($response->getBody()->getContents(), true);
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

    public function getOrders(array $query = []): array
    {
        return $this->get('orders', $query);
    }

    public function getOrdersById($id): array
    {
        return $this->get('orders/' . $id);
    }

    public function getAttributes(array $query = []): array
    {
        return $this->get('products/attributes', $query);
    }

    public function getVariationById($id): array
    {
        return $this->get('products/variations/' . $id);
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

    public function getRoles(){
        try {
            $response = $this->wpClient->get('roles');
            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            logger()->error('WP API Error getting roles: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function getMrbpRoleById($id){
        $product = $this->getProductsById($id);
        return ($product);
        if($product['meta_data']['key'] == 'mrbp_role'){
            return $product['meta_data']['key'];
        }
        return null;
    }

    public function addCategory($name, $parentId, $description)
    {
        return $this->post('products/categories', [
            'name' => $name,
            'parent' => $parentId,
            'description' => $description
        ]);
    }

    public function getCustomers(){
        return $this->get('customers');
    }

    public function getCustomerById($id){
        return $this->get('customers/' . $id);
    }
}
