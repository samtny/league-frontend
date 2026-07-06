<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{

    protected $fillable = array('name', 'role', 'team_id', 'association_id');

    public function association() {
        return $this->belongsTo('App\Association');
    }

    public function team() {
        return $this->belongsTo('App\Team');
    }

}
