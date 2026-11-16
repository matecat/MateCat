# Frontend CLAUDE.md

Guidance for working in the `public/` frontend source tree (React/Vite, plain JS).

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

- Global SCSS with BEM-like class naming (e.g., `.button-component-container`), with CSS Modules (`.module.scss`)
  already in use for some components — follow whichever the surrounding component uses
- Shared variables and tokens: `css/sass/commons/_colors.scss`, `_variables.scss`, `_typography.scss`
- Import styles directly in component files or entry points

## Testing

**Before every commit, run the frontend test suite:**

```bash
yarn test --watchAll=false
```

`yarn test` is `jest --watchAll` and never exits on its own — always pass `--watchAll=false` outside an interactive
terminal, including for a single file (`yarn test --watchAll=false public/js/path/to/Component.test.js`).

### Test conventions

- Tests are **colocated** with source: `Component.js` → `Component.test.js` in the same directory
- Framework: Jest + `@testing-library/react` + MSW for API mocking
- Mock data lives in `public/mocks/` (languagesMock, segmentsMock, userMock, etc.)
- Test blocks use `describe`/`test` with `expect` assertions
- MSW server is set up in `setupFilesAfterEnv.jest.js` — use it for API mocking instead of manual fetch mocks

## Build & Dev

Vite entries are defined in `vite-entries/groups.json`. Adding a new page requires a new entry file in `vite-entries/` and a corresponding entry in `groups.json`.

Build output goes to `public/build/`. Vite also injects asset tags into PHP/PHPTAL templates under `lib/View/` via the HTML template injection plugin — check `vite.config.js` if you need to wire up a new page template.

## Git

Commit rules are in the root `CLAUDE.md`. Common scopes for frontend work: `cattool`, `dashboard`, `segments`, `modals`, `header`, `analyze`, `contextPreview`, `settingsPanel`, `api`, `hooks`, `stores`.

## Pull Requests

Follow `.github/PULL_REQUEST_TEMPLATE.md` when opening PRs. Key sections:

- **Summary** — what the PR does and why, briefly
- **Type** — check exactly one (`feat`, `fix`, `refactor`, `chore`, `perf`, `test`)
- **Changes** — one line per file or logical group in the table
- **Migration Notes** — only if DB migrations are involved (omit the section otherwise)
- **Testing** — check which of `phpunit`, `phpstan`, manual testing, new tests apply; for frontend PRs, also confirm `yarn test` passes
- **AI Disclosure** — disclose if Claude Code or any other AI tool was used
