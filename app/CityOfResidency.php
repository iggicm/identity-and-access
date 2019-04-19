<?php
/**
 * Created by PhpStorm.
 * User: nkalla
 * Date: 01/02/19
 * Time: 18:24
 */

namespace App;


use Illuminate\Database\Eloquent\Model;

class CityOfResidency extends Model
{
    protected $table = 'cityofresidencies';
    protected $fillable = ['cityofresidencyid', 'name', 'countrycode'];

    public function __construct($cityofresidencyid = null, $name = null, $countrycode = null, array $attributes = [])
    {
        parent::__construct($attributes);
        $this->cityofresidencyid = $cityofresidencyid;
        $this->name = $name;
        $this->countrycode = $countrycode;
    }

    public function getCountry(){
        return Country::where('code', '=', $this->countrycode)->first();
    }
}