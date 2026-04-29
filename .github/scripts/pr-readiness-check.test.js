'use strict';

const {describe, it} = require('node:test');
const assert = require('node:assert/strict');
const {
    validatePrChecklist,
    getChecked,
    getUnchecked,
    hasNonEmptySection,
} = require('./pr-readiness-check.js');

// ── Test fixtures ─────────────────────────────────────────────

function validBody({
                       type = '`fix` — bug fix',
                       testing = '`vendor/bin/phpunit --exclude-group=ExternalServices --no-coverage` passes',
                       regression = true,
                       ai = 'No AI tools were used in this PR',
                       migrationSection = false,
                       migrationChecked = false,
                       compat = null,
                       notes = '',
                   } = {}) {
    let body = `## Summary\n\nFix something.\n\n## Type\n\n- [x] ${type}\n\n`;
    body += `## Testing\n\n- [x] ${testing}\n`;
    if (regression) {
        body += '- [x] Regression tests added for bug fixes\n';
    }
    body += '\n';
    body += `## AI Disclosure\n\n- [x] ${ai}\n\n`;

    if (migrationSection) {
        body += '## Migration Notes\n\n';
        if (migrationChecked) {
            body += '- [x] Migration file added in `migrations/` directory\n';
            body += '- [x] Tested on a fresh database and on an existing one\n';
        } else {
            body += '- [ ] Migration file added in `migrations/` directory\n';
            body += '- [ ] Tested on a fresh database and on an existing one\n';
        }
        if (compat === 'backward') {
            body += '- [x] Backward-compatible with current production schema\n';
            body += '- [ ] NOT backward-compatible — breaking changes documented in Notes section\n';
        } else if (compat === 'breaking') {
            body += '- [ ] Backward-compatible with current production schema\n';
            body += '- [x] NOT backward-compatible — breaking changes documented in Notes section\n';
        } else if (compat === 'both') {
            body += '- [x] Backward-compatible with current production schema\n';
            body += '- [x] NOT backward-compatible — breaking changes documented in Notes section\n';
        } else {
            body += '- [ ] Backward-compatible with current production schema\n';
            body += '- [ ] NOT backward-compatible — breaking changes documented in Notes section\n';
        }
    }

    body += `## Notes\n\n${notes}\n`;

    return body;
}

const TEST_FILES = ['tests/unit/SomeTest.php'];
const TEST_FILES_JS_COLOCATED = ['public/js/components/Foo.test.js'];

// ── Helper tests ──────────────────────────────────────────────

describe('getChecked', () => {
    it('finds checked items with [x]', () => {
        const body = '- [x] alpha\n- [ ] beta\n- [X] gamma';
        assert.deepEqual(getChecked(['alpha', 'beta', 'gamma'], body), ['alpha', 'gamma']);
    });

    it('returns empty when nothing is checked', () => {
        const body = '- [ ] alpha\n- [ ] beta';
        assert.deepEqual(getChecked(['alpha', 'beta'], body), []);
    });

    it('handles regex special characters in item text', () => {
        const body = '- [x] `vendor/bin/phpunit --exclude-group=ExternalServices --no-coverage` passes';
        const result = getChecked(
            ['`vendor/bin/phpunit --exclude-group=ExternalServices --no-coverage` passes'],
            body,
        );
        assert.equal(result.length, 1);
    });
});

describe('getUnchecked', () => {
    it('finds unchecked items', () => {
        const body = '- [x] alpha\n- [ ] beta';
        assert.deepEqual(getUnchecked(['alpha', 'beta'], body), ['beta']);
    });
});

describe('hasNonEmptySection', () => {
    it('returns true when section has content', () => {
        const body = '## Notes\n\nBreaking changes: removed column X.\n';
        assert.equal(hasNonEmptySection('Notes', body), true);
    });

    it('returns false when section is empty', () => {
        const body = '## Notes\n\n';
        assert.equal(hasNonEmptySection('Notes', body), false);
    });

    it('returns false when section only has HTML comments', () => {
        const body = '## Notes\n\n<!-- leave blank if none -->\n';
        assert.equal(hasNonEmptySection('Notes', body), false);
    });

    it('returns false when section is missing', () => {
        assert.equal(hasNonEmptySection('Notes', '## Summary\n\nHello\n'), false);
    });

    it('returns true when section has content after a comment', () => {
        const body = '## Notes\n\n<!-- context -->\nActual breaking change here.\n';
        assert.equal(hasNonEmptySection('Notes', body), true);
    });
});

// ── validatePrChecklist ───────────────────────────────────────

describe('validatePrChecklist', () => {
    describe('valid PRs', () => {
        it('passes with all required sections filled (fix)', () => {
            const errors = validatePrChecklist(validBody(), {testFilesWithAdditions: TEST_FILES});
            assert.deepEqual(errors, []);
        });

        it('passes with feat type and test files', () => {
            const body = validBody({type: '`feat` — new user-facing feature', regression: false});
            const errors = validatePrChecklist(body, {testFilesWithAdditions: TEST_FILES});
            assert.deepEqual(errors, []);
        });

        it('passes with refactor type and no test files', () => {
            const body = validBody({type: '`refactor` — restructure without behavior change', regression: false});
            const errors = validatePrChecklist(body, {testFilesWithAdditions: []});
            assert.deepEqual(errors, []);
        });

        it('passes with AI tools used', () => {
            const body = validBody({ai: 'AI tools were used — details below'});
            const errors = validatePrChecklist(body, {testFilesWithAdditions: TEST_FILES});
            assert.deepEqual(errors, []);
        });
    });

    describe('Type section', () => {
        it('fails when no type is selected', () => {
            const body = '## Summary\n\n## Testing\n\n- [x] Manual testing performed (describe below)\n\n## AI Disclosure\n\n- [x] No AI tools were used in this PR\n## Notes\n\n';
            const errors = validatePrChecklist(body, {});
            assert.ok(errors.some((e) => e.includes('No PR type selected')));
        });

        it('fails when multiple types are selected', () => {
            let body = validBody();
            body = body.replace('## Type\n\n- [x] `fix` — bug fix', '## Type\n\n- [x] `fix` — bug fix\n- [x] `feat` — new user-facing feature');
            const errors = validatePrChecklist(body, {testFilesWithAdditions: TEST_FILES});
            assert.ok(errors.some((e) => e.includes('Multiple PR types selected')));
        });
    });

    describe('Testing section', () => {
        it('fails when no testing item is checked', () => {
            const body = '## Summary\n\n## Type\n\n- [x] `chore` — build, deps, config, docs\n\n## Testing\n\n- [ ] Manual testing performed (describe below)\n\n## AI Disclosure\n\n- [x] No AI tools were used in this PR\n## Notes\n\n';
            const errors = validatePrChecklist(body, {});
            assert.ok(errors.some((e) => e.includes('No testing items checked')));
        });
    });

    describe('fix → regression tests mandatory', () => {
        it('fails when type is fix but regression checkbox not checked', () => {
            const body = validBody({regression: false});
            const errors = validatePrChecklist(body, {testFilesWithAdditions: TEST_FILES});
            assert.ok(errors.some((e) => e.includes('Regression tests added for bug fixes')));
        });

        it('passes when type is fix and regression checkbox is checked', () => {
            const errors = validatePrChecklist(validBody(), {testFilesWithAdditions: TEST_FILES});
            assert.deepEqual(errors, []);
        });

        it('does not require regression checkbox for non-fix types', () => {
            const body = validBody({type: '`feat` — new user-facing feature', regression: false});
            const errors = validatePrChecklist(body, {testFilesWithAdditions: TEST_FILES});
            assert.ok(!errors.some((e) => e.includes('Regression tests')));
        });
    });

    describe('feat/fix → test files must be in diff', () => {
        it('fails when type is fix but no test files with additions', () => {
            const body = validBody();
            const errors = validatePrChecklist(body, {testFilesWithAdditions: []});
            assert.ok(errors.some((e) => e.includes('no test files with added lines')));
        });

        it('fails when type is feat but no test files with additions', () => {
            const body = validBody({type: '`feat` — new user-facing feature', regression: false});
            const errors = validatePrChecklist(body, {testFilesWithAdditions: []});
            assert.ok(errors.some((e) => e.includes('no test files with added lines')));
        });

        it('passes when type is fix and test files have additions', () => {
            const errors = validatePrChecklist(validBody(), {testFilesWithAdditions: ['tests/unit/FooTest.php']});
            assert.deepEqual(errors, []);
        });

        it('passes when type is fix and co-located JS test files have additions', () => {
            const errors = validatePrChecklist(validBody(), {testFilesWithAdditions: TEST_FILES_JS_COLOCATED});
            assert.deepEqual(errors, []);
        });

        it('does not require test files for chore type', () => {
            const body = validBody({type: '`chore` — build, deps, config, docs', regression: false});
            const errors = validatePrChecklist(body, {testFilesWithAdditions: []});
            assert.ok(!errors.some((e) => e.includes('no test files')));
        });

        it('does not require test files for refactor type', () => {
            const body = validBody({type: '`refactor` — restructure without behavior change', regression: false});
            const errors = validatePrChecklist(body, {testFilesWithAdditions: []});
            assert.ok(!errors.some((e) => e.includes('no test files')));
        });
    });

    describe('AI Disclosure section', () => {
        it('fails when no AI option is selected', () => {
            const body = '## Summary\n\n## Type\n\n- [x] `chore` — build, deps, config, docs\n\n## Testing\n\n- [x] Manual testing performed (describe below)\n\n## AI Disclosure\n\n- [ ] No AI tools were used in this PR\n- [ ] AI tools were used — details below\n## Notes\n\n';
            const errors = validatePrChecklist(body, {});
            assert.ok(errors.some((e) => e.includes('AI disclosure not filled')));
        });

        it('fails when both AI options are selected', () => {
            const body = '## Summary\n\n## Type\n\n- [x] `chore` — build, deps, config, docs\n\n## Testing\n\n- [x] Manual testing performed (describe below)\n\n## AI Disclosure\n\n- [x] No AI tools were used in this PR\n- [x] AI tools were used — details below\n## Notes\n\n';
            const errors = validatePrChecklist(body, {});
            assert.ok(errors.some((e) => e.includes('Both AI disclosure options')));
        });
    });

    describe('Migration — bidirectional enforcement', () => {
        it('fails when section present but no migration files in diff', () => {
            const body = validBody({migrationSection: true, migrationChecked: true, compat: 'backward'});
            const errors = validatePrChecklist(body, {migrationFilenames: [], testFilesWithAdditions: TEST_FILES});
            assert.ok(errors.some((e) => e.includes('no migration file found')));
        });

        it('fails when migration files in diff but no section in body', () => {
            const body = validBody();
            const errors = validatePrChecklist(body, {
                migrationFilenames: ['migrations/20260420120000_add_column.php'],
                testFilesWithAdditions: TEST_FILES,
            });
            assert.ok(errors.some((e) => e.includes('Migration Notes section is missing')));
        });

        it('lists the migration filenames when section is missing', () => {
            const body = validBody();
            const files = {
                migrationFilenames: ['migrations/20260420120000_add_column.php', 'migrations/20260420130000_add_index.php'],
                testFilesWithAdditions: TEST_FILES,
            };
            const errors = validatePrChecklist(body, files);
            const migError = errors.find((e) => e.includes('Migration Notes section is missing'));
            assert.ok(migError.includes('20260420120000_add_column.php'));
            assert.ok(migError.includes('20260420130000_add_index.php'));
        });

        it('passes when no section and no migration files', () => {
            const errors = validatePrChecklist(validBody(), {testFilesWithAdditions: TEST_FILES});
            assert.deepEqual(errors, []);
        });
    });

    describe('Migration — checklist completeness', () => {
        it('fails when required items are unchecked', () => {
            const body = validBody({migrationSection: true, migrationChecked: false, compat: 'backward'});
            const files = {
                migrationFilenames: ['migrations/20260420120000_add_column.php'],
                testFilesWithAdditions: TEST_FILES
            };
            const errors = validatePrChecklist(body, files);
            assert.ok(errors.some((e) => e.includes('not all items checked')));
        });

        it('passes when all items checked + backward-compatible', () => {
            const body = validBody({migrationSection: true, migrationChecked: true, compat: 'backward'});
            const files = {
                migrationFilenames: ['migrations/20260420120000_add_column.php'],
                testFilesWithAdditions: TEST_FILES
            };
            const errors = validatePrChecklist(body, files);
            assert.deepEqual(errors, []);
        });

        it('passes when all items checked + NOT backward-compatible + notes filled', () => {
            const body = validBody({
                migrationSection: true,
                migrationChecked: true,
                compat: 'breaking',
                notes: 'Column X removed. Run migration before deploying.'
            });
            const files = {
                migrationFilenames: ['migrations/20260420120000_add_column.php'],
                testFilesWithAdditions: TEST_FILES
            };
            const errors = validatePrChecklist(body, files);
            assert.deepEqual(errors, []);
        });
    });

    describe('Migration — compatibility selection', () => {
        it('fails when no compat option is selected', () => {
            const body = validBody({migrationSection: true, migrationChecked: true, compat: null});
            const files = {
                migrationFilenames: ['migrations/20260420120000_add_column.php'],
                testFilesWithAdditions: TEST_FILES
            };
            const errors = validatePrChecklist(body, files);
            assert.ok(errors.some((e) => e.includes('Migration compatibility not specified')));
        });

        it('fails when both compat options are selected', () => {
            const body = validBody({migrationSection: true, migrationChecked: true, compat: 'both'});
            const files = {
                migrationFilenames: ['migrations/20260420120000_add_column.php'],
                testFilesWithAdditions: TEST_FILES
            };
            const errors = validatePrChecklist(body, files);
            assert.ok(errors.some((e) => e.includes('Both migration compatibility')));
        });
    });

    describe('NOT backward-compatible → Notes must have content', () => {
        it('fails when breaking compat selected but Notes section is empty', () => {
            const body = validBody({migrationSection: true, migrationChecked: true, compat: 'breaking', notes: ''});
            const files = {
                migrationFilenames: ['migrations/20260420120000_add_column.php'],
                testFilesWithAdditions: TEST_FILES
            };
            const errors = validatePrChecklist(body, files);
            assert.ok(errors.some((e) => e.includes('Notes section is empty')));
        });

        it('fails when breaking compat selected but Notes has only HTML comments', () => {
            const body = validBody({
                migrationSection: true,
                migrationChecked: true,
                compat: 'breaking',
                notes: '<!-- leave blank -->'
            });
            const files = {
                migrationFilenames: ['migrations/20260420120000_add_column.php'],
                testFilesWithAdditions: TEST_FILES
            };
            const errors = validatePrChecklist(body, files);
            assert.ok(errors.some((e) => e.includes('Notes section is empty')));
        });

        it('passes when breaking compat selected and Notes has content', () => {
            const body = validBody({
                migrationSection: true,
                migrationChecked: true,
                compat: 'breaking',
                notes: 'Column removed. Deploy migration first.'
            });
            const files = {
                migrationFilenames: ['migrations/20260420120000_add_column.php'],
                testFilesWithAdditions: TEST_FILES
            };
            const errors = validatePrChecklist(body, files);
            assert.deepEqual(errors, []);
        });

        it('does not require Notes content when backward-compatible', () => {
            const body = validBody({migrationSection: true, migrationChecked: true, compat: 'backward', notes: ''});
            const files = {
                migrationFilenames: ['migrations/20260420120000_add_column.php'],
                testFilesWithAdditions: TEST_FILES
            };
            const errors = validatePrChecklist(body, files);
            assert.deepEqual(errors, []);
        });
    });

    describe('empty body', () => {
        it('fails with errors for type, testing, and AI', () => {
            const errors = validatePrChecklist('', {});
            assert.equal(errors.length, 3);
            assert.ok(errors.some((e) => e.includes('No PR type selected')));
            assert.ok(errors.some((e) => e.includes('No testing items checked')));
            assert.ok(errors.some((e) => e.includes('AI disclosure not filled')));
        });
    });
});
