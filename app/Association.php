<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Association extends Model
{
    use SoftDeletes;

    protected $fillable = array('name', 'user_id', 'subdomain', 'home_image_path');

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'subdomain';
    }

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

    public function rounds() {
        return $this->hasManyThrough('App\Round', 'App\Schedule', 'association_id', 'schedule_id', 'id', 'id');
    }

    public function activeRounds() {
        return $this->rounds()
            ->where('rounds.start_date', '>=', date('Y-m-d', strtotime('today')))
            ->where('rounds.start_date', '<=', date('Y-m-d', strtotime('now +7 days')) );
    }

    public function users() {
        return $this->hasManyThrough('App\User', 'App\AssociationUser', 'association_id', 'id', 'id', 'user_id');
    }

}
