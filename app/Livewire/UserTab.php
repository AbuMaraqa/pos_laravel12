<?php

namespace App\Livewire;

use App\Models\User;
use App\Tabs\General;
use Livewire\Component;
use Vildanbina\LivewireTabs\TabsComponent;

class UserTab extends TabsComponent
{
    public $userId;

    /*
     * Will return App\Models\User instance or will create empty User (based on $userId parameter)
     */
    public function model()
    {
        return User::findOrNew($this->userId);
    }

    public array $tabs = [
        General::class,
        // Other tabs...
    ];
}
