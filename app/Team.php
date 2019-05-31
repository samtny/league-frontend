<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{

    protected $fillable = array('name');

    public function captains() {
        return $this->hasMany('User');
    }

    public function members() {
        return $this->hasMany('User');
    }

    public function homeVenue() {
        return $this->hasOne('Venue');
    }

}
