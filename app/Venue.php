<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    protected $fillable = array('name', 'address');

    public function machines() {
        return $this->hasMany('Machine');
    }

}
