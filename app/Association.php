<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Association extends Model
{
    use SoftDeletes;

    protected $fillable = array('name', 'user_id', 'subdomain', 'home_image_path');

    public function user() {
        return $this->hasOne('User');
    }

    public function divisions() {
        return $this->hasMany('App\Division');
    }

    public function series() {
        return $this->hasMany('App\Series');
    }

    public function venues() {
        return $this->hasMany('App\Venue');
    }

    public function teams() {
        return $this->hasMany('App\Team');
    }

    public function schedules() {
        return $this->hasMany('App\Schedule');
    }

}
