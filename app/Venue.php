<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venue extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'address', 'association_id'];

    public function association()
    {
        return $this->belongsTo('App\Association');
    }

    public function machines()
    {
        return $this->hasMany('Machine');
    }
}
