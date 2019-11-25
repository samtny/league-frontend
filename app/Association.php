<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Association extends Model
{
    use SoftDeletes;

    protected $fillable = array('name', 'user_id', 'subdomain', 'home_image_path', 'about');

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

    public function activeSchedules() {
        return $this->schedules()
            ->where('archived', '!=', 1)
            ->orWhereNull('archived');
    }

    public function resultSubmissions() {
        return $this->hasMany('App\ResultSubmission');
    }

    public function rounds() {
        return $this->hasManyThrough('App\Round', 'App\Schedule', 'association_id', 'schedule_id', 'id', 'id');
    }

    public function activeRounds() {
        return $this->rounds()
            ->where('rounds.start_date', '>=', date('Y-m-d', strtotime('today -7 days')))
            ->where('rounds.start_date', '<=', date('Y-m-d', strtotime('now +7 days')) );
    }

    public function users() {
        return $this->hasManyThrough('App\User', 'App\AssociationUser', 'association_id', 'id', 'id', 'user_id');
    }

    public function contactSubmissions() {
        return $this->hasMany('App\ContactSubmission');
    }

    public function activeContactSubmissions() {
        return $this->contactSubmissions()
            ->where('archived', '!=', 1)
            ->orWhereNull('archived');
    }

}
