<?php

namespace Kevjo\LaravelCollab\Tests;

use Illuminate\Database\Eloquent\Model;
use Kevjo\LaravelCollab\Traits\HasConcurrentEditing;

class TestPost extends Model
{
    use HasConcurrentEditing;

    protected $table = 'posts';

    protected $fillable = [
        'title',
        'content',
    ];
}
