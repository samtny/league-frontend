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

    public function teams() {
        return $this->hasMany('App\Team');
    }

    public function venues() {
        return $this->hasMany('App\Venue');
    }

    public function series() {
        return $this->hasMany('App\Series');
    }

    public function schedules() {
        return $this->hasMany('App\Schedule');
    }

    public function resultSubmissions() {
        return $this->hasMany('App\ResultSubmission');
    }

    public function users()
    {
        return $this->hasManyThrough('App\User', 'App\AssociationUser', 'association_id', 'id', 'id', 'user_id');
    }

}
