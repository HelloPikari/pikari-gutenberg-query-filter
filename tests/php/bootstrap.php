<?php
/**
 * PHPUnit bootstrap file.
 *
 * Loads the Composer autoloader and base TestCase class.
 * Brain\Monkey handles WordPress function mocking — no WordPress installation needed.
 *
 * @package Pikari\Tests
 */

// Load Composer autoloader (provides Brain\Monkey, Mockery, and plugin classes).
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

// Load the base TestCase class (handles Brain\Monkey setUp/tearDown).
require_once __DIR__ . '/TestCase.php';
