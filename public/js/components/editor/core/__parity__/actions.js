// The edit harness shared by the parity tests (editParity.test.js, domParity.test.js): segments with named
// positions, and each edit as the CodeMirror target makes it (its real api, keymap and DOM handlers). What Draft saved
// and drew for the same edits is frozen in core/__golden__.
import {runScopeHandlers} from '@codemirror/view'
import TextUtils from '../../../../utils/textUtils'
import {formatText} from '../../../../utils/segmentText/textUtils'
import checkForMissingTags from '../../../../utils/segmentText/checkForMissingTag'
import {
  sourceStoreData,
  translationUpdate,
} from '../../../../utils/segmentText/segmentStoreData'
import {
  readSegmentClipboard,
  segmentClipboardHtml,
} from '../../../segmentView/segmentModel'
import {SEGMENTS} from '../../e2e/fixtures'
import {searchedTagText} from '../../../segmentView/segmentModel'
import {targetLayers} from '../../matecat/TargetEditor'
import {tooltipContent} from '../../matecat/layerTooltip'
import {insertRaw, toDoc} from '../docModel'
import {layerRangesAt, replaceLayerRange, setLayer} from '../layers'

// ---------- fixtures ----------
// Named positions are written into the segment as ⟨name⟩, always between tags, never inside one.

export const MARK = /⟨(\w+)⟩/g
export const at = (segment, needle, name, {after = false} = {}) => {
  const i = segment.indexOf(needle)
  if (i < 0) throw new Error(`no ${needle} in ${segment}`)
  const o = after ? i + needle.length : i
  return segment.slice(0, o) + `⟨${name}⟩` + segment.slice(o)
}

export const PH_TARGET = [
  ['Ciao', 's', {}],
  ['hai ', 'at', {}],
  [' nuovi', 'm', {}],
].reduce(
  (seg, [needle, name, opts]) => at(seg, needle, name, opts),
  SEGMENTS.ph.target,
)

export const FIXTURES = [
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
    // `b` is in the labels <br/>, <b> and </b> before Bob: hit 1 is in a tag, hit 3 the B of Bob.
    searches: [
      ['a', 3, 'A'],
      ['b', 1, 'X'],
      ['b', 3, 'X'],
    ],
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
export const MARKERS = ['', '', '']
export const clean = (target) => target.replace(MARK, '')
export const markedWith = (target, names) =>
  target.replace(MARK, (_, n) =>
    names.includes(n) ? MARKERS[names.indexOf(n)] : '',
  )
export const markNames = (target) => [...target.matchAll(MARK)].map((m) => m[1])

// Finds the markers in `text`, as offsets in the text without them.
export const findMarkers = (text) => {
  const found = {}
  let removed = 0
  for (let i = 0; i < text.length; i++) {
    const k = MARKERS.indexOf(text[i])
    if (k >= 0) found[k] = i - removed++
  }
  return found
}
export const stripMarkers = (text) => text.replace(/[-]/g, '')

// What the store holds for the tag menu: the source tag map (sourceStoreData's tagRange) and the source tags missing
// from the loaded target (translationUpdate, as TargetEditor updates the store).
export const tagSuggestions = (fixture) => {
  const sourceTagMap = sourceStoreData(fixture.source).tagRange
  const {tagMap} = translationUpdate(clean(fixture.target), sourceTagMap)
  return checkForMissingTags(sourceTagMap, tagMap)
}

export const cmPoints = (markedRaw, cleanRaw) => {
  const {text} = toDoc(markedRaw)
  if (stripMarkers(text) !== toDoc(cleanRaw).text)
    throw new Error(`markers move the text of ${markedRaw}`)
  return findMarkers(text)
}

export const press = (editor, init) =>
  runScopeHandlers(
    editor.view,
    new KeyboardEvent('keydown', {bubbles: true, cancelable: true, ...init}),
    'editor',
  )

// What the DOM observer dispatches for typed text.
export const cmType = (editor, text) => {
  const {view} = editor
  view.dispatch(view.state.replaceSelection(text), {
    userEvent: 'input.type',
    scrollIntoView: true,
  })
}

export const fakeTransfer = (initial = {}) => {
  const data = {...initial}
  const type = (t) => (t === 'Text' ? 'text/plain' : t)
  return {
    data,
    getData: (t) => data[type(t)] ?? '',
    setData: (t, value) => (data[type(t)] = value),
    clearData: () => Object.keys(data).forEach((t) => delete data[t]),
  }
}
export const fire = (target, name, props = {}) => {
  const event = Object.assign(
    new Event(name, {bubbles: true, cancelable: true}),
    props,
  )
  target.dispatchEvent(event)
  return event
}
export const cmDrop = (editor, pos, props) => {
  editor.view.posAtCoords = () => pos
  fire(editor.view.contentDOM, 'drop', props)
}

// ---------- actions ----------
// Each action is `cm(editor, points, fixture)`; a `clip` action returns the clipboard slice instead. The harness has
// already put the selection on points[0]..points[1] (a caret on points[0]).

export const KEYS = {
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

export const type = (text) => ({
  cm: (ed) => cmType(ed, text),
})

export const key = (name) => ({
  cm: (ed) => {
    if (!press(ed, KEYS[name])) throw new Error(`${name} not handled`)
  },
})

// Paste: the paste handler of the CodeMirror target, with a clipboard holding `text` and `html`.
export const paste = ({text = '', html = ''}) => ({
  cm: (ed) =>
    fire(ed.view.contentDOM, 'paste', {
      clipboardData: fakeTransfer({'text/plain': text, 'text/html': html}),
    }),
})

export const slice = (raw, text) => segmentClipboardHtml({raw, text})

// The tag menu: `<` (or Alt+T), then Enter on the entry `pick` (0 = first missing tag). The CodeMirror host
// (TargetEditor acceptMenuTag/onMenuKey) inserts entries[focused].data.encodedText with insertMenuTag.
export const tagMenu = (opener, pick) => ({
  cm: (ed, p, fx) => {
    const {missingTags, sourceTags} = tagSuggestions(fx)
    // With no tag to offer the menu stays shut and the key types as usual (Alt+T types nothing).
    if (!press(ed, KEYS[opener]) && opener === 'lt') cmType(ed, '<')
    const entries = [...missingTags, ...sourceTags]
    if (entries.length) ed.insertMenuTag(entries[pick].data.encodedText)
  },
})

export const format = (name) => ({
  // TargetEditor's formatSelection
  cm: (ed) => ed.transformSelection((text) => formatText(text, name)),
})

export const CARET_ACTIONS = {
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
    // TargetEditor's copyGlossary
    cm: (ed) => ed.insertText('R&D <i>'),
  },
  'drop a slice from another segment': {
    // a slice dragged from another segment
    cm: (ed) =>
      cmDrop(ed, ed.view.state.selection.main.head, {
        dataTransfer: fakeTransfer({
          'text/html': slice('<x id="7"/>Z', 'Z'),
          'text/plain': 'Z',
        }),
      }),
  },
}

export const SELECTION_ACTIONS = {
  'delete selection (backspace)': key('Backspace'),
  'replace selection by typing': type('new'),
  'paste plain text over selection': paste({text: 'pasted'}),
  'paste a slice over selection': paste({
    text: 'X',
    html: slice('<x id="5"/>X', 'X'),
  }),
  cut: {
    cm: (ed) =>
      fire(ed.view.contentDOM, 'cut', {clipboardData: fakeTransfer()}),
  },
  'upper case': format('uppercase'),
  'lower case': format('lowercase'),
  capitalize: format('capitalize'),
}

// The clipboard slice a copy (and a cut) writes: CodeMirror's copy handler.
export const COPY = {
  cm: (ed) => {
    const clipboardData = fakeTransfer()
    fire(ed.view.contentDOM, 'copy', {clipboardData})
    return readSegmentClipboard(clipboardData.getData('text/html'))
  },
}

// Drag the selection points[0]..points[1] and drop it at points[2].
export const dragDrop = (modifiers = {}) => ({
  cm: (ed, p) => {
    const dataTransfer = fakeTransfer()
    fire(ed.view.contentDOM, 'dragstart', {dataTransfer})
    cmDrop(ed, p[2], {dataTransfer, ...modifiers})
  },
})

export const DROP_ACTIONS = {
  'drag and drop (move)': dragDrop(),
  'alt + drag and drop (copy)': dragDrop({altKey: true}),
  'ctrl + drag and drop (copy)': dragDrop({ctrlKey: true}),
}

// A Lexiqa suggestion clicked on a target warning. The store holds the Lexiqa text of the target
// (lxqDecodedTranslation: prepareTextForLexiqa of the saved segment, translationUpdate) and Lexiqa's results on it:
// one spelling warning on occurrence `index` of `word`, offering `suggestion`. The target reads them through its
// lexiqa layer (targetLayers) and the layer tooltip.
export const LEXIQA_SID = '1'
export const LEXIQA_MODULES = [
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
export const lexiqaSegment = (fx, word, index, suggestion) => {
  const lxqDecodedTranslation = translationUpdate(
    fx.clean,
  ).lxqDecodedTranslation
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

export const lexiqaReplace = (fx, word, index, suggestion) => ({
  cm: (ed) => {
    const {view} = ed
    const segment = lexiqaSegment(fx, word, index, suggestion)
    // TargetEditor's targetLayers: the lexiqa layer, set volatile as the target sets it
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
    // The hover tooltip on the mark (layerTooltip.js's tooltipContent, as createLayerTooltip shows it) and its
    // suggestion click
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

// The search replace of occurrence `index` of `term` by `text`.
export const searchReplace = (term, index, text) => ({
  // TargetEditor replaceSearchResult, which searches each tag as its text in the cattool's search text
  cm: (ed) =>
    ed.replaceOccurrence(
      new RegExp(TextUtils.escapeRegExp(term), 'gi'),
      index,
      text,
      searchedTagText,
    ),
})

// Add all the source tags missing from the target.
export const ADD_MISSING_TAGS = {
  // TargetEditor's addMissingSourceTagsToTarget
  cm: (ed, p, fx) => {
    const {missingTags} = tagSuggestions(fx)
    const raw = missingTags.map((tag) => tag.data.encodedText).join('')
    const end = ed.view.state.doc.length
    ed.view.dispatch(insertRaw(ed.view.state, end, end, raw), {
      userEvent: 'input',
    })
  },
}

// The store's REPLACE_TRANSLATION (a TM match or a copy of the source taking the whole target).
export const REPLACE_TRANSLATION = {
  // TargetEditor's replaceTranslation
  cm: (ed) => ed.setValue('Nuova <g id="1">traduzione</g>##$_A0$##ok'),
}
