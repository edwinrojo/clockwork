<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserPermission: string implements HasLabel
{
    case ACTIVITY = 'activity';
    case EMPLOYEE = 'employee';
    case GROUP = 'group';
    case HOLIDAY = 'holiday';
    case OFFICE = 'office';
    case REQUEST = 'request';
    case SCANNER = 'scanner';
    case SCHEDULE = 'schedule';
    case SETTING = 'setting';
    case SIGNATURE = 'signature';
    case TIMELOG = 'timelog';
    case TIMESHEET = 'timesheet';
    case USER = 'user';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ACTIVITY => 'Activity',
            self::EMPLOYEE => 'Employee',
            self::GROUP => 'Group',
            self::HOLIDAY => 'Holiday',
            self::OFFICE => 'Office',
            self::REQUEST => 'Request',
            self::SCANNER => 'Scanner',
            self::SCHEDULE => 'Schedule',
            self::SETTING => 'Setting',
            self::SIGNATURE => 'Signature',
            self::TIMELOG => 'Timelog',
            self::TIMESHEET => 'Timesheet',
            self::USER => 'User',
            default => null,
        };
    }
}
