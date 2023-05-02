<?php

namespace App\Models;

use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Finale extends Model
{
    use HasFactory;
    protected $table = "finale";

    public function team(){
        return $this->belongsTo(Team::class,'team_id','id');
    }
}
