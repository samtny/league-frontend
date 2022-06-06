<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TeamResult extends Model
{
    protected $fillable = array('schedule_id', 'match_id', 'team_id', 'points', 'win', 'loss', 'tie');

    public function schedule() {
        return $this->belongsTo('App\Schedule');
    }

    public function match() {
        return $this->belongsTo('App\PLMatch');
    }

    public function team() {
        return $this->belongsTo('App\Team');
    }
}
