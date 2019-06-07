<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Series extends Model
{

    protected $fillable = array('user_id', 'association_id', 'name', 'start_date', 'end_date');

    // Series relates to an association:
    public function association() {
        return $this->belongsTo('App\Association');
    }

    public function schedules() {
        return $this->hasMany('App\Schedule');
    }

    public function activeVenues() {
        // FIXME: instead, relate venues to this series through a mapping table:
        return $this->association->venues;
    }

}
