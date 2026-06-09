# Frontend XLIFF Tag Lifecycle — Technical Baseline

> **Scope**: Discovery and mapping of the current frontend tag handling system.  
> **Focus**: 100% client-side JavaScript — no backend/API logic.  
> **Date**: 2026-05-19

---

## Architecture Overview

```
API Response (raw XLIFF markup)
       │
       ▼
SegmentStore (stores raw + decoded versions)
       │
       ▼
Editarea / SegmentSource constructor
       │
       ▼
DraftMatecatUtils.encodeContent(editorState, text, sourceTagMap)
       │
       ├── matchTag(plainText)
       │       ├── findTagWithRegex(text, tagName)  → uses tagSignatures[name].regex
       │       │       └── decodeTagInfo(tag)       → Base64.decode() for ph equiv-text
       │       ├── Pair opening/closing tags by offset
       │       └── Return sorted tag array
       │
       ├── addIncrementalIndex(tagRange)  → Sequential numbering for XLIFF2 ph duplicates
       │
       ├── Replace tag text with ZWSP-wrapped placeholder
       │
       └── Create Draft.js Entity (type, mutability='IMMUTABLE', data)
              │
              ▼
       EditorState with ContentState + Entity Map
              │
              ▼
       Draft.js Decorator (getEntityStrategy('IMMUTABLE'))
              │
              ▼
       TagEntity.component.js (renders tag pill with index counter)
```

---

## 1. Tag Type Definitions

**File**: `public/js/components/segments/utils/DraftMatecatUtils/tagModel.js`

The `tagSignaturesMap` defines **14 tag types** in two categories:

| Category | Tags | Key Property |
|----------|------|-------------|
| **XLIFF inline** | `ph`, `g`, `gCl`, `gSc`, `bx`, `ex`, `x` | `placeholderRegex` + `decodeNeeded` |
| **Special chars** | `nbsp`, `tab`, `carriageReturn`, `lineFeed`, `splitPoint`, `wordJoiner`, `space` | `encodedPlaceholder` (e.g. `##$_A0$##`) |

### `ph` tag — the only tag with `decodeNeeded: true`

```js
ph: {
  type: 'ph',
  regex: /<ph\b[^>]+?equiv-text="base64:([^"]+)"[^>]*?\/>/g,
  placeholderRegex: /<ph\b[^>]+?equiv-text="base64:([^"]+)"[^>]*?\/>/,
  decodeNeeded: true,           // triggers Base64.decode()
  selfClosing: true,
  isClosure: false,
  style: 'tag-selfclosed tag-ph',
  showTooltip: true,
  errorCheckAvailable: true,
}
```

### Markup tags (`g`, `bx`, `ex`, `x`) — extract `id` attribute only

```js
g: {
  type: 'g',
  regex: /<g\b[^>]+?id="([^"]+)"(?![^>]*\/>)[^>]*>/g,
  placeholderRegex: /<g\b[^>]+?id="([^"]+)"(?![^>]*\/>)[^>]*>/,
  decodeNeeded: false,
  selfClosing: false,
  isClosure: false,
  style: 'tag-open',
  styleRTL: 'tag-close',
}
```

### Proxy-based middleware

The exported `tagSignatures` is a `Proxy` around `tagSignaturesMap` (lines 264–280). A middleware system (`tagSignaturesMiddleware`) allows runtime filtering—e.g., toggling `space` visibility based on the `show_whitespace` project setting via `initTagSignature()`.

---

## 2. Tag Data Structure (`TagStruct`)

```js
function TagStruct(offset, length, type, name) {
  this.offset = offset           // Absolute position in text
  this.length = length           // Tag length in characters
  this.type = type               // Tag type string ('ph', 'g', etc.)
  this.mutability = 'IMMUTABLE'  // HARDCODED — Draft.js enforcement
  this.data = {
    id: null,                    // XLIFF id attribute value
    name: name,                  // tagSignatures key ('ph', 'gCl', 'bx', etc.)
    encodedText: null,           // Original XLIFF markup (full tag string)
    decodedText: null,           // Human-readable content (Base64-decoded for ph)
    openTagId: null,             // Unique pair ID linking to opener (format: "offset-offset")
    closeTagId: null,            // Unique pair ID linking to closer
    openTagKey: null,            // Draft.js entity key (opener)
    closeTagKey: null,           // Draft.js entity key (closer)
    placeholder: null,           // Display text shown to user in editor
    originalOffset: -1,          // Offset before entity substitution
  }
}
```

---

## 3. Client-Side Data Flow

### Phase A: API → Store

1. **`getSegments.js`** fetches segments from `/api/app/get-segments`
2. Response contains `segment.segment` (source) and `segment.translation` (target) with raw XLIFF markup
3. **`SegmentStore`** receives via `ADD_SEGMENTS` action, normalizes, and stores both raw text and `decodedSource`/`decodedTranslation` (created via `transformTagsToText()`)

### Phase B: Store → Draft.js EditorState (Component Mount)

When `Editarea` (target) or `SegmentSource` (source) mounts:

```js
// Editarea.js constructor (line 105)
const plainEditorState = EditorState.createEmpty(decorator)
const contentEncoded = DraftMatecatUtils.encodeContent(
  plainEditorState,
  cleanTranslation,    // raw XLIFF text from segment
  sourceTagMap         // optional: preserves source tag indexing in target
)
const {editorState, tagRange} = contentEncoded
```

### Phase C: `encodeContent` → Entity Creation Pipeline

**`encodeContent.js`** orchestrates:

1. Calls `createNewEntitiesFromMap(editorState, excludedTags, plainText, sourceTagMap)`
2. `createNewEntitiesFromMap` calls **`matchTag(plainText)`** which:
   - Calls **`findTagWithRegex(text, tagName)`** for every tag in `tagSignatures`
   - Each regex match creates a `TagStruct` populated via **`decodeTagInfo(tag)`**
   - Separates tags into open, closing, and self-closing arrays
   - Pairs opening `<g>` with closing `</g>` by offset proximity (nearest unpaired opener before closer)
   - Assigns unique pair IDs: `closeTagId = "${openOffset}-${closeOffset}"`
   - Orphan closures get `placeholder = '?'`
3. Applies **sequential indexing** via `addIncrementalIndex()` (see Section 4)
4. Replaces each tag in the plain text with `​placeholder​` (ZWSP-wrapped, U+200B)
5. Creates Draft.js entities: `contentState.createEntity(type, mutability, data)`
6. Applies entities to content via `Modifier.applyEntity()`
7. Returns `{editorState, tagRange}`

### Phase D: Base64 Decoding (`decodeTagInfo.js`)

For `ph` tags with `decodeNeeded: true`:

```js
import {Base64} from 'js-base64'

const contentMatch = placeholderRegex.exec(tagEncodedText)
// Captures base64 string from: equiv-text="base64:Jmx0O3AmZ3Q7"
decodedTagData.content = Base64.decode(contentMatch[1])  // → "&lt;p&gt;"
  .replace(/\n/g, '').replace(/\r/g, '')
decodedTagData.content = unescapeHTMLRecursive(decodedTagData.content)  // → "<p>"
  .replace(/\n/g, ' ')
```

For markup tags (`g`, `bx`, `ex`, `x`): extracts `id` attribute via `getIdAttributeRegEx()` → displays the id value as the placeholder.

### Phase E: Reverse Flow — Save (`decodeSegment.js`)

When the translation is sent to the API:

```js
// Iterates all entities in EditorState
// Replaces each placeholder text with the original encodedText
plainEditorText = plainEditorText.slice(0, start) + encodedText + plainEditorText.slice(end)
```

Then strips ZWSP characters and encodes residual HTML entities (`&` → `&amp;`, `<` → `&lt;`, `>` → `&gt;`), returning the reconstructed XLIFF-tagged string.

---

## 4. Sequential Numbering (Index Counter)

**File**: `createNewEntitiesFromMap.js` → `addIncrementalIndex()`

Numbering applies **only** to XLIFF2 `ph` tags, detected by:

```js
const isXliff2 = (encodedText) =>
  /\bph\b/.test(encodedText) && !/id=\"mtc_/.test(encodedText)
```

Logic:

```js
const addIncrementalIndex = (tagRange) =>
  tagRange.reduce((acc, cur) => {
    const {decodedText, encodedText} = cur.data
    const lastIndex = [...acc].reverse()
      .find(({data}) => data.decodedText === decodedText)?.data?.index ?? -1
    const haveMultipleMatches = tagRange
      .filter(({data}) => data.decodedText === cur.data.decodedText).length > 1
    return [...acc, {
      ...cur,
      data: { ...cur.data,
        ...(isXliff2(encodedText) && haveMultipleMatches && {index: lastIndex + 1})
      }
    }]
  }, [])
```

- Tags with unique `decodedText` → **no index** (property stays `undefined`)
- Tags with duplicate `decodedText` → sequential 0-based index (0, 1, 2...)
- UI displays as **1-based**: `{index >= 0 && <span className="index-counter">{index + 1}</span>}`

When `sourceTagMap` is provided (target editor), indexes are **inherited from source** by matching on `data.id` rather than recalculated:

```js
const tagSource = sourceTagMap.find(({data}) => data.id === tag.data.id)
// Copies index from source tag if found
```

---

## 5. Frontend Immutability Guardrails

Tag content is protected by **5 independent layers**:

| Layer | File | Mechanism |
|-------|------|-----------|
| **1. Draft.js Mutability** | `tagModel.js:291` | `TagStruct.mutability = 'IMMUTABLE'` — Draft.js engine prevents editing/deleting entity text |
| **2. Decorator Strategy** | `getEntityStrategy.js` | Only IMMUTABLE entities get rendered via TagEntity — decorator filters by mutability level |
| **3. ZWSP Boundaries** | `insertTag.js`, `createNewEntitiesFromMap.js` | Zero-width spaces (U+200B) before/after each tag create cursor stop boundaries |
| **4. DOM Attributes** | `TagEntity.component.js:255–256` | `unselectable="on"` + `suppressContentEditableWarning={true}` on tag `<span>` |
| **5. Paste/Drop Sanitization** | `Editarea.js:1423–1485` | External pastes → `removeTagsFromText()` strips all XLIFF tags; internal pastes preserve entities via JSON fragment |

### Source Editor Lockdown (`SegmentSource.js`)

The source segment editor uses blanket prevention on all input vectors:

```js
<Editor
  handleBeforeInput={preventEdit}
  handlePastedText={preventEdit}
  handleDrop={preventEdit}
  handleDroppedFiles={preventEdit}
/>
```

### How Draft.js IMMUTABLE Entities Work

The `getEntityStrategy.js` function:

```js
const getEntityStrategy = (mutability) => {
  return function (contentBlock, callback, contentState) {
    contentBlock.findEntityRanges((character) => {
      const entityKey = character.getEntity()
      if (entityKey === null) return false
      return contentState.getEntity(entityKey).getMutability() === mutability
    }, callback)
  }
}
```

Both `Editarea` and `SegmentSource` register the decorator as:

```js
{
  name: 'tags',
  strategy: getEntityStrategy('IMMUTABLE'),
  component: TagEntity,
  props: { onClick, getUpdatedSegmentInfo, isTarget, isRTL, sid }
}
```

Draft.js IMMUTABLE semantics: if a user selects text that partially overlaps an IMMUTABLE entity, the **entire entity is selected**. Deleting any character within the entity range deletes the whole entity. The entity text cannot be modified character-by-character.

---

## 6. Tag Copy/Insert Mechanism (Source → Target)

### Copy (`Editarea.copyFragment`)

1. Gets Draft.js internal clipboard (fragment)
2. Extracts plain text (strips ZWSP and space placeholders)
3. Serializes fragment + entity map to JSON
4. Stores in `SegmentStore` clipboard via `SegmentActions.copyFragmentToClipboard()`
5. Sets plain text in system clipboard via `clipboardData.setData()`

### Paste (`Editarea.pasteFragment`)

Two paths:

**Internal paste** (text matches stored clipboard):
```js
const fragmentContent = JSON.parse(clipboardFragment)
let fragment = DraftMatecatUtils.buildFragmentFromJson(fragmentContent.orderedMap)
const clipboardEditorPasted = DraftMatecatUtils.duplicateFragment(
  fragment, editorState, fragmentContent.entitiesMap
)
```
→ Entities are reconstructed with full tag data, preserving IMMUTABLE mutability.

**External paste** (text does NOT match internal clipboard):
```js
let cleanText = DraftMatecatUtils.removeTagsFromText(text)  // Strip all XLIFF tags
cleanText = cleanText.replace(/°/gi, nbspSign).replace(/\t/gi, tabSign)
const plainTextClipboardFragment = DraftMatecatUtils.buildFragmentFromText(cleanText)
```
→ External XLIFF markup is **stripped entirely** — only special chars (nbsp, tab) are re-encoded.

### Tag Menu (`TagBox` / `TagSuggestion` / `insertTag.js`)

Users can insert missing source tags via the autocomplete TagMenu:

1. `checkForMissingTags(sourceTagMap, targetTagMap)` computes which source tags are absent from target
2. `TagBox` renders missing tags + all source tags as clickable suggestions
3. On click → `insertTag(tagSuggestion, editorState, triggerText)`:
   - Creates new entity with `tag.mutability` (IMMUTABLE) and original `tag.data`
   - Inserts ZWSP + placeholder + ZWSP at cursor position
   - The inserted entity carries the **same `encodedText`** as the source tag

### Drop Handling (`Editarea.handleDrop`)

```js
// Cannot drop anything ON entities
const {entityKey} = DraftMatecatUtils.selectionIsEntity(editorState)
if (entityKey) return 'handled'  // ← Blocks drop on top of existing tags
```

---

## 7. UI Component Rendering

### `TagEntity.component.js` (Full — Target Editor)

The decorator invokes `TagEntity` for every IMMUTABLE entity character range:

```jsx
<span className={`tag ${style} ${tagWarningStyle}`}
      data-offset-key={offsetkey}
      unselectable="on"
      suppressContentEditableWarning={true}
      onClick={...}>
  {children}  {/* Draft.js-rendered placeholder text */}
  {index >= 0 && <span className="index-counter">{index + 1}</span>}
</span>
```

**CSS classes** derived from `tagSignatures[entityName].style`:
- `tag-selfclosed tag-ph` — ph tags
- `tag-open` / `tag-close` — g opening/closing (direction-aware via `styleRTL`)
- `tag-selfclosed` — bx, ex, x, gSc

**Additional states**:
- `tag-inactive` — tag is in an unopened segment
- `tag-clicked` — user clicked on this tag (green highlight)
- `tag-focused` — tag is cursor-adjacent
- `tag-mismatch-error` — tag present in source but wrong in target
- `tag-mismatch-warning` — tag order mismatch

**Tooltip**: shown on hover for `ph` tags (when `showTooltip: true` in signature) if the content overflows.

### `TagEntityLite.js` (Lightweight — Source Preview)

Simpler renderer without click handling, mismatch detection, or search:

```jsx
<span className={`tag ${style}`} unselectable="on">
  {children}
  {index >= 0 && <span className="index-counter">{index + 1}</span>}
</span>
```

---

## 8. Key File Reference

| Concern | File Path |
|---------|-----------|
| Tag type definitions & regex | `utils/DraftMatecatUtils/tagModel.js` |
| Tag discovery in text | `utils/DraftMatecatUtils/findTagWithRegex.js` |
| Base64 decoding & id extraction | `utils/DraftMatecatUtils/decodeTagInfo.js` |
| Opening/closing tag pairing | `utils/DraftMatecatUtils/matchTag.js` |
| Draft.js entity creation + indexing | `utils/DraftMatecatUtils/createNewEntitiesFromMap.js` |
| Main encoding entry point | `utils/DraftMatecatUtils/encodeContent.js` |
| Reverse decoding (save to API) | `utils/DraftMatecatUtils/decodeSegment.js` |
| Tag insertion (user action) | `utils/DraftMatecatUtils/TagMenu/insertTag.js` |
| Missing tag detection | `utils/DraftMatecatUtils/TagMenu/checkForMissingTag.js` |
| Tag menu UI | `utils/DraftMatecatUtils/TagMenu/TagBox.js`, `TagSuggestion.js` |
| Tag rendering (target editor) | `TagEntity/TagEntity.component.js` |
| Tag rendering (source/lite) | `TagEntity/TagEntityLite.js` |
| Decorator strategy | `utils/DraftMatecatUtils/getEntityStrategy.js` |
| Tag transformation utilities | `utils/DraftMatecatUtils/tagUtils.js` |
| Paste/copy/drop handling | `Editarea.js` (lines 1423–1539) |
| Source editor lockdown | `SegmentSource.js` (line 739–741) |
| HTML entity utils | `utils/DraftMatecatUtils/htmlUtils.js` |
| Entity extraction from state | `utils/DraftMatecatUtils/getEntities.js` |
| Entity → TagStruct conversion | `utils/DraftMatecatUtils/tagFromEntity.js` |
| TagStruct from type name | `utils/DraftMatecatUtils/tagFromTagType.js` |

> All paths relative to `public/js/components/segments/`
