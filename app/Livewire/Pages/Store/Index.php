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

    public function edit($id){
        $this->data = Store::find($id)->toArray();

        $this->modal('edit-store')->show();
    }

    public function update(){
        $this->validate([
            'data.name' => 'required',
            'data.address' => 'nullable',
            'data.notes' => 'nullable',
        ]);

        $store = Store::find($this->data['id']);

        $store->update($this->data);

        $this->stores = Store::all();

        $this->modal('edit-store')->close();

        Toaster::success('Store updated successfully');
    }

    public function delete($id){
        $store = Store::find($id);

        $store->delete();

        $this->stores = Store::all();

        Toaster::success('Store deleted successfully');
    }

    public function render()
    {
        return view('livewire.pages.store.index');
    }
}
