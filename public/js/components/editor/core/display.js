// Display-only marks for characters the translator cannot otherwise see. Nothing here changes the document.
import {RangeSetBuilder, StateField} from '@codemirror/state'
import {
  Decoration,
  EditorView,
  WidgetType,
  highlightSpecialChars,
  highlightWhitespace,
} from '@codemirror/view'

const MARK_COLOR = 'var(--color-blue-500, #0099cc)' // the cattool tag chip blue

// The marks are drawn by the editor, never by a glyph of the font: a font with no glyph for the character (LSEP in
// Chrome on macOS) would show nothing. `keepsWidth`: the mark holds the character itself, so it takes its real width,
// and draws over it; every other mark replaces a character that has no width of its own.
// The visual parts of a mark (`label`, `shape`) are child elements, never pseudo-elements of the mark: the mark has an
// `aria-label`, and the cattool tooltip (commons/_aria-label-tooltip.scss) owns `::before`/`::after` of every element
// that has one.
const space = (title, className, {label = '', shape = ''} = {}) => ({
  label,
  shape,
  className: `cm-space-mark ${className}`,
  title,
  keepsWidth: true,
})
const zeroWidth = (title, className) => ({
  label: '',
  shape: '',
  className: `cm-zero-width ${className}`,
  title,
  keepsWidth: false,
})
const separator = (title, label) => ({
  label,
  shape: '',
  className: 'cm-separator-mark',
  title,
  keepsWidth: false,
})

/**
 * Every special character the editor marks, character -> {label, shape, className, title, keepsWidth}. `label` is the
 * text the mark shows (none for a bar or an underline), `shape` the outline drawn over a space (`bracket`, `box`),
 * `title` its tooltip. The tab is not here: CodeMirror draws it. The line break is `LINE_BREAK`.
 */
export const SPECIAL_CHARS = {
  '\u00a0': space('Non-breaking space (U+00A0)', 'cm-nbsp', {label: '\u00b0'}), // the nbsp tag placeholder of the cattool
  '\u202f': space('Narrow non-breaking space (U+202F)', 'cm-narrow-nbsp', {
    label: '\u00b0',
    shape: 'bracket',
  }),
  '\u2002': space('En space (U+2002)', 'cm-typographic-space', {
    shape: 'bracket',
  }),
  '\u2003': space('Em space (U+2003)', 'cm-typographic-space', {
    shape: 'bracket',
  }),
  '\u2004': space('Three-per-em space (U+2004)', 'cm-typographic-space', {
    shape: 'bracket',
  }),
  '\u2005': space('Four-per-em space (U+2005)', 'cm-typographic-space', {
    shape: 'bracket',
  }),
  '\u2006': space('Six-per-em space (U+2006)', 'cm-typographic-space', {
    shape: 'bracket',
  }),
  '\u2007': space('Figure space (U+2007)', 'cm-typographic-space', {
    shape: 'bracket',
  }),
  '\u2008': space('Punctuation space (U+2008)', 'cm-typographic-space', {
    shape: 'bracket',
  }),
  '\u2009': space('Thin space (U+2009)', 'cm-typographic-space', {
    shape: 'bracket',
  }),
  '\u200a': space('Hair space (U+200A)', 'cm-typographic-space', {
    shape: 'bracket',
  }),
  '\u205f': space(
    'Medium mathematical space (U+205F)',
    'cm-typographic-space',
    {
      shape: 'bracket',
    },
  ),
  '\u3000': space('Ideographic space (U+3000)', 'cm-ideographic-space', {
    shape: 'box',
  }),
  '\u2060': zeroWidth('Word joiner (U+2060)', 'cm-word-joiner'), // the cattool word joiner tag: a blue chip
  '\ufeff': zeroWidth('Zero-width non-breaking space (U+FEFF)', 'cm-bom'),
  '\u200b': zeroWidth('Zero-width space (U+200B)', 'cm-zwsp'),
  '\u200c': zeroWidth('Zero-width non-joiner (U+200C)', 'cm-zwnj'),
  '\u200d': zeroWidth('Zero-width joiner (U+200D)', 'cm-zwj'),
  '\u00ad': zeroWidth('Soft hyphen (U+00AD)', 'cm-soft-hyphen'),
  '\u2028': separator('Line separator (U+2028)', 'LS'),
  '\u2029': separator('Paragraph separator (U+2029)', 'PS'),
}

/** The end-of-line mark of a line break ("\n", the cattool line feed tag): the glyph of the cattool `tag-lf` chip. */
export const LINE_BREAK = {
  label: '\u21b5',
  className: 'cm-line-break',
  title: 'Line break',
}

const MARKED = new RegExp(
  `[\\t${Object.keys(SPECIAL_CHARS)
    .map((ch) => `\\u${ch.charCodeAt(0).toString(16).padStart(4, '0')}`)
    .join('')}]`,
)

// A visual part of a mark: drawn by its own `::before` (a label) or its border (a shape), so it holds no text that a
// selection, a copy or a screen reader could pick up.
const part = (className, label) => {
  const el = document.createElement('span')
  el.className = className
  el.setAttribute('aria-hidden', 'true')
  if (label) el.dataset.label = label
  return el
}

const renderSpecial = (code, description, placeholder) => {
  const el = document.createElement('span')
  const ch = String.fromCodePoint(code)
  const mark = SPECIAL_CHARS[ch]
  if (mark) {
    el.className = `cm-specialChar ${mark.className}`
    el.setAttribute('aria-label', mark.title)
    if (mark.keepsWidth) {
      el.textContent = ch
      if (mark.label) el.append(part('cm-mark-label', mark.label))
      if (mark.shape) el.append(part(`cm-mark-${mark.shape}`))
    } else {
      el.textContent = mark.label
    }
  } else {
    // bidi and control characters: CodeMirror's own mark
    el.className = 'cm-specialChar'
    el.textContent = placeholder
    el.setAttribute('aria-label', description)
  }
  return el
}

class LineBreakWidget extends WidgetType {
  eq() {
    return true
  }
  toDOM() {
    const el = document.createElement('span')
    el.className = LINE_BREAK.className
    el.setAttribute('aria-label', LINE_BREAK.title)
    el.append(part('cm-mark-label', LINE_BREAK.label))
    return el
  }
  // a click on the mark places the caret like a click at the end of the line
  ignoreEvent() {
    return false
  }
}

const lineBreakMark = Decoration.widget({
  widget: new LineBreakWidget(),
  side: 1,
})

// At the end of every line but the last: each one ends with a "\n". Segments are short, the whole document is cheap.
const lineBreakMarks = (doc) => {
  const builder = new RangeSetBuilder()
  for (let n = 1; n < doc.lines; n++) {
    const {to} = doc.line(n)
    builder.add(to, to, lineBreakMark)
  }
  return builder.finish()
}

const lineBreaks = StateField.define({
  create: (state) => lineBreakMarks(state.doc),
  update: (marks, tr) => (tr.docChanged ? lineBreakMarks(tr.state.doc) : marks),
  provide: (field) => EditorView.decorations.from(field),
})

// A zero-width mark is a 3px bar; each family has its own shape.
const bar = (background, extra = {}) => ({
  display: 'inline-block',
  width: '3px',
  height: '1em',
  verticalAlign: 'text-bottom',
  boxSizing: 'border-box',
  background,
  ...extra,
})

const displayTheme = EditorView.baseTheme({
  '.cm-space-mark, .cm-line-break': {position: 'relative'},
  // the parts of a mark are drawing only: no selection, no hit target of their own (the mark gets the hover)
  '.cm-mark-label, .cm-mark-bracket, .cm-mark-box': {
    position: 'absolute',
    userSelect: 'none',
    pointerEvents: 'none',
  },
  '.cm-mark-label::before': {content: 'attr(data-label)', color: MARK_COLOR},
  // The label sits over the character: the space keeps its own width.
  '.cm-space-mark > .cm-mark-label': {
    left: '50%',
    transform: 'translateX(-50%)',
  },
  '.cm-narrow-nbsp > .cm-mark-label': {fontSize: '0.7em'},
  // A bracket under the character: as wide as the space, never less than 2px.
  '.cm-mark-bracket': {
    left: '0',
    right: '0',
    bottom: '0.1em',
    minWidth: '2px',
    height: '0.25em',
    border: `1px solid ${MARK_COLOR}`,
    borderTop: 'none',
    boxSizing: 'border-box',
  },
  // The full-width square of the CJK space.
  '.cm-mark-box': {
    inset: '0.15em 0.1em',
    border: `1px dashed ${MARK_COLOR}`,
    boxSizing: 'border-box',
  },
  // The line break: the glyph after the last character of its line, in the flow (it holds no document position).
  '.cm-line-break': {
    display: 'inline-block',
    userSelect: 'none',
    padding: '0 2px',
    fontSize: '0.75em',
    lineHeight: '1',
  },
  '.cm-line-break > .cm-mark-label': {position: 'static'},
  '.cm-word-joiner': bar(MARK_COLOR),
  '.cm-bom': bar(
    `repeating-linear-gradient(${MARK_COLOR} 0 2px, transparent 2px 4px)`,
  ),
  '.cm-zwsp': bar(
    `linear-gradient(${MARK_COLOR} 35%, transparent 35% 65%, ${MARK_COLOR} 65%)`,
  ),
  '.cm-zwnj': bar('transparent', {border: `1px solid ${MARK_COLOR}`}),
  '.cm-zwj': bar(`linear-gradient(transparent 50%, ${MARK_COLOR} 50%)`),
  '.cm-soft-hyphen': bar(MARK_COLOR, {
    width: '4px',
    height: '2px',
    verticalAlign: 'middle',
  }),
  '.cm-separator-mark': {
    display: 'inline-block',
    padding: '0 2px',
    border: `1px solid ${MARK_COLOR}`,
    borderRadius: '2px',
    color: MARK_COLOR,
    fontSize: '0.65em',
    lineHeight: '1.2',
    verticalAlign: 'middle',
  },
  '.cm-tab': {position: 'relative'},
  '.cm-tab::before': {
    content: '"\\21E5"',
    position: 'absolute',
    left: '0',
    opacity: '0.6',
  },
})

/**
 * The characters of `SPECIAL_CHARS` and the tab are always marked (nbsp, tab and word joiner are what the cattool
 * shows as the `°`, tab-arrow and word joiner tags), and so is every line break (the cattool line feed glyph);
 * spaces only when `showWhitespace` is on.
 */
export const displayExtensions = ({showWhitespace = false} = {}) => [
  highlightSpecialChars({
    addSpecialChars: MARKED,
    render: renderSpecial,
  }),
  lineBreaks,
  displayTheme,
  showWhitespace ? highlightWhitespace() : [],
]
