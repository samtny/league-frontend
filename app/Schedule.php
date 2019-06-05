<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{

    protected $fillable = array('name', 'series_id', 'division_id', 'start_date', 'end_date', 'sequence');

    public function rounds() {
        return $this->hasMany('App\Round');
    }

    public function series() {
        return $this->hasOne('App\Series');
    }

}
