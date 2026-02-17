<?php
/**
 * Base test case for all Pikari plugin tests.
 *
 * Extends PHPUnit TestCase with Brain\Monkey setup/teardown.
 * All test classes should extend this instead of PHPUnit\Framework\TestCase.
 *
 * Usage:
 *   use Pikari\Tests\TestCase;
 *   use Brain\Monkey\Functions;
 *
 *   class MyClassTest extends TestCase {
 *       public function test_something(): void {
 *           Functions\when( 'esc_html' )->returnArg();
 *           // ... your assertions
 *       }
 *   }
 *
 * @package Pikari\Tests
 */

namespace Pikari\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Brain\Monkey;

abstract class TestCase extends PHPUnitTestCase {

    /**
     * Set up Brain\Monkey before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Define common WordPress constants used across plugins.
        if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
            define( 'HOUR_IN_SECONDS', 3600 );
        }
        if ( ! defined( 'DAY_IN_SECONDS' ) ) {
            define( 'DAY_IN_SECONDS', 86400 );
        }
        if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
            define( 'WEEK_IN_SECONDS', 604800 );
        }
    }

    /**
     * Tear down Brain\Monkey after each test.
     */
    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }
}
