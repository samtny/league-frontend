<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Round extends Model
{
    protected $fillable = ['name', 'start_date', 'end_date', 'association_id', 'series_id', 'division_id', 'schedule_id', 'scores_closed', 'off_week', 'playoffs_week', 'message'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'off_week' => 'boolean',
        'playoffs_week' => 'boolean',
    ];

    // Round belongs to a series:
    public function series()
    {
        return $this->belongsTo('App\Series');
    }

    public function schedule()
    {
        return $this->belongsTo('App\Schedule');
    }

    public function matches()
    {
        return $this->hasMany('App\PLMatch');
    }

    public function scheduledMatches()
    {
        return $this->hasMany('App\PLMatch')
            ->whereNotNull('home_team_id');
    }

    /**
     * Create one match per eligible venue in this round's association, using
     * the parent schedule as the source of truth for series/division/
     * association. A venue is eligible when it shares the schedule's
     * division - or, for a divisionless schedule, when the venue itself has
     * no divisions assigned.
     */
    public function createMatches(): void
    {
        $schedule = $this->schedule;
        $association = $schedule->association;

        $eligibleVenues = $association->activeVenues->filter(function ($venue) use ($schedule) {
            return $schedule->division_id === null
                ? $venue->divisions->isEmpty()
                : $venue->divisions->contains('id', $schedule->division_id);
        });

        foreach ($eligibleVenues as $venue) {
            $match = new PLMatch;

            $match->name = $venue->name.' – '.$this->start_date->format('m-d-Y');
            $match->association_id = $schedule->association_id;
            $match->series_id = $schedule->series_id;
            $match->division_id = $schedule->division_id;

            // Unique key fields:
            $match->schedule_id = $schedule->id;
            $match->round_id = $this->id;
            $match->venue_id = $venue->id;
            $match->sequence = 1;

            $match->start_date = $this->start_date;
            $match->end_date = $this->end_date;

            $match->save();
        }
    }
}
