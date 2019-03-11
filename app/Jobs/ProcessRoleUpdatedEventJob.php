<?php

namespace App\Jobs;

use App\Domain\event\GroupAddedToRole;
use App\Domain\event\UserAddedToRole;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Webpatser\Uuid\Uuid;

class ProcessRoleUpdatedEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected  $type;
    protected  $ids;
    protected  $roleid;
    protected  $added_by;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($type, $roleid, $added_by, $ids)
    {
        $this->type = $type;
        $this->roleid = $roleid;
        $this->added_by = $added_by;
        $this->ids = $ids;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        DB::beginTransaction();
        try{
            if ($this->type == env('GROUPS_ADDED_TO_ROLE')){

                foreach ($this->ids as $id){
                    $groupaddedtorole = new GroupAddedToRole(Uuid::generate()->string,$this->roleid, $id->groupid, $this->added_by);
                    $groupaddedtorole->save();
                }
            }

            if ($this->type == env('USERS_ADDED_TO_ROLE')){


                foreach ($this->ids as $id){
                    $useraddedtorole = new UserAddedToRole(Uuid::generate()->string,$this->roleid, $id->userid, $this->added_by);
                    $useraddedtorole->save();

                }
            }
        }catch (\Exception $e){
            DB::rollBack();
        }
        DB::commit();
    }
}
