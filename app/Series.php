<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Series extends Model
{
    use SoftDeletes;

    protected $fillable = array('user_id', 'association_id', 'name', 'start_date', 'end_date', 'archived');

    protected static function booted() {
        static::deleting(function (Series $series) {
            if (! $series->isForceDeleting()) {
                $series->schedules->each->delete();
            }
        });
    }

    // Series relates to an association:
    public function association() {
        return $this->belongsTo('App\Association');
    }

    public function schedules() {
        return $this->hasMany('App\Schedule');
    }

    public function activeVenues() {
        // FIXME: instead, relate venues to this series through a mapping table:
        return $this->association->venues;
    }

}
