<?php

namespace Tests\Unit;

use App\Libraries\OltLockingService;
use CodeIgniter\Test\CIUnitTestCase;

class OltLockingServiceTest extends CIUnitTestCase
{
    public function testRunWithLockExecutesCallback()
    {
        $locker = new OltLockingService();
        $executed = false;

        $result = $locker->runWithLock(999, function () use (&$executed) {
            $executed = true;
            return 'OK';
        });

        $this->assertTrue($executed);
        $this->assertEquals('OK', $result);
    }
}
