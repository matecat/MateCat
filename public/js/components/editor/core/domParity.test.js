/* eslint-disable testing-library/no-node-access, testing-library/no-container --
   Comparing what two editors draw means walking their DOM: neither editor's content has roles or labels to query. */
// DOM parity: the same segment, drawn by the Draft editors (Editarea for the target, SegmentSource for the source)
// and by the CodeMirror editors (TargetEditor, SourceEditor), must show the user the same thing: the same text, the
// same tag chips (label, classes, counter, label inside the element Tag.scss trims with an ellipsis), the same
// special-character marks, the same highlighted ranges with the same tooltips; on load, with the layer data of the
// store, and after an edit and its undo (driven by the harness of editParity.test.js, __parity__/actions.js).
//
// jsdom has no layout, so this compares structure, classes and text, never pixels. drawnDraft() and drawnCm() reduce
// each editor's DOM to a list of tokens; they are the spec of "what the user sees", CSS rules that draw something
// (Tag.scss, SpecialCharMarks.scss) included. Where the two differ on purpose, the difference is listed in
// INTENTIONAL (or EDIT_INTENTIONAL) with the reason, and the test checks it still differs.
//
// It imports Draft (only test files may), and goes away with Draft at M5.
import React from 'react'
import {act, fireEvent, render} from '@testing-library/react'
import Editarea from '../../segments/Editarea'
import SegmentSource from '../../segments/SegmentSource'
import TargetEditor from '../matecat/TargetEditor'
import SourceEditor from '../matecat/SourceEditor'
import {SegmentContext} from '../../segments/SegmentContext'
import {
  targetEditorBridge,
  translationUpdate,
} from '../../segments/targetEditorBridge'
import {sourceEditorBridge} from '../../segments/sourceEditorBridge'
import {initTagSignature} from '../../../utils/segmentText/tagModel'
import {SPECIAL_CHARS, TAB} from '../../../utils/segmentText/specialChars'
import CORPUS from './__fixtures__/roundTripCorpus.json'
import {EditorState as DraftState} from 'draft-js'
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
  draftLoad,
  draftPoints,
  draftSave,
  draftSelection,
  lexiqaReplace,
  tagSuggestions,
  markNames,
  markedWith,
} from './__parity__/actions'
import DraftMatecatUtils from '../../segments/utils/DraftMatecatUtils'
import SearchUtils from '../../header/cattol/search/searchUtils'
import SegmentStore from '../../../stores/SegmentStore'
import SegmentConstants from '../../../constants/SegmentConstants'

// The Draft Editor Editarea renders, unchanged; the test keeps its props so an edit state can be handed to Editarea's
// own onChange, the way Draft hands it every edit.
const mockDraftEditor = {props: null}
// The user's compressed tags option (CatToolStore.isTagsCompressed).
const mockTagsCompressed = {on: false}
jest.mock('draft-js', () => {
  const actual = jest.requireActual('draft-js')
  const {createElement, forwardRef} = jest.requireActual('react')
  const Editor = forwardRef((props, ref) => {
    mockDraftEditor.props = props
    return createElement(actual.Editor, {...props, ref})
  })
  return {...actual, Editor}
})
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
// Actions do nothing, except the ones whose store event an editor draws from: focusTags reaches the Draft chips as
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
// What a chip's text holds but nobody sees: Draft's word joiner chip holds the word joiner itself.
const INVISIBLE = /[\u200b\u2060]/g

// The special characters both editors show as a mark, by what each draws: Draft a tag chip, CodeMirror a display-only
// mark (user decision: nbsp, tab and word joiner are characters with a mark, never tags). Same meaning, same symbol;
// the classes differ by design, and are pinned here: a chip or a mark missing its class is not recognised as the
// character and the comparison fails.
const CHAR_MARKS = [
  {meaning: 'nbsp', draft: 'tag-nbsp', cm: 'special-char-nbsp'},
  {meaning: 'tab', draft: 'tag-tab', cm: 'cm-tab'},
  {
    meaning: 'wordJoiner',
    draft: 'tag-word-joiner',
    cm: 'special-char-word-joiner',
  },
  {meaning: 'lineBreak', draft: 'tag-lf', cm: 'special-char-mark-line-break'},
]

// The highlight of a layer, by the element each editor wraps the text in. Draft's search highlight has no class, only
// an inline background (SearchHighLight.component.js): yellow for a hit, orange for the current one.
const HIGHLIGHTS = [
  {kind: 'lexiqa', draft: '.lexiqahighlight', cm: '.lexiqahighlight'},
  {kind: 'glossary', draft: '.glossaryItem', cm: '.glossaryItem'},
  {
    kind: 'missingTerm',
    draft: '.qaCheckGlossaryItem',
    cm: '.qaCheckGlossaryItem',
  },
  {kind: 'blacklist', draft: '.blacklistItem', cm: '.blacklistItem'},
  {kind: 'icu', draft: '.icuItem', cm: '.icuItem'},
  {kind: 'icuError', draft: '.icuItem-error', cm: '.icuItem-error'},
  {
    kind: 'search',
    draft:
      'span[style*="background-color: rgb(255, 255, 0)"], span[style*="background-color: rgb(255, 210, 14)"]',
    cm: 'mark.searchMarker',
  },
  {
    kind: 'currentSearch',
    draft: 'span[style*="rgb(255, 210, 14)"]',
    cm: 'mark.currSearchItem',
  },
]

const highlightsOf = (node, stop, side) => {
  const kinds = []
  for (let el = node.parentElement; el && el !== stop; el = el.parentElement)
    HIGHLIGHTS.forEach((h) => el.matches(h[side]) && kinds.push(h.kind))
  return [...new Set(kinds)].sort()
}

// A state of a chip that each editor shows its own way: CodeMirror with a class, Draft by what the chip holds.
const CHIP_STATES = [
  // TagEntity markSearch wraps the matching part of the label in a search highlight; the new editor marks the chip.
  {
    cm: 'tag-search-match',
    draft: (el) =>
      HIGHLIGHTS.filter((h) => h.kind === 'search').some((h) =>
        el.querySelector(h.draft),
      ),
  },
]

// A chip: its label as shown, its classes (what Tag.scss styles it by), its counter, and whether the label sits in the
// element Tag.scss trims with an ellipsis (Draft `span[data-text]`, CodeMirror `.tag-text`).
const chipToken = (el, ellipsis, hl, side) => {
  const counter = el.querySelector('.index-counter')
  // Tag.scss hides the label of a compressed chip: one with `tag-compressed`, or any numbered chip while the body has
  // `tags-compressed` (CatToolStore sets it with the user's option). Only the number shows.
  const hidden =
    el.classList.contains('tag-compressed') ||
    (document.body.classList.contains('tags-compressed') &&
      el.classList.contains('tag-numbered'))
  const labelEl = hidden ? null : el.querySelector(ellipsis)
  const label = hidden
    ? counter.textContent
    : (labelEl ?? el).textContent.replace(ZWSP, '')
  return {
    t: 'chip',
    label,
    counter: counter ? counter.textContent : null,
    classes: [
      ...[...el.classList].filter((c) => c !== 'tag'),
      ...(side === 'draft'
        ? CHIP_STATES.filter((st) => st.draft(el)).map((st) => st.cm)
        : []),
    ].sort(),
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

const markOf = (el, side) =>
  CHAR_MARKS.find((m) => el.classList.contains(m[side]))

const pushText = (tokens, text, hl) => {
  if (!text) return
  const last = tokens[tokens.length - 1]
  if (last?.t === 'text' && last.hl.join() === hl.join()) last.text += text
  else tokens.push({t: 'text', text, hl})
}

const walk = (node, stop, side, tokens) => {
  if (node.nodeType === Node.TEXT_NODE) {
    pushText(
      tokens,
      node.textContent.replace(ZWSP, ''),
      highlightsOf(node, stop, side),
    )
    return
  }
  if (node.nodeType !== Node.ELEMENT_NODE) return
  const el = node
  if (el.matches('img.cm-widgetBuffer, br')) return
  const mark = markOf(el, side)
  if (mark) {
    // Draft shows the chip's label; CodeMirror draws the label of a mark with `::before` from `data-label`, and the
    // tab's arrow from the editor theme (display.js), which is TAB.label.
    const symbol =
      side === 'draft'
        ? el.textContent.replace(INVISIBLE, '')
        : mark.meaning === 'tab'
          ? TAB.label
          : (el.querySelector('[data-label]')?.dataset.label ?? '')
    tokens.push({
      t: 'mark',
      meaning: mark.meaning,
      symbol,
      hl: highlightsOf(el, stop, side),
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
      hl: highlightsOf(el, stop, side),
    })
    return
  }
  if (el.classList.contains('tag')) {
    tokens.push(
      chipToken(
        el,
        side === 'draft' ? 'span[data-text="true"]' : '.tag-text',
        highlightsOf(el, stop, side),
        side,
      ),
    )
    return
  }
  el.childNodes.forEach((child) => walk(child, stop, side, tokens))
}

/** The tokens the user sees in a Draft editor rendered in `container`, in document order. */
const drawnDraft = (container) => {
  const tokens = []
  const blocks = [
    ...container.querySelectorAll('[data-contents] > [data-block]'),
  ]
  blocks.forEach((block, i) => {
    if (i > 0) tokens.push({t: 'break'})
    walk(block, block, 'draft', tokens)
    // Tag.scss: every block but the last ends with a `\21B5` drawn by `:last-child::after`.
    if (i < blocks.length - 1)
      tokens.push({t: 'mark', meaning: 'lineBreak', symbol: '\u21b5', hl: []})
  })
  return tokens
}

/** The tokens the user sees in a CodeMirror editor rendered in `container`, in document order. */
const drawnCm = (container) => {
  const tokens = []
  container.querySelectorAll('.cm-content > .cm-line').forEach((line, i) => {
    if (i > 0) tokens.push({t: 'break'})
    walk(line, line, 'cm', tokens)
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
  // the Draft editors scroll to the caret when they focus
  window.scrollTo = () => {}
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
    draft: (seg) => (
      <Editarea
        segment={seg}
        translation={seg.translation}
        updateCounter={() => {}}
        toggleFormatMenu={() => {}}
      />
    ),
    cm: (seg) => (
      <TargetEditor
        segment={seg}
        value={seg.translation}
        translation={seg.translation}
        bridge={targetEditorBridge}
        updateCounter={() => {}}
        toggleFormatMenu={() => {}}
      />
    ),
  },
  source: {
    draft: (seg) => <SegmentSource segment={seg} />,
    cm: (seg) => (
      <SourceEditor
        segment={seg}
        value={seg.segment}
        bridge={sourceEditorBridge}
      />
    ),
  },
}

/** The tokens each editor of `side` draws for `segment` (then `update`): `{draft, cm}`. */
const drawBoth = (side, segment, update, events, hover) => ({
  draft: mount(
    ELEMENTS[side].draft,
    drawnDraft,
    segment,
    update,
    events,
    hover?.draft,
  ),
  cm: mount(ELEMENTS[side].cm, drawnCm, segment, update, events, hover?.cm),
})

// ---------- tooltips ----------
// What hovering the first highlight of each kind shows. Both editors show it with Matecat's common/Tooltip: Draft
// through the Tooltip wrapping the highlight (portalled into the body), the new editor through layerHover, which renders
// common/Tooltip into its own element (layerTooltip.js) — never CodeMirror's `.cm-tooltip`.

const TOOLTIP_KINDS = [
  'lexiqa',
  'blacklist',
  'icuError',
  'glossary',
  'missingTerm',
]
const textOf = (el) => (el?.textContent ?? '').replace(ZWSP, '').trim()

const hoverDraft = (container) =>
  Object.fromEntries(
    TOOLTIP_KINDS.flatMap((kind) => {
      const el = container.querySelector(
        HIGHLIGHTS.find((h) => h.kind === kind).draft,
      )
      if (!el) return []
      // the element common/Tooltip listens on: around the highlight, or inside it (IcuHighlight)
      const wrapper = el.querySelector(':scope > div') ?? el.parentElement
      fireEvent.pointerEnter(wrapper)
      settle()
      // common/Tooltip portals its content into a `.portal-container` of the body (hooks/usePortal.js)
      const shown = [
        ...document.body.querySelectorAll(':scope > .portal-container'),
      ]
        .map(textOf)
        .join('')
      fireEvent.pointerLeave(wrapper)
      settle()
      return [[kind, shown]]
    }),
  )

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
// then shows only its number): its text and the classes of the chip copy it holds. Draft: TagEntity's common/Tooltip
// (portalled into the body); the new editor: segmentView/tagTooltip.js, common/Tooltip in `.tag-full-text-tooltip`.
const chipCopy = (root) => {
  const chip = root?.querySelector('.tag')
  return chip ? {text: textOf(chip), classes: [...chip.classList].sort()} : null
}
const hoverChipDraft = (container) => {
  const chip = container.querySelector('.tag.tag-paired-open')
  const wrapper = chip.closest('.tag-container').parentElement
  fireEvent.pointerEnter(wrapper)
  settle()
  const portals = [
    ...document.body.querySelectorAll(':scope > .portal-container'),
  ]
  const shown = chipCopy(portals.find((p) => p.querySelector('.tag')))
  fireEvent.pointerLeave(wrapper)
  settle()
  return shown
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

// Each entry rewrites both token lists so that one known difference no longer shows; everything else must still be
// equal. A case checks that every entry that changed its tokens was needed (without it the two still differ), and the
// last test that every entry is needed by some case.

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
    draft: (tokens) =>
      mergeTexts(
        tokens.map((t) =>
          t.t === 'text' ? {...t, text: decodeEntities(t.text)} : t,
        ),
      ),
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
    draft: (tokens) => mergeTexts(tokens.map(asCharacter)),
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
    name: 'chip size',
    // NOT DECIDED, pinned so it is seen: the new editor draws its chips with the closed segment's classes, `tag small`
    // (segmentModel.js), so an open segment looks like a closed one: Tag.scss `.tag.small` is 12px with 7px arrows,
    // where Draft's open chips are `.tag`, 13px with 8px arrows. `small` is also what makes the chip inline-flex, which
    // a chip directly in a CodeMirror line needs (Draft wraps each chip in an inline-block `.tag-container`).
    reason:
      'the new editor draws its chips at the closed segment size (.tag.small)',
    cm: (tokens) =>
      tokens.map((t) =>
        t.classes ? {...t, classes: t.classes.filter((k) => k !== 'small')} : t,
      ),
  },
  {
    name: 'compressed chips',
    // A difference of mechanism, not of drawing: Draft marks each compressed chip `tag-compressed` and leaves its label
    // out; the new editor keeps the label and Tag.scss hides it under `body.tags-compressed`. Both show the number.
    reason:
      'Draft marks compressed chips with a class, the new editor relies on body.tags-compressed',
    draft: (tokens) =>
      tokens.map((t) =>
        t.classes
          ? {...t, classes: t.classes.filter((k) => k !== 'tag-compressed')}
          : t,
      ),
  },
  {
    name: 'focused chip after an undo or a drop',
    id: /\| (backspace @ at|delete @ bt|drag and drop \(move\) @ .*)$/,
    // Not judged here: which chip shows as focused (`tag-focused`) follows the selection, and this test compares
    // content, not selections. After undoing a deleted tag Draft focuses the tag beside the caret, the new editor only
    // a selected one; after a drop the new editor keeps the dropped text selected, focusing a chip in it.
    reason:
      'the focused chip follows the selection each editor leaves after an undo or a drop',
    draft: (tokens) => tokens.map(unfocus),
    cm: (tokens) => tokens.map(unfocus),
  },
  {
    name: 'current search hit after a hit in a tag label',
    id: /\| search in a tag label$/,
    // NOT a decision: a bug shared with the closed segment view, pinned here until it is fixed. The cattool numbers
    // the hits of a segment in its decoded text, tag labels included (SearchUtils.createSearchObject), and Draft marks
    // the featured one. The new editor, like the closed view (SegmentWrapper), asks SearchUtils.getSearchMarks for
    // the marks of its markable text, where a tag is one opaque character: a hit inside a tag label is not counted, so
    // every later hit takes the number of the one before it and the featured hit is not drawn as current.
    reason:
      'BUG: the new editor and the closed view miscount the current hit after a hit in a tag label',
    draft: (tokens) =>
      tokens.map((t) =>
        t.hl ? {...t, hl: t.hl.filter((k) => k !== 'currentSearch')} : t,
      ),
  },
]

const unfocus = (t) =>
  t.classes ? {...t, classes: t.classes.filter((k) => k !== 'tag-focused')} : t

const rewrite = (rules, id, raw, draft, cm) =>
  rules
    .filter(
      (rule) =>
        (!rule.applies || rule.applies(raw)) && (!rule.id || rule.id.test(id)),
    )
    .reduce(
      ([d, c], rule) => [
        rule.draft ? rule.draft(d) : d,
        rule.cm ? rule.cm(c) : c,
      ],
      [draft, cm],
    )

const lines = (tokens) => tokens.map(show)
const neededRules = new Set()

/** Equal to Draft once the intentional differences are rewritten, each of them needed. */
const expectSameDrawing = (id, raw, draft, cm) => {
  const [d, c] = rewrite(INTENTIONAL, id, raw, draft, cm)
  expect(lines(c)).toEqual(lines(d))
  INTENTIONAL.forEach((rule) => {
    const others = INTENTIONAL.filter((r) => r !== rule)
    const [d2, c2] = rewrite(others, id, raw, draft, cm)
    const changed =
      lines(d2).join('\n') !== lines(d).join('\n') ||
      lines(c2).join('\n') !== lines(c).join('\n')
    if (!changed) return
    neededRules.add(rule.name)
    expect({
      rule: rule.name,
      differsWithout: lines(c2).join('\n') !== lines(d2).join('\n'),
    }).toEqual({
      rule: rule.name,
      differsWithout: true,
    })
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
    const {draft, cm} = drawBoth(side, makeSegment(raw))
    expectSameDrawing(`${side} | ${raw}`, raw, draft, cm)
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
  const text = DraftMatecatUtils.prepareTextForLexiqa(draftSave(draftLoad(raw)))
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
// (one occurrencesList entry per hit; `ingnoreCase` is really "match case").
const search = (side, term, count, current) => {
  const occurrences = Array.from({length: count}, (_, i) => ({
    searchProgressiveIndex: i,
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
      const {draft, cm} = drawBoth(
        c.side,
        makeSegment(c.raw, c.open),
        makeSegment(c.raw, {...c.open, ...layerData, ...c.search?.segment}),
        c.events ??
          (c.search ? [[SegmentConstants.ADD_SEARCH_RESULTS, '1']] : []),
        {draft: hoverDraft, cm: hoverCm},
      )
      expectSameDrawing(id, c.raw, draft.tokens, cm.tokens)
      expect(cm.tooltips).toEqual(draft.tooltips)
      // the case highlights something, or it compares nothing
      expect(
        draft.tokens.some(
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
      const seg = makeSegment(raw)
      const draft = mount(
        ELEMENTS[side].draft,
        drawnDraft,
        seg,
        null,
        [],
        hoverChipDraft,
      )
      const cm = mount(ELEMENTS[side].cm, drawnCm, seg, null, [], hoverChipCm)
      expectSameDrawing(
        `${side} | compressed | ${raw}`,
        raw,
        draft.tokens,
        cm.tokens,
      )
      expect(
        draft.tokens.some((t) => t.classes?.includes('tag-compressed')),
      ).toBe(true)
      expect(cm.tooltips).toEqual(draft.tooltips)
      expect(draft.tooltips).not.toBeNull()
    },
  )
})

// ---------- after an edit ----------
// The same edit in both editors, mounted as above: Draft through Editarea's own onChange with the state the edit
// harness computes (the pure calls Editarea makes), CodeMirror through TargetEditor's view, keymap, DOM handlers and
// store events. What each draws is compared after the edit and again after one undo.

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
    ...(tagSuggestions(f, draftLoad(clean(f.target))).missingTags.length
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

const editareaRef = React.createRef()
const draftEdit = (c) => {
  const f = c.f
  const lexiqaData = c.lexiqa ? editLexiqa(f) : undefined
  const element = (seg) => (
    <Editarea
      ref={editareaRef}
      segment={seg}
      translation={seg.translation}
      updateCounter={() => {}}
      toggleFormatMenu={() => {}}
    />
  )
  const fx = {...f, clean: clean(f.target)}
  const change = (state) => mockDraftEditor.props.onChange(state)
  return drawEdit(
    element,
    drawnDraft,
    editSegment(f),
    lexiqaData && editSegment(f, lexiqaData),
    () => {
      let state = editareaRef.current.state.editorState
      const p = c.at.length
        ? draftPoints(markedWith(f.target, c.at), state)
        : []
      if (p.length)
        state = DraftState.acceptSelection(
          state,
          draftSelection(state, p[0], p[1]),
        )
      change(c.impl.draft(state, p, fx))
    },
    () => change(DraftState.undo(editareaRef.current.state.editorState)),
  )
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

// Edits after which the two editors draw something else on purpose, in `phases` (`edited`, `undone`).
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
    const draft = draftEdit(c)
    const cm = cmEdit(c)
    const rule = (phase) =>
      EDIT_INTENTIONAL.find((r) => r.phases.includes(phase) && r.id.test(id))
    const raw = clean(c.f.target)
    ;['loaded', 'edited', 'undone'].forEach((phase) => {
      if (!rule(phase)) {
        expectSameDrawing(id, raw, draft[phase], cm[phase])
        return
      }
      const [d, m] = rewrite(INTENTIONAL, id, raw, draft[phase], cm[phase])
      expect({
        phase,
        differs: lines(m).join('\n') !== lines(d).join('\n'),
      }).toEqual({phase, differs: true})
    })
    // the edit changed what is drawn, or there is nothing to compare
    expect(lines(draft.edited)).not.toEqual(lines(draft.loaded))
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
