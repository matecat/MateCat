# Frontend CLAUDE.md

Guidance for working in the `public/` frontend source tree (React/Vite, plain JS).

## State Management

Flux (AppDispatcher) — not Redux, not Zustand.

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

- Mock data lives in `public/mocks/` (languagesMock, segmentsMock, userMock, etc.)
- MSW server is set up in `setupFilesAfterEnv.jest.js` — use it for API mocking instead of manual fetch mocks
- For frontend PRs, also state in the PR's Testing section that `yarn test --watchAll=false` passes

## Build & Dev

Vite entries are defined in `vite-entries/groups.json`. Adding a new page requires a new entry file in `vite-entries/` and a corresponding entry in `groups.json`.

Build output goes to `public/build/`. Vite also injects asset tags into PHP/PHPTAL templates under `lib/View/` via the HTML template injection plugin — check `vite.config.mjs` if you need to wire up a new page template.

## Git

Commit rules are in the root `CLAUDE.md`. Common scopes for frontend work: `cattool`, `dashboard`, `segments`, `modals`, `header`, `analyze`, `contextPreview`, `settingsPanel`, `api`, `hooks`, `stores`.
