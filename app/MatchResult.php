<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MatchResult extends Model
{
    protected $fillable = array('schedule_id', 'match_id', 'team_id', 'points', 'win', 'loss', 'tie');

    public function schedule() {
        return $this->belongsTo('App\Schedule');
    }

    public function match() {
        return $this->belongsTo('App\Match');
    }

    public function team() {
        return $this->belongsTo('App\Team');
    }
}
