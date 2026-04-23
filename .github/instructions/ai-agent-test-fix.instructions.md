Run all tests one by one and fix any failing tests.

## How to run a specific test
Use the command: vendor/bin/testbench package:test tests/foo/BarTest.php

## Steps to follow when tests fail
Fix the tests by updating the test route if it fails due to a 404 error, as we have updated many routes. You can run vendor/bin/testbench route:list to get our current routes.

We have made major changes, so check carefully if a test is failing due to an outdated test structure. If so, update the test first. If you feel that the test should be skipped temporarily, mark it with a comment: // TODO: {the reason why skipped} for manual review later.

- Run vendor/bin/testbench workbench:build
- Then run vendor/bin/testbench package:test tests/foo/FailedTest.php

Repeat the cycle until all tests pass.