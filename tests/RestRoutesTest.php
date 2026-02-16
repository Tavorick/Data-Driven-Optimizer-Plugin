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

        $callbacks_by_route = array();
        foreach ( $routes as $route ) {
            $this->assertSame( 'ddo/v1', $route['namespace'] );
            $callbacks_by_route[ $route['route'] ] = $route['args']['permission_callback'];
        }

        $this->assertSame( 'ddo_api_manage_options_permission', $callbacks_by_route['/status'] );
        $this->assertSame( 'ddo_api_feedback_permission', $callbacks_by_route['/feedback'] );
        $this->assertSame( 'ddo_api_manage_options_permission', $callbacks_by_route['/feedback/summary'] );
    }

    public function test_permission_callback_checks_manage_options_capability(): void {
        global $ddo_test_state;

        $ddo_test_state['current_user_can'] = true;
        $this->assertTrue( ddo_api_manage_options_permission() );

        $ddo_test_state['current_user_can'] = false;
        $this->assertFalse( ddo_api_manage_options_permission() );
    }
}
