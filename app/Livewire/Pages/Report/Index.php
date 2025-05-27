<?php

namespace App\Livewire\Pages\Report;

use App\Services\WooCommerceService;
use Livewire\Component;

class Index extends Component
{
    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    public string $reportType = 'orders';
    public string $period = 'month';
    public ?string $dateMin = null;
    public ?string $dateMax = null;
    public array $reportData = [];

    public function mount()
    {
        $this->loadReportData();
    }

    public function updated($property)
    {
        $this->loadReportData();
    }

    public function loadReportData()
    {
        $params = [];

        if ($this->period) {
            $params['period'] = $this->period;
        }

        if ($this->dateMin) {
            $params['date_min'] = $this->dateMin;
        }

        if ($this->dateMax) {
            $params['date_max'] = $this->dateMax;
        }

        $endpoint = match ($this->reportType) {
            'orders' => 'reports/orders/totals',
            'sales' => 'reports/sales',
            'customers' => 'reports/customers/totals',
            default => 'reports/orders/totals',
        };

        $response = $this->wooService->get($endpoint, $params);
        $this->reportData = $response['data'] ?? $response ?? [];
    }

    public function updatedReportType()
    {
        $this->loadReportData();
    }

    public function render()
    {
        $labels = collect($this->reportData)->pluck('name');
        $values = collect($this->reportData)->pluck('total');

        return view('livewire.pages.report.index', compact('labels', 'values'));
    }
}
