<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Division extends Model
{
    use SoftDeletes;

    protected $fillable = ['name'];

    // Division relates to an association:
    public function association()
    {
        return $this->belongsTo('App\Association');
    }

    public function venues()
    {
        return $this->belongsToMany('App\Venue');
    }

    public function schedules()
    {
        return $this->hasMany('App\Schedule');
    }

    public function activeSchedules()
    {
        return $this->schedules()
            ->where(function ($query) {
                $query->where('archived', '!=', 1)
                    ->orWhereNull('archived');
            });
    }

    public function archivedSchedules()
    {
        return $this->schedules()
            ->where('archived', '=', 1);
    }
}
