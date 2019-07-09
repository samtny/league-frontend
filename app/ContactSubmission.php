<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ContactSubmission extends Model
{
    protected $fillable = [ 'email', 'reason', 'comment' ];
}
