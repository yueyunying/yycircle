<?php
namespace YYCircle\JoinJob;

use Illuminate\Support\Facades\DB;
use YYCircle\JoinJob\Notification\JoinJobToEmployee;
use YYCircle\JoinJob\Util\ApplyFroJobVerify;
use Illuminate\Validation\ValidationException;
use YYCircle\JoinJob\Util\FillDataTransformer;
use YYCircle\JoinJob\Util\PassAuth;

class AdhocJoinJob
{
    protected $adhocJob;
    protected $employee;
    protected $jobID;
    protected $memberID;
    protected $source;
    public function __construct($jobID,$memberID,$source)
    {
        $this->adhocJob = DB::table('job')->where('job_id',$jobID)->first();
        $this->employee = DB::table('member')->where('member_id',$memberID)->first();
        $this->jobID = $jobID;
        $this->memberID = $memberID;
        $this->source = $source;
    }
    public function join()
    {
        ApplyFroJobVerify::verify($this->adhocJob,$this->employee);
        $schedule =  $this->store();
        PassAuth::create($this->adhocJob,$this->employee);
        (new JoinJobToEmployee($this->adhocJob,$this->employee))->joinInJob();
        return $schedule;
    }

    public function store()
    {
        $data = (new FillDataTransformer($this->adhocJob,$this->employee,$this->source))->transform();

        $sId = DB::table('job_schedules')->insertGetId($data['schedule']);
        if ($data['job']){
            DB::table('job')->where('job_id',$this->jobID)->update($data['job']);
        }
        return DB::table('job_schedules')->where('s_id',$sId)->first();
    }



}