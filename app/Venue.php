<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    protected $fillable = array('name', 'address', 'association_id');

    public function association() {
        return $this->belongsTo('App\Association');
    }

    public function machines() {
        return $this->hasMany('Machine');
    }

}
