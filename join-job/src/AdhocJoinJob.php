<?php
namespace YYCircle\JoinJob;

class AdhocJoinJob
{
    protected $jobID;
    protected $memberID;
    public function __construct($jobID,$memberID)
    {
        $this->jobID = $jobID;
        $this->memberID = $memberID;
    }
    public function join()
    {
        //这里是加入工作
        return "is join job";
    }
}