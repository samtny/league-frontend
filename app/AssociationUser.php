<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AssociationUser extends Model
{

    protected $fillable = array('user_id', 'association_id');

    public function association() {
        return $this->hasOne('App\Association');
    }

    public function user() {
        return $this->hasOne('App\User');
    }

}
