<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{

    protected $fillable = array('name', 'association_id', 'series_id', 'division_id', 'start_date', 'end_date', 'sequence');

    public function association() {
        return $this->belongsTo('App\Association');
    }

    public function series() {
        return $this->belongsTo('App\Series');
    }

    public function division() {
        return $this->hasOne('App\Division');
    }

    public function rounds() {
        return $this->hasMany('App\Round');
    }

    public function matches() {
        return $this->hasMany('App\Match');
    }

}
