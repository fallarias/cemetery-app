<?php

namespace App\Models;

use App\Support\DeviceInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'route_name', 'method', 'url', 'details', 'changes',
        'ip_address', 'mac_address', 'device_type', 'user_agent', 'created_at',
    ];

    public static function record(Request $request): void
    {
        $ip = $request->ip();
        $ua = (string) $request->userAgent();

        $details = Arr::except($request->input(), [
            '_token', '_method', 'password', 'password_confirmation', 'current_password', 'pin',
        ]);

        static::create([
            'user_id'     => $request->user()?->id,
            'action'      => match (true) {
                $request->isMethod('DELETE')                                  => 'delete',
                $request->isMethod('PUT'), $request->isMethod('PATCH')        => 'update',
                default                                                       => 'create',
            },
            'route_name'  => $request->route()?->getName(),
            'method'      => $request->method(),
            'url'         => mb_substr($request->path(), 0, 500),
            'details'     => mb_substr((string) json_encode($details, JSON_UNESCAPED_UNICODE), 0, 5000),
            'ip_address'  => $ip,
            'mac_address' => DeviceInfo::mac($ip),
            'device_type' => DeviceInfo::type($ua),
            'user_agent'  => mb_substr($ua, 0, 500),
            'changes' => ($audit = $request->attributes->get('audit_changes'))
                            ? json_encode($audit, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR)
                            : null,
        ]);
    }
}
