<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Round extends Model
{

    protected $fillable = array('name', 'start_date', 'end_date', 'association_id', 'series_id', 'division_id', 'schedule_id');

    // Round belongs to a series:
    public function series() {
        return $this->hasOne('App\Series');
    }

    public function schedule()
    {
        return $this->belongsTo('App\Schedule');
    }

}
