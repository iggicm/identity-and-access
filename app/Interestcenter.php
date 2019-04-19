<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Interestcenter extends Model
{
    protected $table = 'interestcenters';
    protected $fillable = ['interestcenterid', 'name', 'description'];
}
