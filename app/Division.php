<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Division extends Model
{
    use SoftDeletes;

    protected $fillable = array('name');

    // Division relates to an association:
    public function association() {
        return $this->belongsTo('App\Association');
    }

}
