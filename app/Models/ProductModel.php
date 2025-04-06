<?php

namespace App\Models;

use Corcel\Model\Post;
use Illuminate\Database\Eloquent\Model;

class ProductModel extends Post
{
    protected $connection = 'wordpress';
}
