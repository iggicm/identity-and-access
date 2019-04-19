<?php
/**
 * Created by PhpStorm.
 * User: nkalla
 * Date: 26/02/19
 * Time: 20:41
 */

namespace App\Domain\Model\Access;


use Illuminate\Database\Eloquent\Model;

class AdministratorProposition extends Model
{
    public const CREATED = 'CREATED';
    public const ACCEPTED = 'ACCEPTED';

    protected $table = 'administratorpropositions';
    protected $fillable = ['administratorpropositionid', 'proposeduserid', 'proposed_by', 'administratorssignatures', 'state'];

}