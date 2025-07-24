<?php

namespace YYCircle\JoinJob\Notification;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use YYCircle\JoinJob\Util\JPushNotification;

class JoinJobToEmployee extends Notification
{
    public $registrationIds;
    private $notifyType = 'approved_job';
    private $notifyTitle = "Your job application has been approved by YY Circle!";
    private $notifyMessage = "Your application has been approved by YY Circle!  Below is your job application information:";
    private  $notifyExtra = [];
    private $adhocJob;
    private $employee;
    public function __construct($adhcoJob,$employee)
    {
        $this->notifyExtra = [
            'job_title'                 => $adhcoJob->job_title ,
            'job_start_date'            => $adhcoJob->job_start_date ,
            'job_end_date'              => $adhcoJob->job_end_date ,
            'job_address'               => $adhcoJob->job_address ,
            'job_hour_rate'             => $adhcoJob->job_hour_rate ,
            'job_employer_company_name' => $adhcoJob->job_employer_company_name ,
            'job_contact_no'            => $adhcoJob->job_contact_no ,
            'job_contact_name'          => $adhcoJob->job_contact_name ,
        ];
        $this->adhocJob = $adhcoJob;
        $this->employee = $employee;
    }



    public  function joinInJob()
    {
        $this->toJpush();
        $this->toDb();
    }
    public function toJpush()
    {
        (new JPushNotification())->push(
            collect([$this->employee->registration_id])->filter()->values()->all(),
            $this->notifyTitle,
            $this->notifyMessage,
            $this->notifyType,
            $this->notifyExtra
        );
    }

    public function toDb()
    {
        return DB::table('member_notifications')->insert([
            'type'      => $this->notifyType,
            'member_id' => $this->employee->member_id,
            'title'     => $this->notifyTitle,
            'content'   => $this->notifyMessage,
            'job_id'    => $this->adhocJob->job_id,
            'data'      => json_encode($this->notifyExtra),
            'time'      => time(),
        ]);
    }
}