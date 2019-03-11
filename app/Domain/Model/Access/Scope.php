<?php
/**
 * Created by PhpStorm.
 * User: el
 * Date: 6/29/18
 * Time: 11:36 AM
 */

namespace App\Domain\Model\Access;


use Illuminate\Database\Eloquent\Model;

class Scope extends Model
{

    protected $table = 'scopes';
    protected $fillable = ['s_key', 'description'];


    public function __construct($s_key = null, $description = null,  array $attributes = [])
    {
        parent::__construct($attributes);

        $this->s_key = $s_key;
        $this->description = $description;
    }
}
