<?php
namespace Tests\Unit;

use App\Support\StudentSyncSafety;
use PHPUnit\Framework\TestCase;

final class StudentSyncSafetyTest extends TestCase
{
    public function test_complete_school_sync_with_two_missing_is_safe(): void
    {
        self::assertTrue(StudentSyncSafety::complete(0,0,'',644));
        self::assertTrue(StudentSyncSafety::plausible(646,2));
    }

    public function test_failed_limited_partial_or_empty_sync_is_blocked(): void
    {
        self::assertFalse(StudentSyncSafety::complete(1,0,'',644));
        self::assertFalse(StudentSyncSafety::complete(0,10,'',10));
        self::assertFalse(StudentSyncSafety::complete(0,0,'3ME',13));
        self::assertFalse(StudentSyncSafety::complete(0,0,'',0));
    }

    public function test_mass_drop_is_blocked(): void
    {
        self::assertTrue(StudentSyncSafety::plausible(646,64));
        self::assertFalse(StudentSyncSafety::plausible(646,65));
        self::assertFalse(StudentSyncSafety::plausible(646,646));
    }
}
