<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ContactSubmission extends Model
{

    protected $fillable = [ 'email', 'reason', 'comment', 'association_id' ];

    public function association() {
        return $this->belongsTo('App\Association');
    }

}
