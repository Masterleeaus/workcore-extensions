<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\AttendanceVerification\Domain;

enum VerificationMethod: string
{
    case Manual = 'manual';
    case Geofence = 'geofence';
    case StaticQr = 'static_qr';
    case DynamicQr = 'dynamic_qr';
    case Face = 'face';
    case IpAddress = 'ip_address';
    case Offline = 'offline';
}
