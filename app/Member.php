<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $fillable = ['name', 'role', 'order', 'team_id', 'association_id'];

    public function association()
    {
        return $this->belongsTo('App\Association');
    }

    public function team()
    {
        return $this->belongsTo('App\Team');
    }
}
