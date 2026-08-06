<?php

namespace Tests\Unit;

use App\Libraries\SnmpService;
use CodeIgniter\Test\CIUnitTestCase;

class SnmpServiceTest extends CIUnitTestCase
{
    public function testInstantiation()
    {
        $snmp = new SnmpService('public', 2);
        $this->assertInstanceOf(SnmpService::class, $snmp);
    }

    public function testGetZteUnconfiguredOnusEmpty()
    {
        $snmp = new SnmpService('public', 2, 100, 1);
        $result = $snmp->getZteUnconfiguredOnus('127.0.0.1', 'public');
        $this->assertIsArray($result);
    }
}
