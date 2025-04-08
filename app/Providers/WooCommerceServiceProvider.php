<?php

namespace App\Services;

use App\Models\Subscription;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;

class WooCommerceService
{
    protected Client $client;

    public function __construct()
    {
        $user = Auth::user();
        if (!$user || !$user->subscription_id) {

            return redirect()->route('login');
            throw new \Exception("المستخدم غير مسجل الدخول أو لا يملك اشتراك");
        }

        $subscription = Subscription::find($user->subscription_id);

        if (!$subscription || !$subscription->consumer_key || !$subscription->consumer_secret) {
            throw new \Exception("لا توجد مفاتيح WooCommerce صالحة");
        }

        $this->client = new Client([
            'base_uri' => env('WOOCOMMERCE_STORE_URL') . '/wp-json/wc/v3/',
            'auth' => [$subscription->consumer_key, $subscription->consumer_secret],
            'timeout' => 10.0,
        ]);
    }

    public function get(string $endpoint, array $query = []): array
    {
        $response = $this->client->get($endpoint, ['query' => $query]);
        return json_decode($response->getBody()->getContents(), true);
    }

    public function put(string $endpoint, array $data = []): array
    {
        try {
            $response = $this->client->put($endpoint, [
                'json' => $data
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            dd($e->getResponse()->getBody()->getContents());
        }
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

    // مثال:
    public function getProducts(array $query = []): array
    {
        return $this->get('products', $query);
    }

    public function getCategories(array $query = []): array
    {
        return $this->get('products/categories' , $query);
    }

    public function getOrders(array $query = []): array
    {
        return $this->get('orders' , $query);
    }

    public function getOrdersById($id): array
    {
        return $this->get('orders/' . $id);
    }

    public function getAttributes(array $query = []): array
    {
        return $this->get('products/attributes', $query);
    }

    public function getAttributeBySlug($slug)
    {
        $response = $this->client->get('products/attributes', [
            'query' => [
                'slug' => $slug,
            ]
        ]);

        $attributes = json_decode($response->getBody()->getContents(), true);

        return !empty($attributes) ? $attributes : null;
    }

    public function deleteAttribute($id): array
    {
        try {
            $response = $this->client->delete('products/attributes/' . $id);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            dd($e->getResponse()->getBody()->getContents());
        }
    }

    public function getTermsForAttribute($attributeId, array $query = []): array
    {
        $response = $this->client->get('products/attributes/' . $attributeId . '/terms', [
            'query' => $query
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function getAttributeById($id): array
    {
        $response = $this->client->get("products/attributes/{$id}");

        return json_decode($response->getBody()->getContents(), true);
    }

    public function getTermsByAttributeId($attributeId, array $query = []): array
    {
        $response = $this->client->get("products/attributes/{$attributeId}/terms", [
            'query' => $query
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function deleteTerm($attributeId, $termId , array $query = []): array
    {
        try {
            $response = $this->client->delete("products/attributes/{$attributeId}/terms/{$termId}" , [
                'query' => $query
            ]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            dd($e->getResponse()->getBody()->getContents());
        }
    }
}
