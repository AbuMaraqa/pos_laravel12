<?php

namespace App\Livewire\Pages\Store;

use App\Models\Store;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

class Index extends Component
{

    public ?array $data;
    public $stores;

    public function mount(){
        $this->stores = Store::all();
    }

    public function save(){
        $this->validate([
            'data.name' => 'required',
            'data.address' => 'nullable',
            'data.notes' => 'nullable',
        ]);

        $store = Store::create($this->data);

        $this->stores->push($store);

        $this->modal('add-store')->close();

        Toaster::success('Store created successfully');
    }

    public function render()
    {
        return view('livewire.pages.store.index');
    }
}
