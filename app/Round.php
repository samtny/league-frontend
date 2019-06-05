<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Round extends Model
{

    protected $fillable = array('name', 'start_date', 'end_date', 'division_id', 'series_id');

    // Round belongs to a series:
    public function series() {
        return $this->hasOne('Series');
    }

}
