<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'venue_id', 'association_id', 'active'];

    public function association()
    {
        return $this->belongsTo('App\Association');
    }

    public function captains()
    {
        return $this->hasMany('App\User');
    }

    public function members()
    {
        return $this->hasMany('App\User');
    }

    public function homeVenue()
    {
        return $this->belongsTo('App\Venue', 'venue_id');
    }

    public function roster()
    {
        return $this->hasMany('App\Member');
    }
}
