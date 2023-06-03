<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Round extends Model
{

    protected $fillable = array('name', 'start_date', 'end_date', 'association_id', 'series_id', 'division_id', 'schedule_id', 'scores_closed');

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'start_date',
        'end_date',
    ];

    // Round belongs to a series:
    public function series() {
        return $this->belongsTo('App\Series');
    }

    public function schedule()
    {
        return $this->belongsTo('App\Schedule');
    }

    public function matches() {
        return $this->hasMany('App\PLMatch');
    }

    public function scheduledMatches() {
        return $this->hasMany('App\PLMatch')
            ->whereNotNull('home_team_id');
    }

}
