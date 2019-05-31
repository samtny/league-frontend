<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Match extends Model
{

    protected $fillable = array('name', 'start_date', 'end_date');

    public function round() {
        return $this->hasOne('Round');
    }

}
