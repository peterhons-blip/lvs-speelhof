<?php
namespace App\Support;

final class StudentSyncSafety
{
    public static function complete(int $failed, int $limit, string $onlyClass, int $received): bool
    {
        return $failed===0 && $limit===0 && $onlyClass==='' && $received>0;
    }

    public static function plausible(int $active, int $missing): bool
    {
        return $active>=0 && $missing>=0 && $missing<=$active && $missing<=max(5,(int)floor($active*0.10));
    }
}
