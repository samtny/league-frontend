<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'association_id', 'series_id', 'division_id', 'start_date', 'end_date', 'weekday', 'sequence', 'archived', 'venues_configured'];

    protected $casts = ['venues_configured' => 'boolean'];

    /**
     * Schedule is soft-deleted, but Round is not - a soft delete alone never
     * fires the DB's ON DELETE CASCADE (matches -> round_id -> rounds, plus
     * results/result_submissions -> match_id) because no row is physically
     * removed. Hard-deleting the rounds here bridges that gap and lets the
     * existing FK cascades take care of matches/results/result_submissions.
     */
    protected static function booted()
    {
        static::deleting(function (Schedule $schedule) {
            if (! $schedule->isForceDeleting()) {
                $schedule->rounds->each->delete();
            }
        });
    }

    public function association()
    {
        return $this->belongsTo('App\Association');
    }

    public function series()
    {
        return $this->belongsTo('App\Series');
    }

    public function division()
    {
        return $this->belongsTo('App\Division');
    }

    public function rounds()
    {
        return $this->hasMany('App\Round');
    }

    public function matches()
    {
        return $this->hasMany('App\PLMatch');
    }

    public function venues()
    {
        return $this->hasMany('App\Venue');
    }

    public function resultSubmissions()
    {
        return $this->hasMany('App\ResultSubmission');
    }
}
