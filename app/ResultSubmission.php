<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ResultSubmission extends Model
{

    protected $fillable = array('match_id', 'home_team_score', 'away_team_score', 'win_team_id', 'approved');

    public function association() {
        return $this->belongsTo('App\Association');
    }

    public function schedule() {
        return $this->belongsTo('App\Schedule');
    }

    public function match() {
        return $this->belongsTo('App\Match');
    }

}
