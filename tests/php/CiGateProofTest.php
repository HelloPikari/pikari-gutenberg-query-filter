<?php
/**
 * Deliberate failure proving the CI Test job blocks merges. Reverted in the next commit.
 *
 * @package Pikari\Tests\GutenbergQueryFilter
 */

namespace Pikari\Tests\GutenbergQueryFilter;

use Pikari\Tests\TestCase;

class CiGateProofTest extends TestCase {

    public function test_ci_gate_blocks_on_failure(): void {
        $this->fail( 'Deliberate failure: proves a red Test job blocks the PR.' );
    }
}
