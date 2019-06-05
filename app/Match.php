<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Match extends Model
{

    protected $fillable = array('name', 'start_date', 'end_date', 'round_id', 'division_id', 'series_id');

    public function round() {
        return $this->hasOne('Round');
    }

}
