#!/usr/bin/env node
'use strict';

/**
 * Local PR validation script.
 *
 * Runs both the commit-message (title) check and the PR-readiness (body)
 * check against an open GitHub PR, using the same validators that CI uses.
 *
 * Usage:
 *   node .github/scripts/local_pr_validation.js [<PR number>]
 *
 * When no PR number is given the script picks the PR associated with the
 * current branch (via `gh pr view`).
 *
 * Requirements: `gh` CLI authenticated and on PATH.
 */

const {execSync} = require('child_process');
const path = require('path');

const {validateCommitMessage} = require(path.join(__dirname, 'commit-message-check.js'));
const {
    validatePrChecklist,
    getMigrationFilenames,
    getTestFilesWithAdditions,
} = require(path.join(__dirname, 'pr-readiness-check.js'));

// ── Fetch PR data via `gh` CLI ────────────────────────────────

const prArg = process.argv[2] || '';

function gh(args) {
    return execSync(`gh ${args}`, {encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe']}).trim();
}

let prJson;
try {
    const raw = gh(
        `pr view ${prArg} --json number,title,body,files`,
    );
    prJson = JSON.parse(raw);
} catch {
    console.error(
        prArg
            ? `❌ Could not fetch PR #${prArg}. Is the \`gh\` CLI authenticated?`
            : '❌ No open PR found for the current branch. Pass a PR number as argument.',
    );
    process.exit(1);
}

const {number, title, body, files} = prJson;

console.log(`\n🔍 Validating PR #${number}: ${title}\n`);

let hasFailures = false;

// ── 1. Title (commit-message) check ──────────────────────────

console.log('── Title check ──');
const titleResult = validateCommitMessage(title);
if (titleResult.valid) {
    console.log('✅ PR title is valid\n');
} else {
    hasFailures = true;
    console.log('❌ PR title errors:');
    titleResult.errors.forEach((e) => console.log(`   - ${e}`));
    console.log();
}

// ── 2. Body (PR-readiness) check ─────────────────────────────

console.log('── Body check ──');

const prFiles = (files || []).map((f) => ({
    filename: f.path,
    additions: f.additions,
}));

const migrationFilenames = getMigrationFilenames(prFiles);
const testFilesWithAdditions = getTestFilesWithAdditions(prFiles);

console.log('   Migration files found:', migrationFilenames.length ? migrationFilenames : 'none');
console.log('   Test files with additions:', testFilesWithAdditions.length ? testFilesWithAdditions : 'none');
console.log();

const bodyErrors = validatePrChecklist(body, {migrationFilenames, testFilesWithAdditions});

if (bodyErrors.length === 0) {
    console.log('✅ PR body passes all readiness checks\n');
} else {
    hasFailures = true;
    console.log(`❌ ${bodyErrors.length} body error(s):\n`);
    bodyErrors.forEach((e, i) => console.log(`${i + 1}. ${e}\n`));
}

// ── Summary ──────────────────────────────────────────────────

if (hasFailures) {
    console.log('❌ Validation FAILED');
    process.exit(1);
} else {
    console.log('✅ All checks PASSED');
}

