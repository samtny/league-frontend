<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ResultSubmission extends Model
{

    protected $fillable = array('match_id', 'home_team_score', 'away_team_score', 'approved');

    public function match() {
        return $this->belongsTo('App\Match');
    }

}
