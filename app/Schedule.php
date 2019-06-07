<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{

    protected $fillable = array('name', 'association_id', 'series_id', 'division_id', 'start_date', 'end_date', 'sequence');

    public function association() {
        return $this->hasOne('App\Association');
    }

    public function series() {
        return $this->hasOne('App\Series');
    }

    public function division() {
        return $this->hasOne('App\Division');
    }

    public function rounds() {
        return $this->hasMany('App\Round');
    }

}