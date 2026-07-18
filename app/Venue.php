<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venue extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'address', 'association_id', 'pinballmap_id', 'active', 'schedule_id'];

    protected $casts = ['active' => 'boolean'];

    public function association()
    {
        return $this->belongsTo('App\Association');
    }

    public function machines()
    {
        return $this->hasMany('Machine');
    }

    public function divisions()
    {
        return $this->belongsToMany('App\Division');
    }

    public function schedule()
    {
        return $this->belongsTo('App\Schedule');
    }
}
