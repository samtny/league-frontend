<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'association_id', 'series_id', 'division_id', 'start_date', 'end_date', 'sequence', 'archived' => 0];

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

    public function resultSubmissions()
    {
        return $this->hasMany('App\ResultSubmission');
    }
}
