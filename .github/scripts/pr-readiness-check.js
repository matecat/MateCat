'use strict';

// ── Helpers ───────────────────────────────────────────────────

function escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function getChecked(items, body) {
    return items.filter((item) => {
        return new RegExp(`-\\s*\\[[xX]\\]\\s*${escapeRegex(item)}`).test(body);
    });
}

function getUnchecked(items, body) {
    return items.filter((item) => {
        return !new RegExp(`-\\s*\\[[xX]\\]\\s*${escapeRegex(item)}`).test(body);
    });
}

function isChecked(item, body) {
    return new RegExp(`-\\s*\\[[xX]\\]\\s*${escapeRegex(item)}`).test(body);
}

// ── Section validators ────────────────────────────────────────

function validateExactlyOne(items, body, {none, multiple}) {
    const checked = getChecked(items, body);
    if (checked.length === 0) return none;
    if (checked.length > 1) return multiple;
    return null;
}

function validateAtLeastOne(items, body, {none}) {
    if (getChecked(items, body).length === 0) return none;
    return null;
}

function validateAll(items, body, {prefix}) {
    const unchecked = getUnchecked(items, body);
    if (unchecked.length === 0) return null;
    return [prefix, ...unchecked.map((m) => `  - ${m}`)].join('\n');
}

function hasNonEmptySection(sectionHeader, body) {
    const re = new RegExp(`##\\s*${escapeRegex(sectionHeader)}\\s*\\n([\\s\\S]*?)(?=\\n##\\s|$)`);
    const match = body.match(re);
    if (!match) return false;
    let cleaned = match[1];
    let prev;
    do {
        prev = cleaned;
        cleaned = cleaned.replace(/<!--[\s\S]*?-->/g, '');
    } while (cleaned !== prev);
    return cleaned.trim().length > 0;
}

// ── Test-file detection ───────────────────────────────────────

/** @param {string} filename */
function isTestFile(filename) {
    return filename.startsWith('tests/') || /\.(test|spec)\.[jt]sx?$/.test(filename);
}

/** @param {Array<{filename: string, additions: number}>} files */
function getTestFilesWithAdditions(files) {
    return files
        .filter((f) => isTestFile(f.filename) && f.additions > 0)
        .map((f) => f.filename);
}

// ── Migration-file detection ──────────────────────────────────

/** @param {Array<{filename: string}>} files */
function getMigrationFilenames(files) {
    return files
        .map((f) => f.filename)
        .filter((name) => name.startsWith('migrations/') && name !== 'migrations/AbstractMatecatMigration.php');
}

// ── Checklist items ───────────────────────────────────────────

const TYPE_ITEMS = [
    '`feat` — new user-facing feature',
    '`fix` — bug fix',
    '`refactor` — restructure without behavior change',
    '`chore` — build, deps, config, docs',
    '`perf` — performance improvement',
    '`test` — test coverage',
];

const TYPE_FIX = '`fix` — bug fix';
const TYPE_FEAT = '`feat` — new user-facing feature';

const TESTING_ITEMS = [
    '`vendor/bin/phpunit --exclude-group=ExternalServices --no-coverage` passes',
    '`./vendor/bin/phpstan` passes (0 errors, with baseline)',
    'Manual testing performed (describe below)',
    'New tests added for changed behavior',
    'Regression tests added for bug fixes',
];

const REGRESSION_ITEM = 'Regression tests added for bug fixes';

const AI_ITEMS = [
    'No AI tools were used in this PR',
    'AI tools were used — details below',
];

const MIGRATION_REQUIRED_ITEMS = [
    'Migration file added in `migrations/` directory',
    'Tested on a fresh database and on an existing one',
];

const MIGRATION_COMPAT_ITEMS = [
    'Backward-compatible with current production schema',
    'NOT backward-compatible — breaking changes documented in Notes section',
];

const NOT_BACKWARD_COMPATIBLE = 'NOT backward-compatible — breaking changes documented in Notes section';

// ── Main validator ────────────────────────────────────────────

/**
 * @param {string} body - PR body markdown text
 * @param {object} files
 * @param {string[]} files.migrationFilenames - filenames under migrations/ from the PR diff
 * @param {string[]} files.testFilesWithAdditions - test filenames (under tests/) that have additions > 0
 * @returns {string[]} error messages (empty = all checks pass)
 */
function validatePrChecklist(body, {migrationFilenames = [], testFilesWithAdditions = []} = {}) {
    const errors = [];

    // ── Type: exactly one ────────────────────────────────────
    const typeErr = validateExactlyOne(TYPE_ITEMS, body, {
        none: 'No PR type selected. Check exactly one item under **Type**.',
        multiple: 'Multiple PR types selected. Check exactly one item under **Type**.',
    });
    if (typeErr) errors.push(typeErr);

    const isFix = isChecked(TYPE_FIX, body);
    const isFeat = isChecked(TYPE_FEAT, body);

    // ── Testing: at least one ────────────────────────────────
    const testErr = validateAtLeastOne(TESTING_ITEMS, body, {
        none: 'No testing items checked. Check at least one item under **Testing**.',
    });
    if (testErr) errors.push(testErr);

    // ── fix → regression tests checkbox must be checked ──────
    if (isFix && !isChecked(REGRESSION_ITEM, body)) {
        errors.push(
            'PR type is `fix` but "Regression tests added for bug fixes" is not checked.\n' +
            'Bug fixes must include regression tests.',
        );
    }

    // ── feat/fix → test files must be present with additions ─
    if ((isFeat || isFix) && testFilesWithAdditions.length === 0) {
        errors.push(
            `PR type is \`${isFix ? 'fix' : 'feat'}\` but no test files with added lines found in the diff.\n` +
            'Features and bug fixes must include new or updated tests (under tests/).',
        );
    }

    // ── AI Disclosure: exactly one ───────────────────────────
    const aiErr = validateExactlyOne(AI_ITEMS, body, {
        none: 'AI disclosure not filled. Check one item under **AI Disclosure**.',
        multiple: 'Both AI disclosure options checked. Select only one.',
    });
    if (aiErr) errors.push(aiErr);

    // ── Migration: bidirectional enforcement ──────────────────
    const hasMigrationSection = /## Migration Notes/.test(body);
    const hasMigrationFiles = migrationFilenames.length > 0;

    if (hasMigrationSection && !hasMigrationFiles) {
        errors.push(
            'Migration Notes section is present but no migration file found in migrations/ directory.\n' +
            'Either add a migration file (migrations/YYYYMMDDHHMMSS_description.php)\n' +
            'or delete the Migration Notes section if no migration is needed.',
        );
    }

    if (hasMigrationFiles && !hasMigrationSection) {
        errors.push(
            'PR contains migration files but the Migration Notes section is missing:\n' +
            migrationFilenames.map((f) => `  - ${f}`).join('\n') + '\n\n' +
            'Add the Migration Notes section from the PR template and complete all checklist items.',
        );
    }

    if (hasMigrationSection && hasMigrationFiles) {
        const migErr = validateAll(MIGRATION_REQUIRED_ITEMS, body, {
            prefix: 'Migration section present but not all items checked:',
        });
        if (migErr) errors.push(migErr);

        const compatErr = validateExactlyOne(MIGRATION_COMPAT_ITEMS, body, {
            none: 'Migration compatibility not specified. Check exactly one: backward-compatible OR not backward-compatible.',
            multiple: 'Both migration compatibility options checked. Select only one.',
        });
        if (compatErr) errors.push(compatErr);

        // ── NOT backward-compatible → Notes section must have content
        if (isChecked(NOT_BACKWARD_COMPATIBLE, body) && !hasNonEmptySection('Notes', body)) {
            errors.push(
                'Migration is marked as NOT backward-compatible but the Notes section is empty.\n' +
                'Document breaking changes, migration impact, and deployment steps in the Notes section.',
            );
        }
    }

    return errors;
}

module.exports = {
    validatePrChecklist,
    isTestFile,
    getTestFilesWithAdditions,
    getMigrationFilenames,
    getChecked,
    getUnchecked,
    isChecked,
    validateExactlyOne,
    validateAtLeastOne,
    validateAll,
    hasNonEmptySection,
    TYPE_ITEMS,
    TYPE_FIX,
    TYPE_FEAT,
    TESTING_ITEMS,
    REGRESSION_ITEM,
    AI_ITEMS,
    MIGRATION_REQUIRED_ITEMS,
    MIGRATION_COMPAT_ITEMS,
    NOT_BACKWARD_COMPATIBLE,
};
