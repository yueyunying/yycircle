<?php

namespace YYCircle\JoinJob\Util;

use Illuminate\Support\Facades\DB;

class PassAuth
{
    public static function create($adhocJob,$employee)
    {
        $employerEId = DB::table('employer')->where('e_admin_id',$adhocJob->job_employer_admin_id)->value('e_id');
        $devices = DB::table('co_devices')->where('company_id',$employerEId)->get();
        foreach ($devices as  $device){
            $devicePassAuth = DB::table('co_pass_auth')->where( 'device_id', $device->id)->where('staff_id',$employee->member_id)->first();
            DB::table('co_pass_auth')->updateOrInsert([
                'device_id' => $device->id,
                'staff_id'  => $employee->member_id
            ],[
                    'staff_name'  => $employee->member_name,
                    'photo'       => $employee->member_avatar,
                    'auth_status' => $devicePassAuth && $devicePassAuth->auth_status == 3 ? 0 : DB::raw('auth_status'),
                    'push_status' => $devicePassAuth && $devicePassAuth->auth_status == 3 ? 0 : DB::raw('push_status'),
                    'sync_status' => $devicePassAuth && $devicePassAuth->auth_status == 3 ? 0 : DB::raw('sync_status'),
                    'updated_at'  => now(),
                    'company_id'  => $device->company_id,
                    'device_id'   => $device->id,
            ]);
        }

    }
}