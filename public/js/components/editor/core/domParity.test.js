/* eslint-disable testing-library/no-node-access, testing-library/no-container --
   Comparing what two editors draw means walking their DOM: neither editor's content has roles or labels to query. */
// DOM parity: the same segment, drawn by the Draft editors (Editarea for the target, SegmentSource for the source)
// and by the CodeMirror editors (TargetEditor, SourceEditor), must show the user the same thing: the same text, the
// same tag chips (label, classes, counter, label inside the element Tag.scss trims with an ellipsis), the same
// special-character marks, the same highlighted ranges with the same tooltips; on load, with the layer data of the
// store, and after an edit and its undo (driven by the harness of editParity.test.js, __parity__/actions.js).
//
// jsdom has no layout, so this compares structure, classes and text, never pixels. drawnCm() reduces the CodeMirror
// DOM to a list of tokens, the spec of "what the user sees", CSS rules that draw something (Tag.scss,
// SpecialCharMarks.scss) included. What Draft drew, reduced the same way, is frozen in __golden__/domParity.json: the
// expected tokens of each case. Where the two differ on purpose, the difference is listed in INTENTIONAL (or
// EDIT_INTENTIONAL) with the reason. An INTENTIONAL rule rewrites the CodeMirror tokens here; where it corrected the
// Draft side, the correction is in the golden already (each golden drawing lists the rules it needed), so the golden
// never holds what Draft got wrong. For an EDIT_INTENTIONAL phase the golden holds what CodeMirror draws, checked to
// differ from Draft when it was frozen.
import React from 'react'
import {act, fireEvent, render} from '@testing-library/react'
import TargetEditor from '../matecat/TargetEditor'
import SourceEditor from '../matecat/SourceEditor'
import {SegmentContext} from '../../segments/SegmentContext'
import {
  sourceStoreData,
  translationUpdate,
} from '../../../utils/segmentText/segmentStoreData'
import {initTagSignature} from '../../../utils/segmentText/tagModel'
import {SPECIAL_CHARS, TAB} from '../../../utils/segmentText/specialChars'
import CORPUS from './__fixtures__/roundTripCorpus.json'
import GOLDEN from './__golden__/domParity.json'
import {EditorView} from '@codemirror/view'
import {undo} from '@codemirror/commands'
import {insertMenuTag} from './tagMenu'
import {
  ADD_MISSING_TAGS,
  CARET_ACTIONS,
  DROP_ACTIONS,
  FIXTURES,
  REPLACE_TRANSLATION,
  clean,
  cmPoints,
  lexiqaReplace,
  tagSuggestions,
  markNames,
  markedWith,
} from './__parity__/actions'
import SearchUtils from '../../header/cattol/search/searchUtils'
import SegmentStore from '../../../stores/SegmentStore'
import SegmentConstants from '../../../constants/SegmentConstants'

// The user's compressed tags option (CatToolStore.isTagsCompressed).
const mockTagsCompressed = {on: false}
jest.mock('../../../stores/SegmentStore', () => {
  const listeners = {}
  return {
    __esModule: true,
    default: {
      listeners,
      addListener: (name, fn) =>
        (listeners[name] = listeners[name] || []).push(fn),
      removeListener: (name, fn) =>
        (listeners[name] = (listeners[name] || []).filter((f) => f !== fn)),
      emit: (name, ...args) =>
        (listeners[name] || []).forEach((fn) => fn(...args)),
      getSegmentByIdToJS: () => undefined,
    },
  }
})
// Actions do nothing, except the ones whose store event an editor draws from: focusTags reaches the chips as
// FOCUS_TAGS (SegmentStore), as the dispatcher sends it.
jest.mock('../../../actions/SegmentActions', () => {
  const {FOCUS_TAGS} = jest.requireActual(
    '../../../constants/SegmentConstants',
  ).default
  const store = jest.requireMock('../../../stores/SegmentStore').default
  const actions = {
    focusTags: (tagsSelected) =>
      store.emit(FOCUS_TAGS, {actionType: FOCUS_TAGS, tagsSelected}),
  }
  return {
    __esModule: true,
    default: new Proxy(actions, {
      get: (all, name) => (all[name] ??= jest.fn()),
    }),
  }
})
jest.mock('../../../stores/CatToolStore', () => ({
  __esModule: true,
  default: {
    getCurrentProjectTemplate: () => ({characterCounterCountTags: false}),
    getHaveKeysGlossary: () => false,
    isTagsCompressed: () => mockTagsCompressed.on,
    addListener: () => {},
    removeListener: () => {},
    getJobFilesInfo: () => ({}),
    getJobMetadata: () => ({}),
    getJobTmKeys: () => [],
    getProgress: () => ({}),
  },
}))
jest.mock('../../../utils/contextPreviewChannel', () => ({
  __esModule: true,
  default: {sendMessage: () => {}},
}))

// ---------- what the user sees ----------

const ZWSP = /\u200b/g
// The special characters the editor shows as a mark, by its class: Draft drew a tag chip, CodeMirror a display-only
// mark (user decision: nbsp, tab and word joiner are characters with a mark, never tags). Same meaning, same symbol;
// the classes are pinned here: a mark missing its class is not recognised as the character and the comparison fails.
const CHAR_MARKS = [
  {meaning: 'nbsp', cm: 'special-char-nbsp'},
  {meaning: 'tab', cm: 'cm-tab'},
  {meaning: 'wordJoiner', cm: 'special-char-word-joiner'},
  {meaning: 'lineBreak', cm: 'special-char-mark-line-break'},
]

// The highlight of a layer, by the element the editor wraps the text in.
const HIGHLIGHTS = [
  {kind: 'lexiqa', cm: '.lexiqahighlight'},
  {kind: 'glossary', cm: '.glossaryItem'},
  {kind: 'missingTerm', cm: '.qaCheckGlossaryItem'},
  {kind: 'blacklist', cm: '.blacklistItem'},
  {kind: 'icu', cm: '.icuItem'},
  {kind: 'icuError', cm: '.icuItem-error'},
  {kind: 'search', cm: 'mark.searchMarker'},
  {kind: 'currentSearch', cm: 'mark.currSearchItem'},
]

const highlightsOf = (node, stop) => {
  const kinds = []
  for (let el = node.parentElement; el && el !== stop; el = el.parentElement)
    HIGHLIGHTS.forEach((h) => el.matches(h.cm) && kinds.push(h.kind))
  return [...new Set(kinds)].sort()
}

// A chip: its label as shown, its classes (what Tag.scss styles it by), its counter, and whether the label sits in the
// element Tag.scss trims with an ellipsis (`.tag-text`).
const chipToken = (el, hl) => {
  const counter = el.querySelector('.index-counter')
  // Tag.scss hides the label of a compressed chip: one with `tag-compressed`, or any numbered chip while the body has
  // `tags-compressed` (CatToolStore sets it with the user's option). Only the number shows, but for a chip that holds a
  // search hit (`tag-search-match`), whose label shows again.
  const hidden =
    el.classList.contains('tag-compressed') ||
    (document.body.classList.contains('tags-compressed') &&
      el.classList.contains('tag-numbered') &&
      !el.classList.contains('tag-search-match'))
  const labelEl = hidden ? null : el.querySelector('.tag-text')
  const label = hidden
    ? counter.textContent
    : (labelEl ?? el).textContent.replace(ZWSP, '')
  return {
    t: 'chip',
    label,
    counter: counter ? counter.textContent : null,
    classes: [...el.classList].filter((c) => c !== 'tag').sort(),
    ellipsis:
      Boolean(labelEl) && labelEl.textContent.replace(ZWSP, '') === label,
    hl,
  }
}

// What each mark stands for, to read a mark as its character where an INTENTIONAL entry needs it.
const CHAR_OF = {
  nbsp: '\u00a0',
  tab: '\t',
  wordJoiner: '\u2060',
  lineBreak: '\n',
}

const markOf = (el) => CHAR_MARKS.find((m) => el.classList.contains(m.cm))

const pushText = (tokens, text, hl) => {
  if (!text) return
  const last = tokens[tokens.length - 1]
  if (last?.t === 'text' && last.hl.join() === hl.join()) last.text += text
  else tokens.push({t: 'text', text, hl})
}

const walk = (node, stop, tokens) => {
  if (node.nodeType === Node.TEXT_NODE) {
    pushText(
      tokens,
      node.textContent.replace(ZWSP, ''),
      highlightsOf(node, stop),
    )
    return
  }
  if (node.nodeType !== Node.ELEMENT_NODE) return
  const el = node
  if (el.matches('img.cm-widgetBuffer, br')) return
  const mark = markOf(el)
  if (mark) {
    // CodeMirror draws the label of a mark with `::before` from `data-label`, and the tab's arrow from the editor
    // theme (display.js), which is TAB.label.
    const symbol =
      mark.meaning === 'tab'
        ? TAB.label
        : (el.querySelector('[data-label]')?.dataset.label ?? '')
    tokens.push({
      t: 'mark',
      meaning: mark.meaning,
      symbol,
      hl: highlightsOf(el, stop),
    })
    return
  }
  if (
    el.classList.contains('cm-specialChar') ||
    el.classList.contains('special-char-mark')
  ) {
    const code = el.getAttribute('aria-label')?.match(/U\+([0-9A-F]{4})/)?.[1]
    tokens.push({
      t: 'mark',
      meaning: el.className,
      symbol: el.querySelector('[data-label]')?.dataset.label ?? '',
      char: code ? String.fromCharCode(parseInt(code, 16)) : el.textContent,
      hl: highlightsOf(el, stop),
    })
    return
  }
  if (el.classList.contains('tag')) {
    tokens.push(chipToken(el, highlightsOf(el, stop)))
    return
  }
  el.childNodes.forEach((child) => walk(child, stop, tokens))
}

/** The tokens the user sees in a CodeMirror editor rendered in `container`, in document order. */
const drawnCm = (container) => {
  const tokens = []
  container.querySelectorAll('.cm-content > .cm-line').forEach((line, i) => {
    if (i > 0) tokens.push({t: 'break'})
    walk(line, line, tokens)
  })
  return tokens
}

// One line per token, so a failing comparison reads as a diff of what is drawn.
const show = (token) => {
  const hl = token.hl?.length ? ` @${token.hl.join('+')}` : ''
  switch (token.t) {
    case 'text':
      return `text ${JSON.stringify(token.text)}${hl}`
    case 'chip':
      return `chip ${JSON.stringify(token.label)} ${token.classes.join('.')}${token.counter === null ? '' : ` #${token.counter}`}${token.ellipsis ? '' : ' NO-ELLIPSIS'}${hl}`
    case 'mark':
      return `mark ${token.meaning} ${JSON.stringify(token.symbol)}${hl}`
    default:
      return token.t
  }
}

// ---------- mounting ----------

beforeAll(() => {
  const rect = {left: 0, right: 0, top: 0, bottom: 0, width: 0, height: 0}
  Range.prototype.getClientRects = () => []
  Range.prototype.getBoundingClientRect = () => rect
  // Visible spaces off: the default user settings, as the app sets them at load.
  initTagSignature({show_whitespace: 0})
})

beforeEach(() => {
  jest.useFakeTimers()
  Object.assign(global.config, {
    source_code: 'en-US',
    target_code: 'it-IT',
    tag_projection_languages: {},
    isTargetRTL: false,
    isSourceRTL: false,
    lfPlaceholder: '##$_0A$##',
  })
})
afterEach(() => {
  jest.useRealTimers()
})

const makeSegment = (raw, extra = {}) => ({
  sid: '1',
  segment: raw,
  original: raw,
  translation: raw,
  decodedTranslation: raw,
  opened: true,
  muted: false,
  openSplit: false,
  sourceTagMap: translationUpdate(raw).tagMap,
  targetTagMap: [],
  missingTagsInTarget: [],
  warnings: {},
  tagMismatch: {},
  inSearch: false,
  searchParams: {},
  occurrencesInSearch: {occurrences: []},
  currentInSearch: false,
  currentInSearchIndex: 0,
  lxqDecodedTranslation: '',
  lxqDecodedSource: '',
  ...extra,
})

const settle = () => act(() => jest.advanceTimersByTime(2000))

// Mounts the editor for `segment`, then, when `update` is given, re-renders it with that segment: layer data (Lexiqa,
// QA checks, search) reaches an open segment through the store after it opened, and both editors take it as an update.
const mount = (element, read, segment, update, events = [], hover) => {
  const tree = (seg) => (
    <SegmentContext.Provider value={{segment: seg}}>
      {element(seg)}
    </SegmentContext.Provider>
  )
  const {container, unmount, rerender} = render(tree(segment))
  settle()
  if (update) {
    rerender(tree(update))
    // what the store emits with the update, which the editors listen to
    act(() =>
      events.forEach(([name, ...args]) => SegmentStore.emit(name, ...args)),
    )
    settle()
  }
  const tokens = read(container)
  const tooltips = hover ? hover(container) : undefined
  unmount()
  return hover ? {tokens, tooltips} : tokens
}

const ELEMENTS = {
  target: {
    cm: (seg) => (
      <TargetEditor
        segment={seg}
        value={seg.translation}
        translation={seg.translation}
        updateCounter={() => {}}
        toggleFormatMenu={() => {}}
      />
    ),
  },
  source: {
    cm: (seg) => <SourceEditor segment={seg} value={seg.segment} />,
  },
}

/** The tokens the CodeMirror editor of `side` draws for `segment` (then `update`). */
const draw = (side, segment, update, events, hover) =>
  mount(ELEMENTS[side].cm, drawnCm, segment, update, events, hover)

// ---------- tooltips ----------
// What hovering the first highlight of each kind shows. Draft showed it with Matecat's common/Tooltip wrapping the
// highlight; the new editor shows it through layerHover, which renders common/Tooltip into its own element
// (layerTooltip.js) — never CodeMirror's `.cm-tooltip`.

const TOOLTIP_KINDS = [
  'lexiqa',
  'blacklist',
  'icuError',
  'glossary',
  'missingTerm',
]
const textOf = (el) => (el?.textContent ?? '').replace(ZWSP, '').trim()

const hoverCm = (container) =>
  Object.fromEntries(
    TOOLTIP_KINDS.flatMap((kind) => {
      const el = container.querySelector(
        HIGHLIGHTS.find((h) => h.kind === kind).cm,
      )
      if (!el) return []
      fireEvent.mouseOver(el)
      settle()
      const shown = document.body.querySelector('.cm-layer-tooltip')
      // a CodeMirror tooltip, which Draft never shows, would differ from Draft's text
      const text = document.body.querySelector('.cm-tooltip')
        ? '.cm-tooltip'
        : textOf(shown)
      fireEvent.mouseOut(el, {relatedTarget: document.body})
      fireEvent.mouseLeave(el, {relatedTarget: document.body})
      settle()
      return [[kind, text]]
    }),
  )

// The tooltip of the first numbered opening chip, which both editors show while the tags are compressed (the chip
// then shows only its number): its text and the classes of the chip copy it holds. Draft: TagEntity's common/Tooltip;
// the new editor: segmentView/tagTooltip.js, common/Tooltip in `.tag-full-text-tooltip`.
const chipCopy = (root) => {
  const chip = root?.querySelector('.tag')
  return chip ? {text: textOf(chip), classes: [...chip.classList].sort()} : null
}
const hoverChipCm = (container) => {
  const chip = container.querySelector('.cm-content .tag.tag-paired-open')
  fireEvent.mouseEnter(chip)
  settle()
  const shown = document.body.querySelector('.cm-tooltip')
    ? '.cm-tooltip'
    : chipCopy(document.body.querySelector('.tag-full-text-tooltip'))
  fireEvent.mouseLeave(chip)
  settle()
  return shown
}

// ---------- intentional differences ----------

// Each entry is one known difference. It rewrote both token lists so that the difference no longer showed, and the
// rewritten Draft list is the golden: an entry that only corrected Draft (no `cm`) lives on in the golden alone. The
// `cm` rewrite still runs here. Each golden drawing lists the entries it needed when it was frozen (without them the
// two differed); a case checks that each `cm` rewrite it lists is still needed, and the last test that every entry is
// needed by some case.

const ENTITY = /&(?!(?:amp|lt|gt);)#?[a-z0-9]+;/gi
const decodeEntities = (text) =>
  text.replace(ENTITY, (entity) => {
    const el = document.createElement('textarea')
    el.innerHTML = entity
    return el.value
  })

const mergeTexts = (tokens) =>
  tokens.reduce((out, token) => {
    if (token.t === 'text') pushText(out, token.text, token.hl)
    else out.push(token)
    return out
  }, [])

const asCharacter = (token) =>
  token.t === 'mark'
    ? {
        t: 'text',
        text: (token.char ?? CHAR_OF[token.meaning]).replace(ZWSP, ''),
        hl: token.hl,
      }
    : token

// A word joiner in the text is a Draft tag already (a mark on both sides): not one of these.
const SPECIAL_CHAR = new RegExp(
  `[\t\r${Object.keys(SPECIAL_CHARS).join('').replace('\u2060', '')}]`,
)
const PLACEHOLDER = /##\$_[0-9A-F]{2}\$##/g

const INTENTIONAL = [
  {
    name: 'entities',
    // Draft wrong. Draft unescapes only &amp; &lt; &gt;, so &apos; &quot; &nbsp; and numeric references show as
    // written; the closed segment shows the character, and so does the new editor (docModel.test.js).
    reason: 'Draft shows XML entities other than &amp; &lt; &gt; as written',
    applies: (raw) => new RegExp(ENTITY.source, 'i').test(raw),
  },
  {
    name: 'carriage return',
    // Draft wrong. Draft turns a carriage return into a line break, and saves it as one (docModel.test.js); a CR
    // followed by a line feed disappears. The new editor keeps it a tag: a chip Tag.scss draws as `\21B5`.
    reason: 'Draft turns a carriage return into a line break',
    cm: (tokens) =>
      tokens.flatMap((t, i) => {
        if (t.t !== 'chip' || !t.classes.includes('tag-cr')) return [t]
        const next = tokens[i + 1]
        return next?.t === 'mark' && next.meaning === 'lineBreak'
          ? []
          : [
              {t: 'mark', meaning: 'lineBreak', symbol: '\u21b5', hl: []},
              {t: 'break'},
            ]
      }),
  },
  {
    name: 'special characters in the text',
    // A decision (display-only marks). The new editor marks every character of the shared table
    // (utils/segmentText/specialChars.js: typographic spaces, zero-width characters, separators) and every tab or
    // nbsp of the text, as the closed segment does; Draft shows only its own tags as marks, the rest as raw
    // characters, and deletes zero-width spaces. Both sides are read as characters, only for segments with one.
    reason:
      'the new editor marks the special characters of the text, Draft only its tags',
    applies: (raw) =>
      SPECIAL_CHAR.test(decodeEntities(raw.replace(PLACEHOLDER, ''))),
    cm: (tokens) => mergeTexts(tokens.map(asCharacter)),
  },
  {
    name: 'unknown markup',
    // By design of the segment model. XLIFF markup it does not know as a tag (a ph with no equiv-text) stays one
    // protected chip in the new editor, labelled with the markup (tags.js `tag-unknown`); Draft leaves it as text the
    // user can edit apart.
    reason: 'the new editor keeps unknown XLIFF markup as one chip',
    cm: (tokens) =>
      mergeTexts(
        tokens.map((t) =>
          t.t === 'chip' && t.classes.includes('tag-unknown')
            ? {t: 'text', text: t.label, hl: t.hl}
            : t,
        ),
      ),
  },
  {
    name: 'compressed chips',
    // A difference of mechanism, not of drawing: Draft marks each compressed chip `tag-compressed` and leaves its label
    // out; the new editor keeps the label and Tag.scss hides it under `body.tags-compressed`. Both show the number.
    reason:
      'Draft marks compressed chips with a class, the new editor relies on body.tags-compressed',
  },
  {
    name: 'chips of a searched side with the tags compressed',
    id: /\| compressed search \|/,
    // Two ways to show a hit the compaction would hide. Draft, while a side is searched, draws every chip of it as its
    // tag text, with no number (TagEntity markSearch; the chip's own content goes into a `display: none` span), so no
    // chip of that side is compressed any more. The new editor keeps its chips and shows again the label of a chip that
    // holds a hit (Tag.scss, `tag-search-match`), number included. Compared: which chips hold a hit, the current one,
    // and the label shown for them.
    reason:
      'while searching, Draft shows every chip as its text with no number; the new editor un-compresses a chip with a hit',
    cm: (tokens) => tokens.map(searchedChip),
  },
  {
    name: 'focused chip after an undo or a drop',
    id: /\| (backspace @ at|delete @ bt|drag and drop \(move\) @ .*)$/,
    // Not judged here: which chip shows as focused (`tag-focused`) follows the selection, and this test compares
    // content, not selections. After undoing a deleted tag Draft focuses the tag beside the caret, the new editor only
    // a selected one; after a drop the new editor keeps the dropped text selected, focusing a chip in it.
    reason:
      'the focused chip follows the selection each editor leaves after an undo or a drop',
    cm: (tokens) => tokens.map(unfocus),
  },
]

const searchedChip = (t) =>
  t.t !== 'chip'
    ? t
    : {
        ...t,
        label: t.classes.includes('tag-search-match') ? t.label : '',
        counter: null,
        ellipsis: true,
      }

const unfocus = (t) =>
  t.classes ? {...t, classes: t.classes.filter((k) => k !== 'tag-focused')} : t

// The INTENTIONAL entries that apply to the case `id` of the segment `raw`.
const applying = (id, raw) =>
  INTENTIONAL.filter(
    (rule) =>
      (!rule.applies || rule.applies(raw)) && (!rule.id || rule.id.test(id)),
  )

const rewrite = (rules, cm) =>
  rules.reduce((c, rule) => (rule.cm ? rule.cm(c) : c), cm)

const lines = (tokens) => tokens.map(show)
const neededRules = new Set()

/**
 * Draws what Draft drew (`golden`) once the intentional differences are rewritten. A `cm` rewrite that changes what is
 * drawn must be one the golden needed when it was frozen; an entry the golden needed counts as still needed when it
 * only corrected Draft, or when its `cm` rewrite still changes something.
 */
const expectSameDrawing = (id, raw, cm, golden) => {
  const rules = applying(id, raw)
  const drawnLines = lines(rewrite(rules, cm))
  expect(drawnLines).toEqual(golden.lines)
  const drawn = drawnLines.join('\n')
  rules.forEach((rule) => {
    const without = lines(
      rewrite(
        rules.filter((r) => r !== rule),
        cm,
      ),
    ).join('\n')
    const changes = Boolean(rule.cm) && without !== drawn
    if (changes)
      expect({
        rule: rule.name,
        needed: golden.rules.includes(rule.name),
      }).toEqual({rule: rule.name, needed: true})
    if (golden.rules.includes(rule.name) && (!rule.cm || changes))
      neededRules.add(rule.name)
  })
}

// ---------- the cases ----------

const SEGMENTS = [
  ...CORPUS.map(({segment}) => segment),
  ...FIXTURES.map((f) => clean(f.target)),
]
const SOURCES = [
  ...CORPUS.map(({segment}) => segment),
  ...FIXTURES.map((f) => f.source),
]

describe.each([
  ['target', SEGMENTS],
  ['source', SOURCES],
])('the %s draws what Draft draws', (side, segments) => {
  // eslint-disable-next-line jest/expect-expect -- expectSameDrawing asserts
  test.each([...new Set(segments)].map((s) => [s]))('%s', (raw) => {
    const cm = draw(side, makeSegment(raw))
    const id = `${side} | ${raw}`
    expectSameDrawing(id, raw, cm, GOLDEN.draws[id])
  })
})

// ---------- highlights ----------
// The same layer data in the store, given to both editors: each highlights the same text with the same kind. `open`
// is part of the segment from the start (ICU is a property of the segment); the rest reaches it as a store update.

const fixture = (name) => FIXTURES.find((f) => f.name === name)
const lexiqaWarning = (text, word, index, side) => {
  let start = -1
  for (let i = 0; i <= index; i++) start = text.indexOf(word, start + 1)
  if (start < 0) throw new Error(`no ${word} #${index} in ${text}`)
  return {
    start,
    end: start + word.length,
    module: 'spelling',
    errorid: `lxq_1_${index}_${side}`,
    color: '#563d7c',
    suggestions: ['x'],
  }
}
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
// Lexiqa's results on the text the store keeps for Lexiqa (prepareTextForLexiqa of the saved segment).
const lexiqa = (raw, words, side) => {
  const text = sourceStoreData(raw).lxqText
  const results = {
    ...Object.fromEntries(LEXIQA_MODULES.map((m) => [m, []])),
    spelling: words.map(([word, index]) =>
      lexiqaWarning(text, word, index, side[0]),
    ),
  }
  return side === 'target'
    ? {lxqDecodedTranslation: text, lexiqa: {target: results}}
    : {lxqDecodedSource: text, lexiqa: {source: results}}
}
const entry = (id, words, extra = {}) => ({
  term_id: id,
  matching_words: words,
  source: {term: words[0]},
  target: {term: words[0]},
  ...extra,
})
// A search for `term` in this segment (sid 1) with `count` hits, the featured one being hit `current`: the segment
// fields Draft reads and the SearchUtils state the new editor reads, as SearchUtils.createSearchObject builds them
// (one occurrencesList entry per hit; `ingnoreCase` is really "match case"). `positions`, where given, are the hits'
// `matchPosition`s, by which Draft's markSearch finds the current hit inside a tag label.
const search = (side, term, count, current, positions = []) => {
  const occurrences = Array.from({length: count}, (_, i) => ({
    searchProgressiveIndex: i,
    ...(positions[i] === undefined ? {} : {matchPosition: positions[i]}),
  }))
  return {
    segment: {
      inSearch: true,
      searchParams: {[side]: term, ingnoreCase: false, exactMatch: undefined},
      occurrencesInSearch: {occurrences},
      currentInSearch: true,
      currentInSearchIndex: current,
    },
    searchUtils: {
      searchMode: 'normal',
      searchParams: {[side]: term},
      occurrencesList: occurrences.map(() => '1'),
      featuredSearchResult: current,
      searchResultsDictionary: {1: {id: '1', occurrences}},
    },
  }
}

const lastWord = (raw) =>
  raw
    .replace(/<[^>]*>/g, ' ')
    .match(/\p{L}+/gu)
    .at(-1)

const LAYER_CASES = [
  ...[
    'plain',
    'g',
    'nbsp tab line feed',
    'cjk emoji',
    'rtl',
    'multi-line g',
    'bx ex',
  ].flatMap((name) => {
    const f = fixture(name)
    const raw = clean(f.target)
    return (f.lexiqa ?? []).length
      ? [
          {
            name: `lexiqa ${name}`,
            side: 'target',
            raw,
            rtl: f.rtl,
            segment: () => lexiqa(raw, f.lexiqa, 'target'),
          },
          {
            name: `lexiqa ${name}`,
            side: 'source',
            raw: f.source,
            segment: () =>
              lexiqa(f.source, [[lastWord(f.source), 0]], 'source'),
          },
        ]
      : []
  }),
  {
    name: 'blacklist',
    side: 'target',
    raw: clean(fixture('g').target),
    segment: {qaBlacklistGlossary: [entry(4, ['now'])]},
  },
  {
    name: 'blacklist after a line break',
    side: 'target',
    raw: clean(fixture('multi-line g').target),
    segment: {qaBlacklistGlossary: [entry(4, ['end'])]},
  },
  {
    name: 'glossary and missing term',
    side: 'source',
    raw: fixture('g').source,
    segment: {
      glossary: [entry(1, ['now']), entry(2, ['Click'], {missingTerm: true})],
    },
  },
  {
    name: 'glossary after an emoji',
    side: 'source',
    raw: fixture('cjk emoji').source,
    segment: {glossary: [entry(1, ['ok'])]},
  },
  {
    name: 'tag mismatch error',
    side: 'target',
    raw: 'a <g id="1">b</g> <x id="2"/>',
    segment: {tagMismatch: {target: ['<x id="2"/>']}},
    events: [[SegmentConstants.SET_SEGMENT_WARNINGS, '1']],
  },
  {
    name: 'tag order warning',
    side: 'target',
    raw: 'a <g id="1">b</g> <x id="2"/>',
    segment: {tagMismatch: {order: ['<x id="2"/>']}},
    events: [[SegmentConstants.SET_SEGMENT_WARNINGS, '1']],
  },
  ...['target', 'source'].flatMap((side) => [
    {
      name: 'search',
      side,
      raw: 'One <g id="1">two</g> one, ONE',
      search: search(side, 'one', 3, 1),
    },
    {
      name: 'search after a line break',
      side,
      raw: clean(fixture('multi-line g').target),
      search: search(side, 'line', 2, 0),
    },
    {
      name: 'search in a tag label',
      side,
      raw: 'a <ph id="mtc_1" equiv-text="base64:' + btoa('%name%') + '"/> name',
      search: search(side, 'name', 2, 1),
    },
    {
      name: 'current search hit in a tag label',
      side,
      raw: 'a <ph id="mtc_1" equiv-text="base64:' + btoa('%name%') + '"/> name',
      // Draft's block text is `a %name% name`: the first hit is at 3, inside the chip.
      search: search(side, 'name', 2, 0, [3, 9]),
    },
    {
      name: 'icu',
      side,
      raw: '{count, plural, one {# file} other {# files}}',
      open: {icu: true},
    },
    {
      name: 'icu error',
      side,
      raw: '{count, plural, one {# file} other {# files}',
      open: {icu: true},
    },
  ]),
]

describe('highlights', () => {
  afterEach(() => {
    Object.assign(SearchUtils, {
      occurrencesList: [],
      searchResultsDictionary: {},
    })
  })
  test.each(LAYER_CASES.map((c) => [`${c.side} | ${c.name}`, c]))(
    '%s',
    (id, c) => {
      global.config.isTargetRTL = Boolean(c.rtl)
      if (c.search) Object.assign(SearchUtils, c.search.searchUtils)
      // built here: the Lexiqa text depends on the tag signatures set up in beforeAll
      const layerData =
        typeof c.segment === 'function' ? c.segment() : c.segment
      const cm = draw(
        c.side,
        makeSegment(c.raw, c.open),
        makeSegment(c.raw, {...c.open, ...layerData, ...c.search?.segment}),
        c.events ??
          (c.search ? [[SegmentConstants.ADD_SEARCH_RESULTS, '1']] : []),
        hoverCm,
      )
      const golden = GOLDEN.highlights[id]
      expectSameDrawing(id, c.raw, cm.tokens, golden)
      expect(cm.tooltips).toEqual(golden.tooltips)
      // the case highlights something, or it compares nothing
      expect(
        cm.tokens.some(
          (t) =>
            t.hl?.length ||
            t.classes?.some((k) => k.startsWith('tag-mismatch')),
        ),
      ).toBe(true)
    },
  )
})

describe('compressed tags', () => {
  beforeEach(() => {
    mockTagsCompressed.on = true
    document.body.classList.add('tags-compressed')
  })
  afterEach(() => {
    mockTagsCompressed.on = false
    document.body.classList.remove('tags-compressed')
  })
  const f = fixture('pc, dataRef and compressed g (e2e ph)')
  test.each([
    ['target', clean(f.target)],
    ['source', f.source],
  ])(
    '%s: the numbered chips show their number, the hovered one its text',
    (side, raw) => {
      const cm = draw(side, makeSegment(raw), null, [], hoverChipCm)
      const id = `${side} | compressed | ${raw}`
      const golden = GOLDEN.compressed[id]
      expectSameDrawing(id, raw, cm.tokens, golden)
      // some chip shows only its number
      expect(
        cm.tokens.some(
          (t) => t.t === 'chip' && t.counter !== null && t.label === t.counter,
        ),
      ).toBe(true)
      expect(cm.tooltips).toEqual(golden.tooltips)
      expect(cm.tooltips).not.toBeNull()
    },
  )

  // A search hit in the label of a numbered chip: the label is hidden by compaction, so the hit must show some other
  // way. The cattool counts it (its search text decodes the ph equiv-text); here it is the current hit.
  const pc = (id, ctype, label) =>
    `<ph id="${id}" ctype="${ctype}" equiv-text="base64:${btoa(label)}"/>`
  const searchedRaw = `a ${pc('mtc_1', 'x-original_pc_open', '<b class="name">')}x${pc('mtc_2', 'x-original_pc_close', '</b>')} name`
  test.each(['target', 'source'])(
    '%s: a current hit in a compressed label is drawn on its chip',
    (side) => {
      // Draft's block text is `a ⁠<b class="name">…`, the chip from 3: markSearch finds the current hit in it there.
      const s = search(side, 'name', 2, 0, [3, 40])
      Object.assign(SearchUtils, s.searchUtils)
      const cm = draw(
        side,
        makeSegment(searchedRaw),
        makeSegment(searchedRaw, s.segment),
        [[SegmentConstants.ADD_SEARCH_RESULTS, '1']],
      )
      const id = `${side} | compressed search | ${searchedRaw}`
      expectSameDrawing(id, searchedRaw, cm, GOLDEN.compressed[id])
      // It shows the label of the chip holding the hit, and colours the hit as the current one.
      const hit = cm.find((t) => t.classes?.includes('tag-search-current'))
      expect(hit.label).toBe('<b class="name">')
      Object.assign(SearchUtils, {
        occurrencesList: [],
        searchResultsDictionary: {},
      })
    },
  )
})

// ---------- after an edit ----------
// The edit, in CodeMirror mounted as above: through TargetEditor's view, keymap, DOM handlers and store events. What
// it draws is compared after the edit and again after one undo with what Draft drew for the same edit (made through
// Editarea's own onChange with the pure calls Editarea makes).

const editsOf = (f) => {
  const names = markNames(f.target)
  const caret = (action, at) =>
    names.includes(at) ? [{action, at: [at], impl: CARET_ACTIONS[action]}] : []
  return [
    ...caret('type a character', 'bt'),
    ...caret('type a character', 'at'),
    ...caret('backspace', 'at'),
    ...caret('delete', 'bt'),
    ...caret('paste a segment slice with tags', 'bt'),
    ...caret('paste a segment slice with tags', 'm'),
    ...caret('tag menu: < then Enter (first entry)', 'm'),
    ...caret('tag menu: < then Enter (first entry)', 'at'),
    ...(f.drops ?? []).map((at) => ({
      action: 'drag and drop (move)',
      at,
      impl: DROP_ACTIONS['drag and drop (move)'],
    })),
    ...(tagSuggestions(f).missingTags.length
      ? [
          {
            action: 'add all missing source tags',
            at: [],
            impl: ADD_MISSING_TAGS,
          },
        ]
      : []),
    {action: 'REPLACE_TRANSLATION', at: [], impl: REPLACE_TRANSLATION},
    ...(f.lexiqa ?? []).flatMap(([word, index, suggestion]) => [
      {
        action: `lexiqa replace occurrence ${index} of ${JSON.stringify(word)}`,
        at: [],
        lexiqa: true,
        impl: lexiqaReplace(
          {...f, clean: clean(f.target)},
          word,
          index,
          suggestion,
        ),
      },
    ]),
    ...(f.lexiqa?.length && names.includes('s')
      ? [
          {
            action: 'type before the Lexiqa warnings',
            at: ['s'],
            lexiqa: true,
            impl: CARET_ACTIONS['type a character'],
          },
        ]
      : []),
  ]
}

const EDIT_CASES = FIXTURES.flatMap((f) =>
  editsOf(f).map((e) => [
    `${f.name} | ${e.action}${e.at.length ? ` @ ${e.at.join('..')}` : ''}`,
    {...e, f},
  ]),
)

// The segment of a fixture: its target, and the source the tag menu and the missing tags come from.
const editSegment = (f, extra) =>
  makeSegment(clean(f.target), {
    segment: f.source,
    sourceTagMap: translationUpdate(f.source).tagMap,
    ...extra,
  })

const editLexiqa = (f) => lexiqa(clean(f.target), f.lexiqa, 'target')

// Mounts `element`, applies `edit(container)` and `undo(container)`, and returns what is drawn after each.
const drawEdit = (element, read, segment, update, edit, undo) => {
  const tree = (seg) => (
    <SegmentContext.Provider value={{segment: seg}}>
      {element(seg)}
    </SegmentContext.Provider>
  )
  const {container, unmount, rerender} = render(tree(segment))
  settle()
  if (update) {
    rerender(tree(update))
    settle()
  }
  const loaded = read(container)
  act(() => edit(container))
  settle()
  const edited = read(container)
  act(() => undo(container))
  settle()
  const undone = read(container)
  unmount()
  return {loaded, edited, undone}
}

const cmEdit = (c) => {
  const f = c.f
  const lexiqaData = c.lexiqa ? editLexiqa(f) : undefined
  const fx = {...f, clean: clean(f.target)}
  const viewOf = (container) =>
    EditorView.findFromDOM(container.querySelector('.cm-editor'))
  // The handle the harness drives, on TargetEditor's own view: what the editor handle would call.
  const handle = (view) => ({
    view,
    insertMenuTag: (raw) => insertMenuTag(view, raw),
    // the store's REPLACE_TRANSLATION, which TargetEditor listens to
    setValue: (str) =>
      SegmentStore.emit(SegmentConstants.REPLACE_TRANSLATION, '1', str),
  })
  return drawEdit(
    ELEMENTS.target.cm,
    drawnCm,
    editSegment(f),
    lexiqaData && editSegment(f, lexiqaData),
    (container) => {
      const view = viewOf(container)
      const found = c.at.length
        ? cmPoints(markedWith(f.target, c.at), fx.clean)
        : {}
      const p = c.at.map((_, i) => found[i])
      if (p.length)
        view.dispatch({
          selection: {anchor: p[0], head: p[1] ?? p[0]},
          userEvent: 'select',
        })
      c.impl.cm(handle(view), p, fx)
    },
    (container) => undo(viewOf(container)),
  )
}

// Edits after which the two editors draw something else on purpose, in `phases` (`edited`, `undone`). For these the
// golden holds what CodeMirror draws (INTENTIONAL rewrites applied), checked to differ from Draft when it was frozen.
const EDIT_INTENTIONAL = [
  {
    // fixtures with no source tags never open the menu
    id: /^(?!plain|nbsp tab line feed|entities).* \| tag menu: < then Enter \(first entry\) @/,
    phases: ['undone'],
    // The same choice as in editParity.test.js: the typed `<` is its own undo step in the new editor, so one undo
    // shows the `<` again where Draft shows the segment as it was.
    reason: 'the typed < is its own undo step in the new editor',
  },
  {
    id: /\| type before the Lexiqa warnings @/,
    phases: ['edited', 'undone'],
    // A decision. Editarea hides every Lexiqa warning as soon as the text changes, until Lexiqa checks it again; the
    // new editor keeps each warning the edit does not touch on the same words (the lexiqa layer is volatile: an edit
    // drops only the warnings it touches), and an undo gives them back with the text.
    reason: 'the new editor keeps the Lexiqa warnings an edit does not touch',
  },
  {
    id: /^(g|g inside a word|nested g and x|rtl) \| delete @ bt$/,
    phases: ['edited'],
    // Draft wrong. Deleting an opening tag leaves its closing tag without a pair: the new editor labels it `?`, as
    // both editors label an unpaired closing tag they load (and Draft one it gets back from a drop). Draft keeps the
    // number of the pair it no longer has until the segment is loaded again.
    reason:
      'Draft keeps the number of a closing tag whose opening tag was deleted',
  },
]

describe('after an edit, both editors draw the same', () => {
  beforeAll(() => {
    initTagSignature({show_whitespace: 0})
  })
  test.each(EDIT_CASES)('%s', (id, c) => {
    global.config.isTargetRTL = Boolean(c.f.rtl)
    const cm = cmEdit(c)
    const raw = clean(c.f.target)
    ;['loaded', 'edited', 'undone'].forEach((phase) =>
      expectSameDrawing(id, raw, cm[phase], GOLDEN.edits[id][phase]),
    )
    // the edit changed what is drawn, or there is nothing to compare
    expect(lines(cm.edited)).not.toEqual(lines(cm.loaded))
  })

  test('every intentional difference after an edit still matches a case', () => {
    EDIT_INTENTIONAL.forEach((r) =>
      expect({
        rule: r.reason,
        matches: EDIT_CASES.some(([id]) => r.id.test(id)),
      }).toEqual({rule: r.reason, matches: true}),
    )
  })
})

test('every intentional difference is still needed by some case', () => {
  expect(
    INTENTIONAL.map((r) => r.name).filter((n) => !neededRules.has(n)),
  ).toEqual([])
})
