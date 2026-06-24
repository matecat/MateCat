# Frontend CLAUDE.md

Guidance for working in the `public/` frontend source tree (React/Vite, plain JS).

## Directory Structure

| Path | Purpose |
|------|---------|
| `js/actions/` | Flux action creators (AppDispatcher-based) |
| `js/api/` | One file per backend endpoint (~170+ files) |
| `js/components/` | React components, grouped by feature area |
| `js/components/common/` | Shared/reusable UI components |
| `js/constants/` | Flux constants and global keys |
| `js/hooks/` | Custom React hooks |
| `js/pages/` | Top-level page components, mounted by Vite entries |
| `js/stores/` | Flux stores (AppDispatcher, not Redux) |
| `js/utils/` | Utility modules |
| `css/sass/` | SCSS source — entry files at top level, partials in `commons/` and `components/` |
| `vite-entries/` | Vite entry points (JS wrappers per page group, mapped by `groups.json`) |
| `mocks/` | Jest mock data (language, segments, user, QA model) |

## State Management

Flux (AppDispatcher) — not Redux, not Zustand.

- Actions live in `js/actions/`, constants in `js/constants/`
- Stores in `js/stores/` subscribe to dispatcher and emit change events
- Components read from stores via hooks or direct store subscriptions

## Component Conventions

- **PascalCase** filenames for components (`Accordion.js`, `SegmentBody.js`)
- **camelCase** or kebab-case for utilities and hooks (`segmentUtils.js`, `useContextDocument.js`)
- Directory-per-component: `ComponentName/ComponentName.js`, optionally `index.js` re-exporting it
- Complex features group multiple files in one directory (e.g., `Segment/Segment.js`, `SegmentBody.js`, `SegmentFooter.js`)
- Use PropTypes (no TypeScript — this is a plain JS codebase)
- React 18 functional components with hooks

## API Layer

One file per endpoint in `js/api/`. Add a new file rather than extending existing ones. Each file exports a single async function wrapping a fetch call.

## CSS / SCSS

- Plain SCSS, **no CSS Modules** — global namespace with BEM-like class naming (e.g., `.button-component-container`)
- Shared variables and tokens: `css/sass/commons/_colors.scss`, `_variables.scss`, `_typography.scss`
- Feature styles: `css/sass/components/`
- Import styles directly in component files or entry points
- Do not introduce `.module.scss` without an explicit decision to migrate

## Testing

**Before every commit, run the frontend test suite:**

```bash
yarn test
```

Run a single test file:

```bash
yarn test public/js/path/to/Component.test.js
```

Run in watch mode during development:

```bash
yarn test --watch
```

### Test conventions

- Tests are **colocated** with source: `Component.js` → `Component.test.js` in the same directory
- Framework: Jest + `@testing-library/react` + MSW for API mocking
- Mock data lives in `public/mocks/` (languagesMock, segmentsMock, userMock, etc.)
- Test blocks use `describe`/`test` with `expect` assertions
- MSW server is set up in `setupFilesAfterEnv.jest.js` — use it for API mocking instead of manual fetch mocks

## Build & Dev

```bash
yarn watch            # Dev server with HMR
yarn build:dev        # Development build
yarn build:production # Production build
```

Vite entries are defined in `vite-entries/groups.json`. Adding a new page requires a new entry file in `vite-entries/` and a corresponding entry in `groups.json`.

Build output goes to `public/build/`. Vite also injects asset tags into PHP/PHPTAL templates under `lib/View/` via the HTML template injection plugin — check `vite.config.js` if you need to wire up a new page template.

## Prettier & ESLint

```bash
yarn prettier --write .   # Format
yarn eslint .             # Lint
```

Prettier config: no semicolons, single quotes, no bracket spacing, trailing commas everywhere.

ESLint: React + React Hooks rules for browser JS; `public/js/lib/**` is ignored.

## Git

Follow `.github/prompts/conventional-commit.prompt.md` for all commit messages.

- Format: `<emoji> <type>(<scope>): <description>`
- **Always show the proposed commit message and wait for explicit approval before committing**
- Use `git commit -a` (lowercase `-a`), never `-A`
- 100-character line limit
- Imperative mood, no capitalization, no period
- Do not add `Co-Authored-By` trailers

Common scopes for frontend work: `cattool`, `dashboard`, `segments`, `modals`, `header`, `analyze`, `contextPreview`, `settingsPanel`, `api`, `hooks`, `stores`.

## Pull Requests

Follow `.github/PULL_REQUEST_TEMPLATE.md` when opening PRs. Key sections:

- **Summary** — what the PR does and why, briefly
- **Type** — check exactly one (`feat`, `fix`, `refactor`, `chore`, `perf`, `test`)
- **Changes** — one line per file or logical group in the table
- **Migration Notes** — only if DB migrations are involved (omit the section otherwise)
- **Testing** — check which of `phpunit`, `phpstan`, manual testing, new tests apply; for frontend PRs, also confirm `yarn test` passes
- **AI Disclosure** — disclose if Claude Code or any other AI tool was used
