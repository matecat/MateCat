// Edit parity: for the same segment and the same edit, the CodeMirror target saves what the Draft target (Editarea)
// saves. docModel.test.js compares load -> save; this file compares load -> edit -> save, then undo and redo.
//
// Draft is driven the way Editarea drives it, through the same helpers and the same Draft key commands; where Editarea
// only reacts to a DOM event, the pure calls it makes are run in the same order, with the Editarea lines cited.
// CodeMirror is driven through its real api: the editor handle, its keymap (runScopeHandlers), and its DOM handlers
// for paste, cut, copy and drop. Where the two save different strings, the case is listed in INTENTIONAL with the
// reason, and the test checks it still differs.
//
// It imports Draft (only test files may), and goes away with Draft at M5.
import {
  EditorState as DraftState,
  Modifier,
  SelectionState,
  getDefaultKeyBinding,
} from 'draft-js'
import keyCommandPlainBackspace from 'draft-js/lib/keyCommandPlainBackspace'
import keyCommandPlainDelete from 'draft-js/lib/keyCommandPlainDelete'
import keyCommandInsertNewline from 'draft-js/lib/keyCommandInsertNewline'
import {runScopeHandlers} from '@codemirror/view'
import {cloneDeep} from 'lodash'
import DraftMatecatUtils from '../../segments/utils/DraftMatecatUtils'
import updateOffsetBasedOnEditorState from '../../segments/utils/DraftMatecatUtils/updateOffsetBasedOnEditorState'
import canDecorateRange from '../../segments/utils/DraftMatecatUtils/canDecorateRange'
import {LEXIQA_DECORATOR} from '../../segments/utils/DraftMatecatUtils/editorConstants'
import LexiqaUtils from '../../../utils/lxq.main'
import resolveEditorCommand from '../../segments/utils/DraftMatecatUtils/resolveEditorCommand'
import buildPastedEditorState from '../../segments/utils/DraftMatecatUtils/buildPastedEditorState'
import resolveDrop from '../../segments/utils/DraftMatecatUtils/resolveDrop'
import insertTag from '../../segments/utils/DraftMatecatUtils/TagMenu/insertTag'
import checkForMissingTags from '../../segments/utils/DraftMatecatUtils/TagMenu/checkForMissingTag'
import matchTypingSequence from '../../../utils/matchTypingSequence/matchTypingSequence'
import {
  initTagSignature,
  tagSignatures,
} from '../../../utils/segmentText/tagModel'
import TextUtils from '../../../utils/textUtils'
import {
  readSegmentClipboard,
  segmentClipboardHtml,
} from '../../segmentView/segmentModel'
import {SEGMENTS} from '../e2e/fixtures'
import {targetLayers} from '../matecat/TargetEditor'
import {tooltipContent} from '../matecat/layerTooltip'
import {createSegmentEditor} from './createSegmentEditor'
import {insertRaw, toDoc} from './docModel'
import {layerRangesAt, replaceLayerRange, setLayer} from './layers'

// ---------- fixtures ----------
// Named positions are written into the segment as ⟨name⟩, always between tags, never inside one.

const MARK = /⟨(\w+)⟩/g
const at = (segment, needle, name, {after = false} = {}) => {
  const i = segment.indexOf(needle)
  if (i < 0) throw new Error(`no ${needle} in ${segment}`)
  const o = after ? i + needle.length : i
  return segment.slice(0, o) + `⟨${name}⟩` + segment.slice(o)
}

const PH_TARGET = [
  ['Ciao', 's', {}],
  ['hai ', 'at', {}],
  [' nuovi', 'm', {}],
].reduce(
  (seg, [needle, name, opts]) => at(seg, needle, name, opts),
  SEGMENTS.ph.target,
)

const FIXTURES = [
  {
    name: 'plain',
    target: '⟨s⟩Hello ⟨m⟩world⟨e⟩',
    source: 'Hello world',
    sels: [
      ['s', 'e'],
      ['m', 'e'],
    ],
    searches: [['o', 1, 'O']],
    lexiqa: [['world', 0, 'earth']],
  },
  {
    name: 'g',
    target: '⟨s⟩Click ⟨bt⟩<g id="1">⟨gi⟩Sa⟨gm⟩ve⟨ge⟩</g>⟨at⟩ now⟨e⟩',
    source: 'Click <g id="1">Save</g> now <x id="2"/>',
    sels: [
      ['s', 'e'],
      ['s', 'gm'],
      ['gm', 'e'],
      ['bt', 'at'],
      ['gi', 'ge'],
    ],
    drops: [
      ['gi', 'ge', 'e'],
      ['gm', 'at', 's'],
    ],
    searches: [
      ['save', 0, 'Keep'],
      ['c', 1, 'K'],
    ],
    lexiqa: [
      ['Save', 0, 'Store'],
      ['now', 0, 'then'],
    ],
  },
  {
    // A tag inside a word: the text after it continues the word.
    name: 'g inside a word',
    target: '⟨s⟩Ka⟨bt⟩<g id="1">⟨gi⟩rl</g>⟨at⟩sson ⟨m⟩and<x id="2"/>⟨ax⟩ co⟨e⟩',
    source: 'Ka<g id="1">rl</g>sson and<x id="2"/> co',
    sels: [
      ['s', 'e'],
      ['gi', 'e'],
      ['bt', 'm'],
    ],
    searches: [
      ['karlsson', 0, 'K'],
      ['s', 1, 'z'],
    ],
    lexiqa: [['sson', 0, 'sen']],
  },
  {
    name: 'nested g and x',
    target:
      '⟨s⟩<g id="1">A ⟨bt⟩<g id="2">⟨gi⟩b⟨ge⟩</g></g>⟨at⟩<x id="3"/>⟨ax⟩ c⟨e⟩',
    source: '<g id="1">A <g id="2">b</g></g><x id="3"/> c',
    sels: [
      ['s', 'e'],
      ['gi', 'ax'],
      ['bt', 'ge'],
    ],
    drops: [['ax', 'e', 'gi']],
    searches: [['b', 0, 'B']],
  },
  {
    name: 'bx ex',
    target: '⟨s⟩<bx id="1"/>⟨at⟩Start ⟨m⟩text⟨bt⟩<ex id="2"/>⟨e⟩',
    source: '<bx id="1"/>Start text<ex id="2"/><x id="3"/>',
    sels: [
      ['s', 'e'],
      ['at', 'm'],
    ],
    drops: [['at', 'm', 'e']],
    searches: [['t', 2, 'T']],
    lexiqa: [['Start', 0, 'Begin']],
  },
  {
    name: 'ph equiv-text',
    target:
      '⟨s⟩You have ⟨bt⟩<ph id="mtc_1" ctype="x-percent-s" equiv-text="base64:JXM="/>⟨at⟩ new ⟨m⟩messages⟨e⟩',
    source:
      'You have <ph id="mtc_1" ctype="x-percent-s" equiv-text="base64:JXM="/> new <ph id="mtc_2" ctype="x-mustache" equiv-text="base64:e3tjb3VudH19"/> messages',
    sels: [
      ['s', 'e'],
      ['bt', 'm'],
    ],
    drops: [['m', 'e', 's']],
    searches: [['e', 2, 'E']],
    lexiqa: [['new', 0, 'fresh']],
  },
  {
    name: 'pc, dataRef and compressed g (e2e ph)',
    target: PH_TARGET + '⟨e⟩',
    source: SEGMENTS.ph.source,
    sels: [
      ['s', 'e'],
      ['at', 'm'],
    ],
    searches: [['a', 3, 'A']],
  },
  {
    name: 'nbsp tab line feed',
    target:
      '⟨s⟩one⟨bt⟩##$_A0$##⟨an⟩two##$_09$##⟨at⟩three##$_0A$##⟨l2⟩line ⟨m⟩two⟨e⟩',
    source: 'one two three line two',
    sels: [
      ['s', 'e'],
      ['an', 'l2'],
      ['l2', 'e'],
    ],
    drops: [['l2', 'm', 'an']],
    searches: [
      ['t', 2, 'T'],
      ['two', 1, '2'],
    ],
    lexiqa: [
      ['two', 0, 'deux'],
      ['two', 1, 'zwei'],
      ['three', 0, '3'],
    ],
  },
  {
    name: 'entities',
    target: '⟨s⟩R&amp;D ⟨m⟩&lt;b&gt; 5 &gt; 3⟨e⟩',
    source: 'R&amp;D &lt;b&gt; 5 &gt; 3',
    sels: [
      ['s', 'e'],
      ['s', 'm'],
    ],
    drops: [['s', 'm', 'e']],
    searches: [
      ['&', 0, '+'],
      ['<b>', 0, '<i>'],
    ],
    lexiqa: [['5', 0, 'five']],
  },
  {
    name: 'rtl',
    rtl: true,
    target: '⟨s⟩שלום ⟨bt⟩<g id="1">⟨gi⟩עולם</g>⟨at⟩ טוב⟨e⟩',
    source: 'Hello <g id="1">world</g> good',
    sels: [
      ['s', 'e'],
      ['bt', 'e'],
      ['gi', 'at'],
    ],
    searches: [['ו', 1, 'ש']],
    lexiqa: [['טוב', 0, 'רע']],
  },
  {
    name: 'cjk emoji',
    target: '⟨s⟩你好⟨bt⟩<x id="1"/>⟨at⟩世界 😀⟨m⟩ ok⟨e⟩',
    source: 'Hello <x id="1"/>world 😀 ok',
    sels: [
      ['s', 'e'],
      ['at', 'm'],
    ],
    drops: [['at', 'm', 's']],
    searches: [['界', 0, '间']],
    lexiqa: [
      ['世界', 0, '地球'],
      ['ok', 0, 'okay'],
    ],
  },
  {
    name: 'multi-line g',
    target: '⟨s⟩line one##$_0A$##⟨l2⟩line <g id="1">⟨gi⟩two</g>⟨at⟩ end⟨e⟩',
    source: 'line one line <g id="1">two</g> end',
    sels: [
      ['s', 'e'],
      ['l2', 'e'],
      ['s', 'gi'],
    ],
    drops: [['gi', 'at', 's']],
    searches: [
      ['line', 1, 'row'],
      ['e', 3, 'E'],
    ],
    lexiqa: [
      ['one', 0, 'uno'],
      ['line', 1, 'row'],
      ['two', 0, 'three'],
      ['end', 0, 'fin'],
    ],
  },
]

// ---------- positions ----------
const MARKERS = ['', '', '']
const clean = (target) => target.replace(MARK, '')
const markedWith = (target, names) =>
  target.replace(MARK, (_, n) =>
    names.includes(n) ? MARKERS[names.indexOf(n)] : '',
  )
const markNames = (target) => [...target.matchAll(MARK)].map((m) => m[1])

// Finds the markers in `text`, as offsets in the text without them.
const findMarkers = (text) => {
  const found = {}
  let removed = 0
  for (let i = 0; i < text.length; i++) {
    const k = MARKERS.indexOf(text[i])
    if (k >= 0) found[k] = i - removed++
  }
  return found
}
const stripMarkers = (text) => text.replace(/[-]/g, '')

// ---------- Draft ----------
const draftLoad = (raw) =>
  DraftMatecatUtils.encodeContent(DraftState.createEmpty(), raw).editorState
const draftSave = (state) =>
  DraftMatecatUtils.decodeSegment(state).decodedSegment
const blockTexts = (state) =>
  state
    .getCurrentContent()
    .getBlocksAsArray()
    .map((b) => b.getText())

// {block, offset} of each marker; the marked segment must load as the clean one plus the markers.
const draftPoints = (markedRaw, cleanState) => {
  const marked = blockTexts(draftLoad(markedRaw))
  expect(marked.map(stripMarkers)).toEqual(blockTexts(cleanState))
  const points = []
  marked.forEach((text, block) => {
    Object.entries(findMarkers(text)).forEach(([k, offset]) => {
      points[k] = {block, offset}
    })
  })
  return points
}

const draftSelection = (state, anchor, head = anchor) => {
  const blocks = state.getCurrentContent().getBlocksAsArray()
  const anchorKey = blocks[anchor.block].getKey()
  const focusKey = blocks[head.block].getKey()
  return SelectionState.createEmpty(anchorKey).merge({
    anchorKey,
    anchorOffset: anchor.offset,
    focusKey,
    focusOffset: head.offset,
    isBackward:
      head.block < anchor.block ||
      (head.block === anchor.block && head.offset < anchor.offset),
    hasFocus: true,
  })
}

const typingWordJoiner = matchTypingSequence(
  [
    [50, 98],
    [48, 96],
    [54, 102],
    [48, 96],
  ],
  2000,
)

// A key on the Draft target: Editarea myKeyBindingFn (Editarea.js:586-607) then handleKeyCommand
// (Editarea.js:609-680), then Draft's own command when the handler says not-handled (draft-js editOnKeyDown).
// `menu` is the tag menu part of the Editarea state (displayPopover, triggerText, focusedTagIndex, suggestions).
const draftKey = (state, e, menu = {open: false}) => {
  const resolved = resolveEditorCommand(e, {
    displayPopover: menu.open,
    editorState: state,
    isRTL: Boolean(config.isTargetRTL),
    isChromeBook: false,
    hasSpaceTag: tagSignatures.space,
    selectionIsCaret: () => state.getSelection().isCollapsed(),
    typingWordJoiner,
  })
  if (resolved.clearTriggerText) menu.trigger = null
  // typeTextInEditor (Editarea.js:555-583)
  if (resolved.typeText) {
    state = DraftMatecatUtils.insertText(state, resolved.typeText)
    menu.trigger = resolved.typeText
  }
  if (resolved.editorState) state = resolved.editorState
  const command =
    resolved.command === null ? getDefaultKeyBinding(e) : resolved.command
  const struct = (name) => DraftMatecatUtils.structFromName(name)
  switch (command) {
    case 'toggle-tag-menu':
      if (menu.sourceTags?.length) menu.open = true
      return state
    // acceptTagMenuSelection (Editarea.js:935-975)
    case 'enter-press': {
      const entries = [...menu.missingTags, ...menu.sourceTags]
      menu.open = false
      return insertTag(entries[menu.focused ?? 0], state, menu.trigger)
    }
    // insertTagAtSelection (Editarea.js:682-710)
    case 'insert-tab-tag':
      return insertTag(struct('tab'), state)
    case 'insert-nbsp-tag':
      return insertTag(struct('nbsp'), state)
    case 'insert-word-joiner-tag':
      return insertTag(struct('wordJoiner'), state)
    // not-handled: Draft's own commands
    case 'backspace':
      return keyCommandPlainBackspace(state)
    case 'delete':
      return keyCommandPlainDelete(state)
    case 'split-block':
      return keyCommandInsertNewline(state)
    default:
      return state
  }
}

// What the store holds for the tag menu: the source tag map (SegmentSource, encodeContent's tagRange) and the
// missing tags Editarea computes after every change (updateTranslationInStore, Editarea.js:309-321).
const tagSuggestions = (fixture, targetState) => {
  const sourceTagMap = DraftMatecatUtils.encodeContent(
    DraftState.createEmpty(),
    fixture.source,
  ).tagRange
  const {entitiesRange} = DraftMatecatUtils.decodeSegment(targetState)
  const targetTagMap = DraftMatecatUtils.matchTagInEditor(
    targetState,
    entitiesRange,
  )
  const {missingTags, sourceTags} = checkForMissingTags(
    sourceTagMap,
    targetTagMap,
  )
  return {missingTags, sourceTags, sourceTagMap, targetTagMap}
}

// ---------- CodeMirror ----------
beforeAll(() => {
  const rect = {left: 0, right: 0, top: 0, bottom: 0, width: 0, height: 0}
  Range.prototype.getClientRects = () => []
  Range.prototype.getBoundingClientRect = () => rect
})

const cmPoints = (markedRaw, cleanRaw) => {
  const {text} = toDoc(markedRaw)
  expect(stripMarkers(text)).toBe(toDoc(cleanRaw).text)
  return findMarkers(text)
}

const press = (editor, init) =>
  runScopeHandlers(
    editor.view,
    new KeyboardEvent('keydown', {bubbles: true, cancelable: true, ...init}),
    'editor',
  )

// What the DOM observer dispatches for typed text.
const cmType = (editor, text) => {
  const {view} = editor
  view.dispatch(view.state.replaceSelection(text), {
    userEvent: 'input.type',
    scrollIntoView: true,
  })
}

const fakeTransfer = (initial = {}) => {
  const data = {...initial}
  const type = (t) => (t === 'Text' ? 'text/plain' : t)
  return {
    data,
    getData: (t) => data[type(t)] ?? '',
    setData: (t, value) => (data[type(t)] = value),
    clearData: () => Object.keys(data).forEach((t) => delete data[t]),
  }
}
const fire = (target, name, props = {}) => {
  const event = Object.assign(
    new Event(name, {bubbles: true, cancelable: true}),
    props,
  )
  target.dispatchEvent(event)
  return event
}
const cmDrop = (editor, pos, props) => {
  editor.view.posAtCoords = () => pos
  fire(editor.view.contentDOM, 'drop', props)
}

// ---------- actions ----------
// Each action has a Draft side `draft(state, points, fixture) => state` and a CodeMirror side
// `cm(editor, points, fixture)`; points are in each editor's own terms. A `clip` side returns the clipboard slice
// instead. The harness has already put the selection on points[0]..points[1] (a caret on points[0]).

const KEYS = {
  Backspace: {key: 'Backspace', keyCode: 8},
  Delete: {key: 'Delete', keyCode: 46},
  Enter: {key: 'Enter', keyCode: 13},
  Tab: {key: 'Tab', keyCode: 9},
  nbsp: {key: ' ', code: 'Space', keyCode: 32, ctrlKey: true, shiftKey: true},
  wordJoiner: {
    key: ' ',
    code: 'Space',
    keyCode: 32,
    ctrlKey: true,
    altKey: true,
  },
  openQuote: {
    key: '[',
    code: 'BracketLeft',
    keyCode: 219,
    ctrlKey: true,
    shiftKey: true,
  },
  closeQuote: {key: ']', code: 'BracketRight', keyCode: 221, ctrlKey: true},
  lt: {key: '<', keyCode: 188, shiftKey: true},
  altT: {key: 't', keyCode: 84, altKey: true},
}

const type = (text) => ({
  // Draft types through editOnBeforeInput, which is DraftMatecatUtils.insertText (replaceText over a selection)
  draft: (s) => DraftMatecatUtils.insertText(s, text),
  cm: (ed) => cmType(ed, text),
})

const key = (name) => ({
  draft: (s) => draftKey(s, KEYS[name]),
  cm: (ed) => {
    if (!press(ed, KEYS[name])) throw new Error(`${name} not handled`)
  },
})

// Paste: Editarea pasteFragment -> buildPastedEditorState (Editarea.js:1024-1034).
const paste = ({text = '', html = ''}) => ({
  draft: (s) => buildPastedEditorState({text, html, editorState: s}) ?? s,
  cm: (ed) =>
    fire(ed.view.contentDOM, 'paste', {
      clipboardData: fakeTransfer({'text/plain': text, 'text/html': html}),
    }),
})

const slice = (raw, text) => segmentClipboardHtml({raw, text})

// The tag menu: `<` (or Alt+T), then Enter on the entry `pick` (0 = first missing tag). The CodeMirror host
// (TargetEditor acceptMenuTag/onMenuKey) inserts entries[focused].data.encodedText with insertMenuTag.
const tagMenu = (opener, pick) => ({
  draft: (s, p, fx) => {
    const {missingTags, sourceTags} = tagSuggestions(fx, s)
    const menu = {open: false, missingTags, sourceTags, focused: pick}
    s = draftKey(s, KEYS[opener], menu)
    return menu.open ? draftKey(s, KEYS.Enter, menu) : s
  },
  cm: (ed, p, fx) => {
    const {missingTags, sourceTags} = tagSuggestions(fx, draftLoad(fx.clean))
    // With no tag to offer the menu stays shut and the key types as usual (Alt+T types nothing).
    if (!press(ed, KEYS[opener]) && opener === 'lt') cmType(ed, '<')
    const entries = [...missingTags, ...sourceTags]
    if (entries.length) ed.insertMenuTag(entries[pick].data.encodedText)
  },
})

const format = (name) => ({
  // Editarea formatSelection (Editarea.js:1132-1158)
  draft: (s) => {
    if (s.getSelection().isCollapsed()) return s
    const selected = DraftMatecatUtils.getSelectedTextWithoutEntities(s).map(
      (sel) => ({...sel, value: DraftMatecatUtils.formatText(sel.value, name)}),
    )
    return DraftMatecatUtils.replaceMultipleText(s, selected)
  },
  // TargetEditor formatSelection (TargetEditor.js:550-554), bridge.formatText = DraftMatecatUtils.formatText
  cm: (ed) =>
    ed.transformSelection((text) => DraftMatecatUtils.formatText(text, name)),
})

const CARET_ACTIONS = {
  'type a character': type('x'),
  'type a word': type('né '),
  backspace: key('Backspace'),
  delete: key('Delete'),
  'enter (line break)': key('Enter'),
  'tab key': key('Tab'),
  'nbsp shortcut': key('nbsp'),
  'word joiner shortcut': key('wordJoiner'),
  'smart open double quote': key('openQuote'),
  'smart close single quote': key('closeQuote'),
  'paste plain text with tag markup': paste({
    text: 'x <b>y</b> <x id="9"/>z',
    html: '<p>x <b>y</b></p>',
  }),
  'paste plain text with a degree sign': paste({text: '25°C'}),
  'paste plain text with a tab': paste({text: 'a\tb'}),
  'paste plain text with CRLF': paste({text: 'a\r\nb'}),
  'paste plain text with & and <': paste({text: 'R&D <5'}),
  'paste a segment slice with tags': paste({
    text: 'X1Y',
    html: slice('X<g id="1">Y</g>&amp;', 'XY&'),
  }),
  'paste a slice with nbsp and line feed': paste({
    text: 'a b\nc',
    html: slice('a##$_A0$##b##$_0A$##c', 'a b\nc'),
  }),
  'tag menu: < then Enter (first entry)': tagMenu('lt', 0),
  'tag menu: Alt+T then Enter (first entry)': tagMenu('altT', 0),
  'copy glossary term at caret': {
    // copyGlossaryToEditArea (Editarea.js:482-498); TargetEditor copyGlossary (TargetEditor.js:396-403)
    draft: (s) => DraftMatecatUtils.insertText(s, 'R&D <i>'),
    cm: (ed) => ed.insertText('R&D <i>'),
  },
  'drop a slice from another segment': {
    // handleDrop -> resolveDrop, not dragging from this editor (Editarea.js:1062-1082)
    draft: (s) =>
      resolveDrop({
        editorState: s,
        selection: s.getSelection(),
        html: slice('<x id="7"/>Z', 'Z'),
        draggingFromEditArea: false,
      }).editorState ?? s,
    cm: (ed) =>
      cmDrop(ed, ed.view.state.selection.main.head, {
        dataTransfer: fakeTransfer({
          'text/html': slice('<x id="7"/>Z', 'Z'),
          'text/plain': 'Z',
        }),
      }),
  },
}

// Draft's cut: copyFragment writes the slice (Editarea.js:1039-1051), then draft-js editOnCut removes the fragment
// with Modifier.removeRange(..., 'forward') pushed as 'remove-range'.
const draftCut = (s) =>
  DraftState.push(
    s,
    Modifier.removeRange(s.getCurrentContent(), s.getSelection(), 'forward'),
    'remove-range',
  )

const SELECTION_ACTIONS = {
  'delete selection (backspace)': key('Backspace'),
  'replace selection by typing': type('new'),
  'paste plain text over selection': paste({text: 'pasted'}),
  'paste a slice over selection': paste({
    text: 'X',
    html: slice('<x id="5"/>X', 'X'),
  }),
  cut: {
    draft: draftCut,
    cm: (ed) =>
      fire(ed.view.contentDOM, 'cut', {clipboardData: fakeTransfer()}),
  },
  'upper case': format('uppercase'),
  'lower case': format('lowercase'),
  capitalize: format('capitalize'),
}

// The clipboard slice a copy (and a cut) writes: Draft getSegmentSlice, CodeMirror's copy handler.
const COPY = {
  draft: (s) => DraftMatecatUtils.getSegmentSlice(s)?.raw ?? null,
  cm: (ed) => {
    const clipboardData = fakeTransfer()
    fire(ed.view.contentDOM, 'copy', {clipboardData})
    return readSegmentClipboard(clipboardData.getData('text/html'))
  },
}

// Drag the selection points[0]..points[1] and drop it at points[2]: Editarea writes the slice on dragstart
// (onDragEvent, Editarea.js:1053-1056) and handleDrop runs resolveDrop with draggingFromEditArea.
const dragDrop = (modifiers = {}) => ({
  draft: (s, p) => {
    const html = segmentClipboardHtml(DraftMatecatUtils.getSegmentSlice(s))
    return (
      resolveDrop({
        editorState: s,
        selection: draftSelection(s, p[2]),
        html,
        draggingFromEditArea: true,
      }).editorState ?? s
    )
  },
  cm: (ed, p) => {
    const dataTransfer = fakeTransfer()
    fire(ed.view.contentDOM, 'dragstart', {dataTransfer})
    cmDrop(ed, p[2], {dataTransfer, ...modifiers})
  },
})

const DROP_ACTIONS = {
  'drag and drop (move)': dragDrop(),
  'alt + drag and drop (copy)': dragDrop({altKey: true}),
  'ctrl + drag and drop (copy)': dragDrop({ctrlKey: true}),
}

// A Lexiqa suggestion clicked on a target warning. The store holds the Lexiqa text of the target
// (lxqDecodedTranslation: prepareTextForLexiqa of the saved segment, Editarea.js:323-324) and Lexiqa's results on it:
// one spelling warning on occurrence `index` of `word`, offering `suggestion`. Both sides read them through the real
// LexiqaUtils.getRanges and buildTooltipMessages.
const LEXIQA_SID = '1'
const LEXIQA_MODULES = [
  'numbers',
  'punctuation',
  'spaces',
  'specialchardetect',
  'mspolicheck',
  'urls',
  'spelling',
  'glossary',
  'blacklist',
]
const lexiqaSegment = (fx, word, index, suggestion) => {
  const lxqDecodedTranslation = DraftMatecatUtils.prepareTextForLexiqa(
    draftSave(draftLoad(fx.clean)),
  )
  let start = -1
  for (let i = 0; i <= index; i++)
    start = lxqDecodedTranslation.indexOf(word, start + 1)
  if (start < 0)
    throw new Error(`no ${word} #${index} in ${lxqDecodedTranslation}`)
  const warning = {
    start,
    end: start + word.length,
    module: 'spelling',
    errorid: `lxq_${LEXIQA_SID}_0_t`,
    color: '#563d7c',
    suggestions: [suggestion],
  }
  return {
    sid: LEXIQA_SID,
    lxqDecodedTranslation,
    // every module Lexiqa reports on, as it sends them (cleanRanges needs them all)
    lexiqa: {
      target: {
        ...Object.fromEntries(LEXIQA_MODULES.map((m) => [m, []])),
        spelling: [warning],
      },
    },
  }
}

const lexiqaReplace = (fx, word, index, suggestion) => ({
  draft: (s) => {
    const segment = lexiqaSegment(fx, word, index, suggestion)
    // addLexiqaDecorator (Editarea.js:206-227): Lexiqa offsets made relative to each block, no tag correction
    const ranges = LexiqaUtils.getRanges(
      cloneDeep(segment.lexiqa.target),
      segment.lxqDecodedTranslation,
      false,
    )
    const warnings = updateOffsetBasedOnEditorState(s, ranges)
    // activateLexiqa strategy (activateLexiqa.js:84-96): the warnings of each block that canDecorateRange allows
    const content = s.getCurrentContent()
    const warn = warnings.find((w) => {
      const block = content.getBlockForKey(w.blockKey)
      return canDecorateRange(w.start, w.end, block, content, LEXIQA_DECORATOR)
    })
    if (!warn) return s
    // LexiqaHighlight getWarning + LexiqaTooltipInfo click (LexiqaHighlight.component.js:21-55,
    // LexiqaTooltipInfo.component.js:37-46): the suggestion carries the warning's offsets and block
    const {start, end, msg} = LexiqaUtils.buildTooltipMessages(
      warn,
      segment.sid,
      false,
    ).find((m) => m.type === 'suggestion')
    // replaceWordAt (Editarea.js:531-551)
    const selection = s.getSelection().merge({
      anchorKey: warn.blockKey,
      anchorOffset: start,
      focusKey: warn.blockKey,
      focusOffset: end,
      isBackward: false,
    })
    return DraftState.push(s, Modifier.replaceText(content, selection, msg))
  },
  cm: (ed) => {
    const {view} = ed
    const segment = lexiqaSegment(fx, word, index, suggestion)
    // TargetEditor targetLayers (TargetEditor.js:90-147): the lexiqa layer, set volatile as the target sets it
    const layer = targetLayers(view.state, segment, false).find(
      (l) => l.name === 'lexiqa',
    )
    view.dispatch({
      effects: setLayer.of({
        name: layer.name,
        ranges: layer.ranges(),
        volatile: layer.volatile,
      }),
    })
    const [range] = layer.ranges()
    if (!range) return
    // The hover tooltip on the mark (createLayerTooltip, layerTooltip.js:145-155) and its suggestion click
    const content = tooltipContent(layerRangesAt(view.state, range.from), {
      onReplace: (r, text) => replaceLayerRange(view, r, text),
    })
    const pick = content.range.data.messages.find(
      (m) => m.type === 'suggestion',
    )
    content.element.props.onReplaceWord({
      newWord: pick.msg,
      start: pick.start,
      end: pick.end,
    })
  },
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
      impl: {
        // replaceCurrentSearch (Editarea.js:274-301); TargetEditor replaceSearchResult (TargetEditor.js:377-395)
        draft: (s) =>
          DraftMatecatUtils.replaceOccurrences(s, term, text, index),
        cm: (ed) =>
          ed.replaceOccurrence(
            new RegExp(TextUtils.escapeRegExp(term), 'gi'),
            index,
            text,
          ),
      },
    }),
  )
  caseList.push({
    fx,
    action: 'add all missing source tags',
    at: [],
    impl: {
      // addMissingSourceTagsToTarget (Editarea.js:1160-1183): addTagEntityToEditor per missing tag
      draft: (s, p, fx) =>
        tagSuggestions(fx, s).missingTags.reduce(
          (state, tag) => DraftMatecatUtils.addTagEntityToEditor(state, tag),
          s,
        ),
      // TargetEditor addMissingSourceTagsToTarget (TargetEditor.js:518-529)
      cm: (ed, p, fx) => {
        const {missingTags} = tagSuggestions(fx, draftLoad(fx.clean))
        const raw = missingTags.map((tag) => tag.data.encodedText).join('')
        const end = ed.view.state.doc.length
        ed.view.dispatch(insertRaw(ed.view.state, end, end, raw), {
          userEvent: 'input',
        })
      },
    },
  })
  caseList.push({
    fx,
    action: 'REPLACE_TRANSLATION',
    at: [],
    oneStep: true,
    impl: {
      // setNewTranslation (Editarea.js:240-272)
      draft: (s, p, fx) => {
        const translation = 'Nuova <g id="1">traduzione</g>##$_A0$##ok'
        const {editorState} = DraftMatecatUtils.encodeContent(
          s,
          translation,
          tagSuggestions(fx, s).sourceTagMap,
        )
        return DraftState.moveSelectionToEnd(
          DraftState.push(
            s,
            editorState.getCurrentContent(),
            'insert-fragment',
          ),
        )
      },
      // TargetEditor replaceTranslation (TargetEditor.js:370-376)
      cm: (ed) => ed.setValue('Nuova <g id="1">traduzione</g>##$_A0$##ok'),
    },
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
