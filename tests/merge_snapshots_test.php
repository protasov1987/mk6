<?php
require_once __DIR__ . '/../helpers.php';

function assert_unique_codes(array $ops): void
{
    $codes = array_filter(array_map(fn($op) => $op['code'] ?? '', $ops));
    $unique = array_unique($codes);
    if (count($codes) !== count($unique)) {
        throw new RuntimeException('Duplicate operation codes found in snapshot');
    }
}

function simulate_parallel_posts_with_same_codes(): void
{
    $initial = build_default_data();

    $newCode = 'OP-PARALLEL';
    $incomingA = $initial;
    $incomingA['ops'][] = ['id' => gen_id('op'), 'code' => $newCode, 'name' => 'Оп A'];

    $incomingB = $initial;
    $incomingB['ops'][] = ['id' => gen_id('op'), 'code' => $newCode, 'name' => 'Оп B'];

    $stateAfterFirst = merge_snapshots($initial, $incomingA);
    ensure_operation_codes($stateAfterFirst);

    $stateAfterSecond = merge_snapshots($stateAfterFirst, $incomingB);
    ensure_operation_codes($stateAfterSecond);

    assert_unique_codes($stateAfterSecond['ops'] ?? []);

    $codes = array_map(fn($op) => $op['code'] ?? '', $stateAfterSecond['ops'] ?? []);
    $opParallelCount = count(array_filter($codes, fn($code) => $code === $newCode));
    if ($opParallelCount > 1) {
        throw new RuntimeException('Parallel posts produced duplicate operation codes');
    }
}

simulate_parallel_posts_with_same_codes();

echo "OK\n";
