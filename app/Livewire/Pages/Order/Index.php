<?php

namespace App\Livewire\Pages\Order;

use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;
use App\Services\WooCommerceService;
use Illuminate\Pagination\LengthAwarePaginator;

class Index extends Component
{
    use WithPagination;

    public array $filters = [
        'customer_name' => '',
        'order_number' => '',
        'date_from' => null,
        'date_to' => null,
        'status' => '',
    ];

    public int $perPage = 10;
    public int $total = 0;
    public int $page = 1;

    // هذا السطر مهم لتفعيل الترقيم
    protected $queryString = ['page'];

    // إعادة تعيين الصفحة عند تحديث الفلاتر
    public function updated($key): void
    {
        if (str_starts_with($key, 'filters.')) {
            $this->resetPage();
        }
    }

    public function getOrders(): array
    {
        $query = [
            'search' => $this->filters['order_number'] ?? '',
            'after' => $this->filters['date_from'] ? Carbon::parse($this->filters['date_from'])->startOfDay()->toIso8601String() : null,
            'before' => $this->filters['date_to'] ? Carbon::parse($this->filters['date_to'])->endOfDay()->toIso8601String() : null,
            'status' => $this->filters['status'] ?: 'any',
            'per_page' => $this->perPage,
            'page' => $this->getPage(),
        ];

        $query = array_filter($query);

        $woo = app(WooCommerceService::class)->get('orders', $query);

        return [
            'orders' => $woo['data'] ?? $woo,
            'total' => $woo['total'] ?? 0,
        ];
    }

    public function render()
    {
        $response = $this->getOrders();

        $collection = collect($response['orders'] ?? $response);
        $total = $response['total'] ?? 1000;

        $orders = new LengthAwarePaginator(
            $collection,
            $total,
            $this->perPage,
            $this->page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('livewire.pages.order.index', [
            'orders' => $orders,
        ]);
    }
}
