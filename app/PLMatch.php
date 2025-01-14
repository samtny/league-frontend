<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PLMatch extends Model
{

    protected $fillable = array('name', 'start_date', 'end_date', 'association_id',  'series_id', 'division_id', 'schedule_id', 'round_id', 'venue_id', 'home_team_id', 'away_team_id');

    protected $table = 'matches';

    public function association() {
        return $this->belongsTo('App\Association');
    }

    public function series() {
        return $this->belongsTo('App\Series');
    }

    public function division() {
        return $this->belongsTo('App\Division');
    }

    public function schedule() {
        return $this->belongsTo('App\Schedule');
    }

    public function round() {
        return $this->belongsTo('App\Round');
    }

    public function venue() {
        return $this->belongsTo('App\Venue');
    }

    public function homeTeam() {
        return $this->belongsTo('App\Team', 'home_team_id');
    }

    public function awayTeam() {
        return $this->belongsTo('App\Team', 'away_team_id');
    }

    public function result() {
        return $this->hasOne('App\Result', 'match_id');
    }

    public function resultSubmissions() {
        return $this->hasMany('App\ResultSubmission', 'match_id');
    }
}
