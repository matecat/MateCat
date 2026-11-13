// Edit parity: for the same segment and the same edit, the CodeMirror target saves what the Draft target (Editarea)
// saves. docModel.test.js compares load -> save; this file compares load -> edit -> save, then undo and redo.
//
// Draft is driven the way Editarea drives it, through the same helpers and the same Draft key commands; where Editarea
// only reacts to a DOM event, the pure calls it makes are run in the same order, with the Editarea lines cited.
// CodeMirror is driven through its real api: the editor handle, its keymap (runScopeHandlers), and its DOM handlers
// for paste, cut, copy and drop. Where the two save different strings, the case is listed in INTENTIONAL with the
// reason, and the test checks it still differs.
//
// The segments and the edits on both sides live in __parity__/actions.js, shared with domParity.test.js.
//
// It imports Draft (only test files may), and goes away with Draft at M5.
import {EditorState as DraftState} from 'draft-js'
import DraftMatecatUtils from '../../segments/utils/DraftMatecatUtils'
import {initTagSignature} from '../../../utils/segmentText/tagModel'
import {createSegmentEditor} from './createSegmentEditor'
import {
  ADD_MISSING_TAGS,
  CARET_ACTIONS,
  COPY,
  DROP_ACTIONS,
  FIXTURES,
  REPLACE_TRANSLATION,
  SELECTION_ACTIONS,
  clean,
  cmPoints,
  draftLoad,
  draftPoints,
  draftSave,
  draftSelection,
  key,
  lexiqaReplace,
  markNames,
  markedWith,
  searchReplace,
  tagSuggestions,
} from './__parity__/actions'

beforeAll(() => {
  const rect = {left: 0, right: 0, top: 0, bottom: 0, width: 0, height: 0}
  Range.prototype.getClientRects = () => []
  Range.prototype.getBoundingClientRect = () => rect
})

// ---------- the cases ----------

const caseList = []
FIXTURES.forEach((f) => {
  const fx = {...f, clean: clean(f.target)}
  const names = markNames(f.target)
  names.forEach((n) =>
    Object.entries(CARET_ACTIONS).forEach(([action, impl]) =>
      caseList.push({fx, action, impl, at: [n]}),
    ),
  )
  ;(f.sels ?? []).forEach((sel) => {
    Object.entries(SELECTION_ACTIONS).forEach(([action, impl]) =>
      caseList.push({fx, action, impl, at: sel}),
    )
    caseList.push({fx, action: 'copy', impl: COPY, at: sel, clip: true})
  })
  // A selection whose end is dragged into the middle of the next tag: the new editor takes the whole chip, Draft
  // (where a tag is one character) can only cover it.
  if (names.includes('bt'))
    caseList.push({
      fx,
      action: 'delete a selection ending inside a tag',
      impl: key('Backspace'),
      at: [names[0], 'bt'],
      intoTag: true,
    })
  ;(f.drops ?? []).forEach((drop) =>
    Object.entries(DROP_ACTIONS).forEach(([action, impl]) =>
      caseList.push({fx, action, impl, at: drop, oneStep: true}),
    ),
  )
  ;(f.searches ?? []).forEach(([term, index, text]) =>
    caseList.push({
      fx,
      action: `search replace occurrence ${index} of ${JSON.stringify(term)} with ${JSON.stringify(text)}`,
      at: [],
      impl: searchReplace(term, index, text),
    }),
  )
  caseList.push({
    fx,
    action: 'add all missing source tags',
    at: [],
    impl: ADD_MISSING_TAGS,
  })
  caseList.push({
    fx,
    action: 'REPLACE_TRANSLATION',
    at: [],
    oneStep: true,
    impl: REPLACE_TRANSLATION,
  })
  ;(f.lexiqa ?? []).forEach(([word, index, suggestion]) =>
    caseList.push({
      fx,
      action: `lexiqa replace occurrence ${index} of ${JSON.stringify(word)} with ${JSON.stringify(suggestion)}`,
      at: [],
      oneStep: true,
      changes: true,
      impl: lexiqaReplace(fx, word, index, suggestion),
    }),
  )
})

const caseId = (c) =>
  `${c.fx.name} | ${c.action}${c.at.length ? ` @ ${c.at.join('..')}` : ''}`

const editors = []
afterEach(() => {
  editors.splice(0).forEach((e) => e.destroy())
  document.body.innerHTML = ''
})

const runDraft = (c) => {
  const {fx} = c
  let state = draftLoad(fx.clean)
  const loaded = draftSave(state)
  const p = c.at.length ? draftPoints(markedWith(fx.target, c.at), state) : []
  // In Draft the selection can only cover the tag: it ends where the next entity ends.
  if (c.intoTag) {
    const blocks = state.getCurrentContent().getBlocksAsArray()
    const key = blocks[p[1].block].getKey()
    const entity = DraftMatecatUtils.getEntities(state).find(
      (e) => e.blockKey === key && e.start >= p[1].offset,
    )
    p[1] = {...p[1], offset: entity.end}
  }
  if (p.length)
    state = DraftState.acceptSelection(state, draftSelection(state, p[0], p[1]))
  const result = c.impl.draft(state, p, fx)
  const clip = c.clip ? result : null
  if (!c.clip) state = result
  const edit = draftSave(state)
  const undo1 = draftSave(DraftState.undo(state))
  let s = state
  while (s.getUndoStack().size) s = DraftState.undo(s)
  const undoAll = draftSave(s)
  while (s.getRedoStack().size) s = DraftState.redo(s)
  return {loaded, clip, edit, undo1, undoAll, redoAll: draftSave(s)}
}

const runCm = (c) => {
  const {fx} = c
  const parent = document.body.appendChild(document.createElement('div'))
  const {sourceTags} = tagSuggestions(fx, draftLoad(fx.clean))
  const editor = createSegmentEditor({
    parent,
    value: fx.clean,
    mode: 'target',
    dir: fx.rtl ? 'rtl' : 'ltr',
    tagMenu: {canOpen: () => sourceTags.length > 0},
  })
  editors.push(editor)
  const loaded = editor.getValue()
  const found = c.at.length
    ? cmPoints(markedWith(fx.target, c.at), fx.clean)
    : {}
  const p = c.at.map((_, i) => found[i])
  if (c.intoTag) p[1] += 1
  if (p.length) {
    editor.view.dispatch({
      selection: {anchor: p[0], head: p[1] ?? p[0]},
      userEvent: 'select',
    })
  }
  const result = c.impl.cm(editor, p, fx)
  const clip = c.clip ? result : null
  const edit = editor.getValue()
  editor.undo()
  const undo1 = editor.getValue()
  while (editor.undo());
  const undoAll = editor.getValue()
  while (editor.redo());
  return {loaded, clip, edit, undo1, undoAll, redoAll: editor.getValue()}
}

// Cases where CodeMirror deliberately saves something else than Draft: `id` matches case ids, `phase` is `edit`
// (the saved string after the action), `undo1` (after one undo) or `clip` (the slice a copy writes).
const INTENTIONAL = [
  {
    id: /\| paste plain text with a degree sign @/,
    phase: 'edit',
    // Draft wrong. buildPastedEditorState turns every pasted ° into an nbsp: the Draft nbsp entity shows as °, so a
    // copy out of a Draft editor once carried the nbsp as °. A real degree sign ('25°C' from a web page) became an
    // nbsp. A copy out of either editor now carries the segment slice in text/html, so the plain text never has to
    // stand for a tag, and the new editor keeps the degree sign.
    reason: 'Draft turns a pasted degree sign into an nbsp',
  },
  {
    id: /\| (alt|ctrl) \+ drag and drop \(copy\) @/,
    phase: 'edit',
    // Draft wrong. resolveDrop moves whatever is dragged out of the editor and ignores the modifier; Alt (Option on
    // macOS) or Ctrl while dropping is the browser's copy gesture, which the new editor honours.
    reason: 'Draft moves the dragged text even with the copy modifier held',
  },
  {
    id: /^(nested g and x \| capitalize @ (s\.\.e|bt\.\.ge)|pc, dataRef and compressed g \(e2e ph\) \| capitalize @ s\.\.e|multi-line g \| capitalize @ l2\.\.e)$/,
    phase: 'edit',
    // Draft wrong. Draft keeps a zero-width space on each side of a tag, and formatText only starts a word after a
    // space, so a word right after a tag that follows a space ('A <g>b') is never capitalized. The new editor sees
    // the space before the tag.
    reason: 'Draft does not capitalize a word that starts right after a tag',
  },
  {
    id: /^(nbsp tab line feed \| (upper case|capitalize) @ (s\.\.e|an\.\.l2)|multi-line g \| (upper case @ (s\.\.e|s\.\.gi)|capitalize @ s\.\.e))$/,
    phase: 'edit',
    // Draft wrong. getSelectedTextWithoutEntities reads only the line the selection starts on, with the offsets of
    // the line it ends on (Editarea formatSelection), so a selection across a line break formats part of the first
    // line or nothing at all. The new editor formats all the selected text.
    reason: 'Draft formats only part of a selection that crosses a line break',
  },
  {
    id: /^bx ex \| delete a selection ending inside a tag @ s\.\.bt$/,
    phase: 'edit',
    // Draft wrong. Deleting everything up to the last tag leaves the zero-width space Draft keeps after it. Draft's
    // ContentState.hasText() counts zero-width spaces as no text, so decodeSegment returns the raw block text, and
    // Editarea stores a translation made of one invisible U+200B. The new editor saves an empty string.
    reason:
      'Draft saves a lone zero-width space for a target emptied up to a tag',
  },
  {
    id: /^pc, dataRef and compressed g \(e2e ph\) \| search replace occurrence 1 of "b" with "X"$/,
    phase: 'edit',
    // Draft wrong. The cattool numbers the hits in its search text, tag labels included, and Draft replaces the
    // n-th match of its block text, where a tag is its label: when the current hit is inside a label, Modifier.replaceText
    // rewrites the tag's characters, and the save keeps them as plain text. Here hit 1 is in the <b> label: Draft saves
    // `<X>` as text in place of the tag. A tag is locked, so the new editor counts the hit and replaces nothing.
    reason:
      'Draft turns a tag into plain text when the replaced hit is in its label',
  },
  {
    id: /^(?!plain|nbsp tab line feed|entities).* \| tag menu: < then Enter \(first entry\) @/,
    phase: 'undo1',
    // A choice, both defensible. Draft merges the typed `<` and the tag that replaces it into one undo step (two
    // 'insert-characters' pushes in a row). The new editor keeps the `<` as its own step, as an accepted
    // autocompletion does: the first undo gives back the `<` that was typed, the second removes it. Undoing all
    // still restores the original (checked below). Fixtures with no source tags never open the menu.
    reason: 'the typed < is its own undo step in the new editor',
  },
]

const intentional = (id, phase) =>
  INTENTIONAL.find((r) => r.phase === phase && r.id.test(id))

const CASES = caseList.map((c) => [caseId(c), c])

beforeAll(() => {
  // Visible spaces off: the default user settings, as the app sets them at load.
  initTagSignature({show_whitespace: 0})
})
beforeEach(() => {
  global.config.lfPlaceholder = '##$_0A$##'
})
afterEach(() => {
  global.config.isTargetRTL = false
})

describe('an edit saves what Draft saves', () => {
  test.each(CASES)('%s', (id, c) => {
    global.config.isTargetRTL = Boolean(c.fx.rtl)
    const draft = runDraft(c)
    const cm = runCm(c)
    // Equal to Draft, or, for an intentional difference, still different.
    const parity = (phase) =>
      intentional(id, phase) ? {differs: cm[phase] !== draft[phase]} : cm[phase]
    const expected = (phase) =>
      intentional(id, phase) ? {differs: true} : draft[phase]
    const phases = ['clip', 'edit', 'undo1']
    expect(phases.map(parity)).toEqual(phases.map(expected))
    // In each editor, undoing everything gives back what was loaded and redoing everything the edit; an edit marked
    // `oneStep` is taken back by a single undo. An edit marked `changes` changed the segment: two no-ops agree too.
    const history = (r) => ({
      undoAll: r.undoAll === r.loaded,
      redoAll: r.redoAll === r.edit,
      oneStep: !c.oneStep || r.undo1 === r.loaded,
      changed: !c.changes || r.edit !== r.loaded,
    })
    const whole = {undoAll: true, redoAll: true, oneStep: true, changed: true}
    expect({cm: history(cm), draft: history(draft)}).toEqual({
      cm: whole,
      draft: whole,
    })
  })

  test('every intentional difference still matches a case', () => {
    INTENTIONAL.forEach((rule) => {
      expect({
        rule: rule.id.source,
        matches: CASES.some(([id]) => rule.id.test(id)),
      }).toEqual({rule: rule.id.source, matches: true})
    })
  })
})
