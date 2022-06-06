<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Result extends Model
{

    protected $fillable = array('match_id', 'home_team_id', 'away_team_id', 'home_team_score', 'away_team_score');

    public function match() {
        return $this->belongsTo('App\PLMatch');
    }

}
