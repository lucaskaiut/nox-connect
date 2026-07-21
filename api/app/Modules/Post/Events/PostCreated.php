<?php

namespace App\Modules\Post\Events;

use App\Modules\Post\Models\Post;
use Illuminate\Foundation\Events\Dispatchable;

class PostCreated
{
    use Dispatchable;

    public function __construct(public readonly Post $post) {}
}
