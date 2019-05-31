<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Machine extends Model
{

    public function game() {
        return $this->hasOne('Game');
    }

}
