<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Association extends Model
{
    use SoftDeletes;

    protected $fillable = array('name', 'user_id', 'subdomain');

    public function user() {
        return $this->hasOne('User');
    }

}
