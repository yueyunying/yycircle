<?php

namespace YYCircle\JoinJob\Util;

class FillDataTransformer
{
    public $adhocJob;
    public $employee;
    public $source;
    public function __construct($adhocJob,$employee,$source)
    {
        $this->adhocJob = $adhocJob;
        $this->employee = $employee;
        $this->source = $source;
    }
    public function transform(): array
    {
        $data['schedule'] = [
            'job_id'                 => $this->adhocJob->job_id,
            'member_id'              => $this->employee->member_id,
            'member_name'            => $this->employee->member_name,
            'adjusted_checkin_time'  => $this->adhocJob->job_start_date,
            'adjusted_checkout_time' => $this->adhocJob->job_end_date,
            'break_time_from'        => $this->adhocJob->break_time_from ? Carbon::parse($this->adhocJob->break_time_from)->timestamp: 0,
            'break_time_to'          => $this->adhocJob->break_time_to ? Carbon::parse($this->adhocJob->break_time_to)->timestamp: 0,
            'is_send'                => 1,
            'add_time'               => now()->timestamp,
            'source'                 => $this->source,
            'adjusted_hourly_rate'   => $this->adhocJob->job_hour_rate,
            'work_status'            => $this->adhocJob->employer_status != 1 ? 13 : 2,
            'cancel_status'          => 0,
            'cancel_reason'          => '',
            'agency_rate'            => $this->adhocJob->agency_rate,
        ];
        $data['job'] = [];
        if ($this->adhocJob->employer_status != 1){
            $data['job'] = [
                'job_update_time' => now()->timestamp,
                'revised_date' => now(),
                'employer_status' => 2,
            ];
        }
        return $data;
    }
}