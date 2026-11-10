# Test ledger: DraftMatecatUtils → CodeMirror 6 editor

Every test file under `components/segments/utils/DraftMatecatUtils/` (DMU), with what happens to it when Draft goes
(plan `05-editor-plan.md` §4). Counts are `grep -cE "^\s*(it|test)\("`.

- **port**: the behaviour is re-specified against the CM6 core (`EditorState` level, or the Playwright suite) in the
  milestone shown. The Draft test is deleted with Draft in M5, after its port is green.
- **keep**: string layer with no Draft in it. Stays, and moves with its module in M5.
- **delete**: tests Draft plumbing only (entities, blocks, decorators); nothing in the CM6 model corresponds.

There is no `DraftSource/` folder on this branch, so nothing is listed for it.

## Port

| Test file                           | Tests | Milestone                                 | Ported to                                                                    | Reason                                                                                                 |
| ----------------------------------- | ----: | ----------------------------------------- | ---------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| encodeContent                       |     4 | M1                                        | `core/docModel.test.js` (toDoc, round trip)                                  | segment string → editor content                                                                        |
| decodeSegment                       |     3 | M1                                        | `core/docModel.test.js` (serialize, round trip)                              | editor content → segment string                                                                        |
| createNewEntitiesFromMap            |     8 | M1                                        | `core/tags.test.js` (chip numbers)                                           | which markup is a tag, pc pair numbering; `sourceTagMap` inheritance moves to `api.setSourceTags` (M3) |
| createNewEntitiesFromMap.gTags      |     7 | M1                                        | `core/tags.test.js` (g labels and numbers)                                   | g equiv-text labels and numbering; its `checkForMissingTags` and Lexiqa cases stay with those modules  |
| manageCaretPositionNearEntity       |    28 | M1                                        | `core/createSegmentEditor.test.js`, `e2e/editing.e2e.js`                     | caret never inside a tag, arrows cross a tag in one press (atomic ranges)                              |
| selectionAroundEntity               |     6 | M1                                        | `core/createSegmentEditor.test.js` (shift + arrow, click)                    | a selection takes a tag whole; the zero-width spaces it handles do not exist in CM6                    |
| resolveEditorCommand                |    15 | M1 (keys), M3 (tag menu, Ctrl+K)          | `core/createSegmentEditor.test.js`, `e2e/editing.e2e.js`                     | Enter, Tab, nbsp, Ctrl/Cmd+Enter unbound; tag menu and Ctrl+K are M3                                   |
| insertEntityAtSelection             |     3 | M1                                        | `core/createSegmentEditor.test.js` (insertRaw)                               | inserting a tag replaces the selection                                                                 |
| getSegmentSlice                     |     6 | M1                                        | `core/createSegmentEditor.test.js`, `e2e/clipboard.e2e.js`                   | copy writes the raw slice                                                                              |
| buildPastedEditorState              |     5 | M1 (content), M3 (wiring)                 | `core/createSegmentEditor.test.js` (transferContent), `e2e/clipboard.e2e.js` | outside paste strips tags, maps `°`                                                                    |
| buildPastedEditorState.segmentSlice |     5 | M1 (content), M3 (wiring)                 | `core/createSegmentEditor.test.js`, `e2e/clipboard.e2e.js`                   | an internal slice pastes with its tags, forged markup does not become tags                             |
| resolveDrop                         |     8 | M1 (refuse onto chip), M3 (Alt/Ctrl copy) | `e2e/clipboard.e2e.js`                                                       | nothing may be dropped onto a tag; move vs copy                                                        |
| buildFragmentFromText               |     2 | M3                                        |                                                                              | text → insertable content                                                                              |
| duplicateFragment                   |     3 | M3                                        |                                                                              | paste of a fragment keeps tags                                                                         |
| insertText                          |     2 | M3                                        |                                                                              | `COPY_GLOSSARY_IN_EDIT_AREA` → `api.insertText`                                                        |
| replaceOccurrences                  |     5 | M3                                        |                                                                              | `REPLACE_SEARCH_RESULTS` → `api.replaceOccurrence`                                                     |
| replaceMultipleText                 |     5 | M3                                        |                                                                              | multi-range replace through the bridge                                                                 |
| addTagEntityToEditor                |     2 | M3                                        |                                                                              | `api.insertMissingTags`                                                                                |
| TagMenu/insertTag                   |     3 | M3                                        |                                                                              | tag menu inserts through `insertRaw`                                                                   |
| transformLexiqaPoints               |     5 | M3                                        |                                                                              | Lexiqa offsets → visible offsets                                                                       |
| updateLexiqaWarnings                |     4 | M3                                        |                                                                              | Lexiqa layer update                                                                                    |
| updateOffsetBasedOnEditorState      |     5 | M3                                        |                                                                              | Lexiqa offsets after edits (decoration mapping in CM6)                                                 |
| activateSearch                      |     6 | M2                                        |                                                                              | search layer                                                                                           |
| activateGlossary                    |     3 | M2                                        |                                                                              | glossary layer                                                                                         |
| activateQaCheckGlossary             |     3 | M2                                        |                                                                              | QA glossary layer                                                                                      |
| activateQaCheckBlacklist            |     3 | M3                                        |                                                                              | blacklist layer (target)                                                                               |
| activateLexiqa                      |     3 | M2                                        |                                                                              | Lexiqa layer                                                                                           |
| canDecorateRange                    |     6 | M2                                        |                                                                              | which tags a layer may cover                                                                           |
| createICUDecorator                  |    15 | M2                                        |                                                                              | ICU layer                                                                                              |
| getSelectedText                     |     2 | M2                                        |                                                                              | selection text for the source toolbar                                                                  |
| getSelectedTextWithTags             |     4 | M2                                        |                                                                              | `api.getSelection().textWithTags` (M1 has the api, M2 the callers)                                     |
| getSelectedTextWithoutEntities      |     4 | M2                                        |                                                                              | `api.getSelection().textWithoutTags`                                                                   |
| getEditorRelativeSelectionOffset    |     4 | M2                                        |                                                                              | selection toolbar position (`coordsAtPos`)                                                             |
| matchTagInEditor                    |     4 | M2                                        |                                                                              | tag click → matching tag in the other editor                                                           |

## Keep

| Test file                  | Tests | Reason                                                                                      |
| -------------------------- | ----: | ------------------------------------------------------------------------------------------- |
| tagModel                   |    22 | tag signatures, read by `segmentModel`                                                      |
| tagUtils                   |    36 | segment string helpers                                                                      |
| tagUtils.tagIndex          |    17 | tag numbering on strings                                                                    |
| compressibleTagUtils       |    27 | pair numbering used by `tokenize`; its editor-side meaning is ported in `core/tags.test.js` |
| decodeTagInfo              |     6 | tag label decoding                                                                          |
| matchTag                   |     5 | `</g>` label pairing on strings                                                             |
| htmlUtils                  |     9 | string helpers                                                                              |
| textUtils                  |     9 | string helpers                                                                              |
| prepareTextForLexiqa       |     3 | Lexiqa text from a segment string                                                           |
| findTagWithRegex           |     4 | string regex helper, no Draft                                                               |
| textConstants              |     2 | word delimiter regex                                                                        |
| TagMenu/checkForMissingTag |    16 | missing tags on strings (tag menu order, R3)                                                |
| TagMenu/TagBox             |     6 | reused tag menu UI (plan open question 4)                                                   |
| TagMenu/TagSuggestion      |     4 | reused tag menu UI                                                                          |

## Delete with Draft

| Test file                   | Tests | Reason                                                 |
| --------------------------- | ----: | ------------------------------------------------------ |
| getEntityStrategy           |     4 | Draft decorator strategy                               |
| applyEntityToContentBlock   |     3 | Draft entity on a content block                        |
| entityBuilder               |     2 | Draft entity construction                              |
| linkEntities                |     3 | links Draft entity pairs                               |
| beautifyEntities            |     3 | Draft entity text replacement                          |
| updateEntityData            |     4 | Draft entity data                                      |
| getEntities                 |     3 | reads Draft entities from blocks                       |
| getEntitiesInFragment       |     3 | reads Draft entities from a fragment                   |
| splitBlockAtSelection       |     2 | Draft block split (Enter is a `\n` in CM6)             |
| removeNewLineInContentState |     5 | Draft line-feed entities → blocks (a real `\n` in CM6) |
| splitOnTagPlaceHolder       |     3 | Draft blocks split on line breaks                      |
| selectionIsEntity           |     5 | Draft selection on an entity (atomic ranges in CM6)    |
| tagFromEntity               |     1 | Draft entity → tag struct                              |
| tagFromTagType              |     2 | tag struct for a new Draft entity                      |
| editorConstants             |     1 | Draft decorator names                                  |
| index                       |     1 | DMU barrel export                                      |

## Totals

|         |  Files |   Tests |
| ------- | -----: | ------: |
| port    |     34 |     191 |
| keep    |     14 |     166 |
| delete  |     16 |      45 |
| **all** | **64** | **402** |
