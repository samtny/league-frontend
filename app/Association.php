<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Association extends Model
{

    protected $fillable = array('name', 'user_id');

    public function user() {
        return $this->hasOne('User');
    }

}
