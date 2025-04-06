<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    //

    protected $table = 'subscriptions';

    protected $fillable = [
        'company_name',
        'site_url',
        'consumer_secret',
        'consumer_key',
        'app_username',
        'app_password',
        'start_date',
        'end_date',
        'annual_price',
        'notes',
        'status',
    ];
}
