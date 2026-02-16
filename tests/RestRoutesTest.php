<?php

use PHPUnit\Framework\TestCase;

class RestRoutesTest extends TestCase {
    protected function setUp(): void {
        global $ddo_test_state;
        $ddo_test_state['registered_routes'] = array();
        $ddo_test_state['current_user_can']  = false;
    }

    public function test_registers_expected_routes_with_permission_callback(): void {
        global $ddo_test_state;

        ddo_register_rest_routes();

        $routes = $ddo_test_state['registered_routes'];
        $this->assertCount( 3, $routes );

        foreach ( $routes as $route ) {
            $this->assertSame( 'ddo/v1', $route['namespace'] );
            $this->assertSame( 'ddo_api_manage_options_permission', $route['args']['permission_callback'] );
        }
    }

    public function test_permission_callback_checks_manage_options_capability(): void {
        global $ddo_test_state;

        $ddo_test_state['current_user_can'] = true;
        $this->assertTrue( ddo_api_manage_options_permission() );

        $ddo_test_state['current_user_can'] = false;
        $this->assertFalse( ddo_api_manage_options_permission() );
    }
}
