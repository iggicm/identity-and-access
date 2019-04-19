<?php
/**
 * Created by PhpStorm.
 * User: nkalla
 * Date: 02/03/19
 * Time: 20:40
 */

namespace App\Domain\Model\Identity;


use Illuminate\Database\Eloquent\Model;

class UserRevokationProposition extends Model
{

    public const CREATED = 'CREATED';
    public const ACCEPTED = 'ACCEPTED';

    protected $table = 'userrevokationpropositions';
    protected $fillable = ['userrevokationpropositionid', 'proposeduserid', 'proposed_by', 'administratorssignatures', 'state'];

}