<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RestApiTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wpcc_test_can_manage'] = false;
    }

    public function testConversionEndpointsRequireAdministratorByDefault(): void
    {
        $api = new WPCC_Rest_Api();
        self::assertFalse($api->check_permission(new stdClass()));

        $GLOBALS['wpcc_test_can_manage'] = true;
        self::assertTrue($api->check_permission(new stdClass()));
    }

    public function testPayloadLimits(): void
    {
        $api = new WPCC_Rest_Api();

        self::assertTrue($api->validate_text(str_repeat('a', 100000)));
        self::assertFalse($api->validate_text(str_repeat('a', 100001)));
        self::assertTrue($api->validate_text_batch(['网络', '軟體']));
        self::assertFalse($api->validate_text_batch(array_fill(0, 101, 'a')));
        self::assertFalse($api->validate_text_batch([str_repeat('a', 500001)]));
        self::assertFalse($api->validate_text_batch(['ok', 123]));
    }
}
