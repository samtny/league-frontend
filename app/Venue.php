<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venue extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'address', 'association_id', 'pinballmap_id', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function association()
    {
        return $this->belongsTo('App\Association');
    }

    public function machines()
    {
        return $this->hasMany('Machine');
    }
}
