<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Series extends Model
{

    protected $fillable = array('name', 'start_date', 'end_date');

    // Series relates to an association:
    public function association() {
        return $this->hasOne('Association');
    }

}
