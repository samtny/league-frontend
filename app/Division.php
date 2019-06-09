<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Division extends Model
{

    protected $fillable = array('name');

    // Division relates to an association:
    public function association() {
        return $this->belongsTo('App\Association');
    }

}
