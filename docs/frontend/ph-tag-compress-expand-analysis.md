# Ph Tag Compress/Expand Feature — Development Analysis

## Problem Statement

Add a toggle that switches ph tags between two display modes:

- **Expanded**: sequential number + decoded equiv-text content (e.g. `[1] <br/>`)
- **Compressed**: only the sequential number (e.g. `[1]`)

Additionally, solve the identity challenge: repeated ph tags with identical `equiv-text` and no XLIFF id attribute are currently indistinguishable from one another.

---

## Current State: The Identity Problem

### How `addIncrementalIndex` Works Today

Location: `public/js/components/segments/utils/DraftMatecatUtils/createNewEntitiesFromMap.js` (lines 154–177)

Ph tags are identified by their `decodedText` (decoded equiv-text content). When duplicates exist:

- First ph tag with `equiv-text="hello"` → `index: 0`
- Second ph tag with `equiv-text="hello"` → `index: 1`

**But** the index is ONLY assigned when BOTH conditions are true:

1. `isXliff2(encodedText)` returns true (no `id="mtc_"` prefix)
2. Multiple tags share the same `decodedText`

Unique ph tags (content appears only once) receive **no index at all**.

### The `isXliff2` Gate

```javascript
const isXliff2 = (encodedText) =>
  /\bph\b/.test(encodedText) && !/id=\"mtc_/.test(encodedText)
```

- **XLIFF2 ph tags**: `<ph equiv-text="..."/>` (no mtc_ prefix) → eligible for indexing
- **Legacy ph tags**: `<ph id="mtc_..." equiv-text="..."/>` → use the `id` field instead

### The Bug in `checkForMissingTags`

Location: `public/js/components/segments/utils/DraftMatecatUtils/TagMenu/checkForMissingTag.js` (lines 28–55)

The matching logic (`arraySubtract`) has a critical flaw for duplicate ph tags:

```javascript
return nameTargetTag === 'ph' && index === undefined
  ? decodedTextSourceTag === decodedTextTargetTag && nameSourceTag === nameTargetTag
  : idTargetTag === idSourceTag && nameSourceTag === nameTargetTag
```

| Condition | Match Strategy | Problem |
|-----------|---------------|---------|
| Ph tags WITHOUT index | Match by `decodedText` only | Works for unique tags |
| Ph tags WITH index | Match by `id` field | XLIFF2 ph tags have **empty** `id` → match always fails |

**Result**: When two ph tags share the same content:

1. Both get `decodedText: "hello"`
2. `addIncrementalIndex` assigns `index: 0` and `index: 1`
3. `checkForMissingTags` sees `index !== undefined` → uses id-based matching
4. XLIFF2 ph tags have empty `id` fields → match fails
5. Second ph tag is incorrectly reported as "missing"

### Summary

| Condition | Current Behavior | Problem |
|-----------|-----------------|---------|
| Unique ph tags (different content) | No index assigned | No sequential number available |
| Duplicate ph tags (same content, XLIFF2) | Index 0, 1, 2... assigned | Partially works |
| Duplicate ph tags in `checkForMissingTags` | Matches by `id` field | Always fails for XLIFF2 → duplicates reported "missing" |

**Implication**: This feature doesn't just add a visual toggle — it requires **fixing the numbering foundation** first.

---

## Proposed Architecture

### Layer 1: Universal Ph Tag Numbering (Data Model Fix)

**File**: `createNewEntitiesFromMap.js` → `addIncrementalIndex()`

**Change**: All ph tags get a global sequential index, regardless of content uniqueness.

Current logic (pseudocode):
```javascript
// Only XLIFF2 + only duplicates get an index
...(isXliff2(encodedText) && haveMultipleMatches && {index: lastIndex + 1})
```

Proposed logic:
```javascript
// Every ph tag gets a position-based index
...(isPh(encodedText) && {index: globalPhCounter++})
```

This gives every ph tag a unique sequential number (0-based internally, displayed as 1-based) based on appearance order in the segment.

Two identical `<br/>` tags at positions 10 and 45 become ph[0] and ph[1].

**Impact on `checkForMissingTags`**: Must update matching to use `decodedText + index` composite key for ph tags (instead of the broken `id`-based matching).

**Impact on `sourceTagMap`**: The `sourceTagMap` flow already passes `index` from source to target by matching on `data.id`. For XLIFF2 tags with empty ids, this needs to fall back to `decodedText + index` matching.

### Layer 2: Toggle State (Global Setting)

**Recommendation**: Global setting stored in `CatToolStore`, following the `show_whitespace` precedent.

Reasoning:
- Users want consistent behavior across all segments
- Per-segment toggle creates cognitive overhead
- The `show_whitespace` pattern already exists as a proven approach

**Flow**:

1. Toggle button dispatches action
2. `CatToolStore` updates `phTagsCompressed` flag
3. `CatToolStore` emits change event
4. `TagEntity.component.js` reads flag via store listener (already imports `SegmentStore` and `SegmentConstants` — can also listen to `CatToolStore`)

### Layer 3: Rendering (TagEntity Component — No ContentState Mutation)

**Critical design choice**: Do NOT re-encode content on toggle. Handle entirely in the rendering layer.

The entity data already contains everything needed:
- `data.index` → the sequential number
- `data.placeholder` / `data.decodedText` → the content
- `data.name` → tag type (filter for `'ph'` only)

**TagEntity render change** (conceptual):
```jsx
renderContent() {
  const isPhTag = entityName === 'ph'
  const compressed = /* read from store/props */

  return (
    <>
      {isPhTag && index >= 0 && (
        <span className="tag-ph-index">{index + 1}</span>
      )}
      {(!isPhTag || !compressed) && this.props.children}
    </>
  )
}
```

**Why rendering-layer only?**

| Concern | Impact |
|---------|--------|
| Undo/redo history | None — ContentState is not mutated by toggle |
| Performance | Instant toggle (no re-encoding hundreds of segments) |
| `decodeSegment.js` | Unaffected — uses `encodedText` which never changes |
| Tag mismatch detection | Unaffected — uses `encodedText` |
| Cursor behavior | Draft.js text still contains placeholder characters, visually hidden via CSS |

**CSS for compressed mode**:
```scss
.tag.tag-ph.tag-compressed {
  span[data-text='true'] {
    display: none;
  }
}
```

### Layer 4: TagMenu / TagSuggestion

`TagSuggestion.js` already renders `index + 1` when present. With universal numbering, all ph tags in the menu would automatically show their number.

In compressed mode, the tag menu should still show the full content (for discoverability) — the compression only affects inline rendering.

---

## Existing Toggle Pattern (Reference)

The `show_whitespace` setting demonstrates the established pattern:

1. **Initialization**: `CatTool.js:414` calls `initTagSignature(metadata)`
2. **Middleware**: `setTagSignatureMiddleware('space', ...)` creates a Proxy on the tag signature object
3. **Propagation**: Settings flow through the tag model and affect rendering globally
4. **State location**: CatToolStore / project metadata

The ph compress toggle should follow a similar global-state-driven approach, though it can be simpler since it only affects `TagEntity` rendering, not the underlying tag model structure.

---

## Design Decisions (Confirmed)

| # | Question | Decision |
|---|----------|----------|
| 1 | Numbering scope | All ph tags get universal sequential number (not just duplicates) |
| 2 | Toggle scope | Global — all segments at once, stored in CatToolStore |
| 3 | Expanded view format | `[N] content` — number prefix + equiv-text content |
| 4 | Number display style | Plain digit in badge (matches existing index-counter) |
| 5 | Button location | Global CatTool header/toolbar |
| 6 | Tooltip on compressed tags | Yes — hover shows full content (critical for usability) |

---

## Impact Assessment

| Component | Impact | Effort |
|-----------|--------|--------|
| `createNewEntitiesFromMap.js` | Modify `addIncrementalIndex` to number ALL ph tags | Low |
| `checkForMissingTags.js` | Fix matching logic for indexed ph tags (use `decodedText + index`) | Medium |
| `TagEntity.component.js` | Add conditional rendering (number vs content) | Medium |
| `TagEntityLite.js` | Same rendering change for source segments | Low |
| `Tag.scss` | Compressed mode styling, index badge styling | Low |
| `CatToolStore.js` | Add `phTagsCompressed` state + action handler | Low |
| `SegmentTargetToolbar.js` or global toolbar | Add toggle button | Low |
| `TagSuggestion.js` | Always show content in menu even when compressed | Low |
| Tooltip (TagEntity) | Show full content on hover in compressed mode | Low |
| `decodeSegment.js` | **None** — uses `encodedText` | None |
| `encodeContent.js` | **None** — rendering-layer change only | None |
| `insertTag.js` | **None** — inserts full tag data regardless of display mode | None |
| Undo/redo | **None** — ContentState not mutated by toggle | None |

---

## Risk Areas

### 1. Source-Target Index Consistency

If source has 5 ph tags numbered 1–5 and target has 3 (missing 2), the target must display the **same numbers** as source (not renumber 1–3). The `sourceTagMap` mechanism handles this, but needs validation with universal numbering.

### 2. Tag Insertion Ordering

When a user inserts a missing tag via TagMenu, the new tag's index must match the source's index for that tag — NOT be assigned a new sequential number based on insertion position.

### 3. Split Segments

When a segment is split, ph tag numbering must remain consistent within each part. The `split_group` handling needs verification.

### 4. TM Matches / Concordance

The `transformTagsToHtml` function (used in TM match display) would ideally also respect the compressed mode. This is a stretch goal — not required for initial implementation.

### 5. Performance with Many Segments

The toggle triggers a re-render of all visible TagEntity components. Since this is CSS/conditional-render only (no ContentState changes), React's reconciliation should handle this efficiently. However, segments with 50+ tags should be tested.

---

## Suggested Implementation Order

| Phase | Task | Dependencies |
|-------|------|--------------|
| 1 | Fix numbering: universal ph indexing in `addIncrementalIndex` | None |
| 2 | Fix `checkForMissingTags`: use `decodedText + index` composite matching | Phase 1 |
| 3 | Add global toggle state in `CatToolStore` | None (can parallel with 1–2) |
| 4 | Modify `TagEntity` rendering: conditional number/content display | Phases 1, 3 |
| 5 | Add toolbar button (toggle UI) | Phase 3 |
| 6 | CSS: compressed mode styling | Phase 4 |
| 7 | Tooltip: show full content on hover when compressed | Phase 4 |
| 8 | Verify `sourceTagMap` preserves indexes across source-target | Phase 1 |
| 9 | Verify TagMenu insertion uses correct source index | Phase 1 |

---

## Relevant Files

| File | Role |
|------|------|
| `public/js/components/segments/utils/DraftMatecatUtils/createNewEntitiesFromMap.js` | Entity creation + `addIncrementalIndex` (numbering) |
| `public/js/components/segments/utils/DraftMatecatUtils/TagMenu/checkForMissingTag.js` | Tag completeness validation (matching logic) |
| `public/js/components/segments/TagEntity/TagEntity.component.js` | Tag UI rendering with index-counter |
| `public/js/components/segments/TagEntity/TagEntityLite.js` | Lightweight source tag renderer |
| `public/js/components/segments/utils/DraftMatecatUtils/tagModel.js` | Tag signatures, Proxy middleware, `initTagSignature` |
| `public/js/components/segments/utils/DraftMatecatUtils/encodeContent.js` | Main encoding entry point |
| `public/js/components/segments/utils/DraftMatecatUtils/decodeSegment.js` | Reverse flow for saving |
| `public/js/components/segments/Editarea.js` | Target editor (decorator setup, sourceTagMap usage) |
| `public/js/components/segments/SegmentSource.js` | Source editor |
| `public/js/components/segments/SegmentTargetToolbar.js` | Toolbar buttons |
| `public/js/components/segments/SegmentTarget.js` | Parent component managing toolbar state |
| `public/js/stores/CatToolStore.js` | Global project-level state |
| `public/js/stores/SegmentStore.js` | Segment-level state |
| `public/js/actions/segmentDispatchActions.js` | Dispatch actions |
| `public/js/components/segments/utils/DraftMatecatUtils/TagMenu/insertTag.js` | Tag insertion |
| `public/css/sass/components/segment/Tag.scss` | Tag styling |
| `public/js/pages/CatTool.js` | Calls `initTagSignature(metadata)` |
