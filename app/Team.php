<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{

    protected $fillable = array('name', 'venue_id');

    public function association() {
        return $this->belongsTo('App\Association');
    }

    public function captains() {
        return $this->hasMany('App\User');
    }

    public function members() {
        return $this->hasMany('App\User');
    }

    public function homeVenue() {
        return $this->hasOne('App\Venue');
    }

}
