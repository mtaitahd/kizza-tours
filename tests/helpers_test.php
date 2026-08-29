<?php
/**
 * CLI test/smoke harness for the shared tour-list helpers in includes/config.php.
 *
 * Run from the project root:
 *   php tests/helpers_test.php
 * Prints "All tour-list helper tests passed." on success; exits non-zero otherwise.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config.php';

$failures = 0;
$checks = 0;

function ok($condition, $name) {
    global $failures, $checks;
    $checks++;
    if ($condition) {
        echo "  ok  - $name\n";
    } else {
        $failures++;
        echo "FAIL  - $name\n";
    }
}

function eq($actual, $expected, $name) {
    ok($actual === $expected, $name . ' => ' . json_encode($actual));
}

echo "tourListItems():\n";
eq(tourListItems(''), [], 'empty string -> []');
eq(tourListItems(null), [], 'null -> []');
eq(tourListItems(['a', 'b']), ['a', 'b'], 'plain array passthrough');
eq(tourListItems('["alpha","beta","alpha"]'), ['alpha', 'beta'], 'JSON array deduped');
eq(tourListItems('[" alpha ","beta"]'), ['alpha', 'beta'], 'JSON items trimmed');
eq(tourListItems('["[broken"'), ['["[broken"'], 'malformed JSON falls back to comma handling without crashing');
eq(tourListItems('one, two,,three'), ['one', 'two', 'three'], 'legacy comma string');
eq(tourListItems("one\ntwo", ), ['one', 'two'], 'legacy newline string');
eq(tourListItems('["one","two"]'), ['one', 'two'], 'quoted JSON array');
eq(tourListItems(['', 'x', ' x ']), ['x'], 'array with empties/dupes');

echo "tourListStorage():\n";
eq(tourListStorage(['One', 'Two', 'one']), json_encode(['One', 'Two']), 'array deduped to JSON');
eq(tourListStorage([]), '', 'empty array -> ""');
eq(tourListStorage('alpha, beta'), '["alpha","beta"]', 'legacy comma string -> JSON');
eq(tourListStorage("alpha\nbeta"), '["alpha","beta"]', 'newline string -> JSON');
eq(tourListStorage('["x","y"]'), '["x","y"]', 'JSON passthrough normalized');

echo "round-trip (storage -> items):\n";
$stored = tourListStorage(['Safari', 'Lodge', 'Safari']);
eq(tourListItems($stored), ['Safari', 'Lodge'], 'round trip equality');

echo "\n$checks checks, $failures failures\n";
exit($failures > 0 ? 1 : 0);