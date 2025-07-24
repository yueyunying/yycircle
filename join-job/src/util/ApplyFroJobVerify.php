<?php

namespace YYCircle\JoinJob\Util;

use App\Constants\AdhocSchedule\WorkStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function JmesPath\search;

class ApplyFroJobVerify
{
    public static function verify ($adhocJob,$employee)
    {
        self::notJob($adhocJob);
        self::notEmployee($employee);
        self::workDate($adhocJob);
        self::joinJob($adhocJob,$employee);
        self::hasOverlap($adhocJob,$employee);
        self::employement($adhocJob,$employee);
        self::weeklyWorkingHours($adhocJob,$employee);
        self::hoursPerFortnight($adhocJob,$employee);
        self::employerRestrictions($adhocJob,$employee);
    }



    /**
     * 工作是否存在
     * @param $adhocJob
     * @return void
     * @throws ValidationException
     */
    public static function notJob($adhocJob)
    {
        if (!$adhocJob) {
            self::message('Job Not fond');
        }
    }

    /**
     * Employee not  exist
     * @param $employee
     * @return void
     * @throws ValidationException
     */
    public static function notEmployee($employee)
    {
        if (!$employee) {
            self::message('Employee Not fond');
        }
    }

    /**
     * 工作时间
     * @param $adhocJob
     * @return void
     * @throws ValidationException
     */
    public static function workDate($adhocJob)
    {
        if (Carbon::createFromTimestamp($adhocJob->job_start_date) < now()->subMonth()->startOfMonth()){
            self::message('This job has been finished for more than a month, and no further additions are allowed');
        }
    }
    public static function joinJob($adhocJob,$employee)
    {
        $schedule = DB::table('job_schedules')->where('job_id',$adhocJob->job_id)->where('member_id',$employee->member_id)->first();
        if ($schedule){
            if ($schedule->work_status == 3)
            {
                self::message('Sorry, you have applied for this job, but it was rejected by the administrator');
            }
            if ($schedule->work_status == 4)
            {
                self::message('Sorry, the job you applied for has been automatically cancelled by the system because it has not been checkout for a long time');
            }
            if($schedule->work_status == 9){
                self::message('You will not be able to apply for this job again as you have cancelled previously.');
            }
            if ($schedule->work_status == 10){
                self::message('Sorry, because you rejected this job when the system assigned you, you cannot apply for this job again.');
            }
            if ($schedule->work_status != 16) {
                self::message( 'You have already applied for this job');
            }
        }
    }
    /**
     * 验证 overlap
     * @param $adhocJob
     * @param $employee
     * @return void
     * @throws ValidationException
     */
    public static function hasOverlap($adhocJob,$employee)
    {
        $schedule = DB::table('job_schedules as js')
            ->leftJoin('job as j','js.job_id','j.job_id')
            ->where('js.member_id',$employee->member_id)
            ->where('j.job_status',4)
            ->get();
        $overlap = $schedule->map(function ($schedule)use ($adhocJob){
            if ($schedule){
                return (int)self::hasOverlapWithOtherJob(
                    $schedule->job_start_date,
                    $schedule->job_end_date,
                    $adhocJob->job_start_date,
                    $adhocJob->job_end_date
                );
            }
            return 0;
        })->filter()->contains(1);
        if ($overlap){
            self::message('You have a schedule that overlaps with this job start date or end date!');
        }
    }

    /**
     * 验证 employement
     * @param $adhocJob
     * @param $employee
     * @return void
     * @throws ValidationException
     */

    public static function employement($adhocJob,$employee)
    {
        if ($adhocJob->employement_status && $adhocJob->employement_status != 'Other'){
            $memberEmployement = DB::table('member_info')->where('member_id',$employee->member_id)->value('employement_status');
            if (!collect(explode(',',$adhocJob->employement_status))->contains($memberEmployement)){
                self::message('Right to Work does not meet requirements.');
            }
        }
    }
    public static function weeklyWorkingHours($adhocJob,$employee)
    {
        [$start,$end] = [Carbon::createFromTimestamp($adhocJob->job_start_date)->startOfWeek(),Carbon::createFromTimestamp($adhocJob->job_start_date)->addDays(7)->endOfWeek()];
        $totalWorkMinute = self::totalWorkMinute($employee,$adhocJob,$start,$end) / 60;
        if ($totalWorkMinute  >= 38){
            self::message('You have exceeded the weekly working hours.');
        }
    }
    public static function hoursPerFortnight($adhocJob,$employee)
    {
        $hoursPerFortnight = DB::table('member_certificate')
            ->where('member_id',$employee->member_id)->where('hours_per_fortnight','>',0)->value('hours_per_fortnight');
        if ($hoursPerFortnight){
            [$start,$end] = [Carbon::createFromTimestamp($adhocJob->job_start_date)->subDays(7)->startOfDay(),Carbon::createFromTimestamp($adhocJob->job_start_date)->addDays(7)->endOfDay()];
            $totalHour = self::totalWorkMinute($employee,$adhocJob,$start,$end) / 60 ;
            if ($totalHour > $hoursPerFortnight){
                self::message(sprintf('Working hours cannot exceed %s hours within two weeks',$hoursPerFortnight));
            }
        }
    }
    public static function employerRestrictions($adhocJob,$employee)
    {
        //WorkStatus::$applied
        $employerRestrictions = DB::table('member_certificate')->where('member_id',$employee->member_id)->where('employer_restrictions','>',0)->value('employer_restrictions');
        if ($employerRestrictions){
            $firstWorkDateTime = DB::table('job_schedules as js')
                ->leftJoin('job as j','js.job_id','j.job_id')
                ->where('member_id',$employee->member_id)
                ->where('j.job_employer_admin_id',$adhocJob->job_employer_admin_id)
                ->whereIn('work_status',[2,5,6,8,11,13,14])
                ->orderBy('s_id','Asc')
                ->value('adjusted_checkin_time');
            if ($firstWorkDateTime){
                $diffDays = Carbon::createFromTimestamp($firstWorkDateTime)->diffInDays(Carbon::createFromTimestamp($adhocJob->job_start_date));
                $employerRestrictionsDays = $employerRestrictions * 30;
                if ($diffDays > $employerRestrictionsDays){
                    self::message(sprintf('First job and current job have been over %s months',$employerRestrictions));
                }
            }
        }
    }

    /**
     * 验证 overlap
     * @param $start1
     * @param $end1
     * @param $start2
     * @param $end2
     * @return bool
     */

    public static function hasOverlapWithOtherJob($start1,$end1,$start2,$end2)
    {
        return ($start1) <  ($end2) && ($start2) < ($end1);
    }

    public static function message($message)
    {
        throw ValidationException::withMessages(['error' => $message]);
    }

    public static function totalWorkMinute($employee,$adhocJob,$start,$end)
    {
        $totalMinute = 0;
        $schedules = DB::table('job_schedules as js')
            ->leftJoin('job as j','js.job_id','j.job_id')
            ->where('js.member_id',$employee->member_id)
            ->where('j.job_status',4)
            ->whereBetween('adjusted_checkin_time',[Carbon::parse($start)->timestamp,Carbon::parse($end)->timestamp])
            ->get(['js.adjusted_checkin_time','js.adjusted_checkout_time']);
        $schedules->map(function ($schedule) use (&$totalMinute){
            $totalMinute += Carbon::createFromTimestamp($schedule->adjusted_checkout_time)->diffInMinutes(Carbon::createFromTimestamp($schedule->adjusted_checkin_time));
        });
        $jobDiffMinute = Carbon::createFromTimestamp($adhocJob->job_start_date)->diffInMinutes(Carbon::createFromTimestamp($adhocJob->job_end_date));
        return $totalMinute + $jobDiffMinute;
    }
}