// Display-only marks for characters the translator cannot otherwise see. Nothing here changes the document.
import {
  EditorView,
  highlightSpecialChars,
  highlightWhitespace,
} from '@codemirror/view'

const MARK_COLOR = 'var(--color-blue-500, #0099cc)' // the cattool tag chip blue

// The marks are drawn by the editor, never by a glyph of the font: a font with no glyph for the character (LSEP in
// Chrome on macOS) would show nothing. `keepsWidth`: the mark holds the character itself, so it takes its real width,
// and draws over it; every other mark replaces a character that has no width of its own.
const space = (title, className, label = '') => ({
  label,
  className: `cm-space-mark ${className}`,
  title,
  keepsWidth: true,
})
const zeroWidth = (title, className) => ({
  label: '',
  className: `cm-zero-width ${className}`,
  title,
  keepsWidth: false,
})
const separator = (title, label) => ({
  label,
  className: 'cm-separator-mark',
  title,
  keepsWidth: false,
})

/**
 * Every special character the editor marks, character -> {label, className, title, keepsWidth}. `label` is the text
 * the mark shows (none for a bar or an underline), `title` its tooltip. The tab is not here: CodeMirror draws it.
 */
export const SPECIAL_CHARS = {
  ' ': space('Non-breaking space (U+00A0)', 'cm-nbsp', '°'), // the nbsp tag placeholder of the cattool
  ' ': space('Narrow non-breaking space (U+202F)', 'cm-narrow-nbsp', '°'),
  ' ': space('En space (U+2002)', 'cm-typographic-space'),
  ' ': space('Em space (U+2003)', 'cm-typographic-space'),
  ' ': space('Three-per-em space (U+2004)', 'cm-typographic-space'),
  ' ': space('Four-per-em space (U+2005)', 'cm-typographic-space'),
  ' ': space('Six-per-em space (U+2006)', 'cm-typographic-space'),
  ' ': space('Figure space (U+2007)', 'cm-typographic-space'),
  ' ': space('Punctuation space (U+2008)', 'cm-typographic-space'),
  ' ': space('Thin space (U+2009)', 'cm-typographic-space'),
  ' ': space('Hair space (U+200A)', 'cm-typographic-space'),
  ' ': space('Medium mathematical space (U+205F)', 'cm-typographic-space'),
  '　': space('Ideographic space (U+3000)', 'cm-ideographic-space'),
  '⁠': zeroWidth('Word joiner (U+2060)', 'cm-word-joiner'), // the cattool word joiner tag: a blue chip
  '﻿': zeroWidth('Zero-width non-breaking space (U+FEFF)', 'cm-bom'),
  '​': zeroWidth('Zero-width space (U+200B)', 'cm-zwsp'),
  '‌': zeroWidth('Zero-width non-joiner (U+200C)', 'cm-zwnj'),
  '‍': zeroWidth('Zero-width joiner (U+200D)', 'cm-zwj'),
  '­': zeroWidth('Soft hyphen (U+00AD)', 'cm-soft-hyphen'),
  ' ': separator('Line separator (U+2028)', 'LS'),
  ' ': separator('Paragraph separator (U+2029)', 'PS'),
}

const MARKED = new RegExp(
  `[\\t${Object.keys(SPECIAL_CHARS)
    .map((ch) => `\\u${ch.charCodeAt(0).toString(16).padStart(4, '0')}`)
    .join('')}]`,
)

const renderSpecial = (code, description, placeholder) => {
  const el = document.createElement('span')
  const ch = String.fromCodePoint(code)
  const mark = SPECIAL_CHARS[ch]
  if (mark) {
    el.className = `cm-specialChar ${mark.className}`
    el.title = mark.title
    if (mark.keepsWidth) {
      el.textContent = ch
      if (mark.label) el.dataset.label = mark.label
    } else {
      el.textContent = mark.label
    }
  } else {
    // bidi and control characters: CodeMirror's own mark
    el.className = 'cm-specialChar'
    el.textContent = placeholder
    el.title = description
  }
  el.setAttribute('aria-label', el.title)
  return el
}

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
  '.cm-space-mark': {position: 'relative'},
  // The label sits over the character: the space keeps its own width.
  '.cm-space-mark[data-label]::before': {
    content: 'attr(data-label)',
    position: 'absolute',
    left: '50%',
    transform: 'translateX(-50%)',
    color: MARK_COLOR,
  },
  '.cm-narrow-nbsp::before': {fontSize: '0.7em'},
  // A bracket under the character: as wide as the space, never less than 2px.
  '.cm-typographic-space::after, .cm-narrow-nbsp::after': {
    content: '""',
    position: 'absolute',
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
  '.cm-ideographic-space::after': {
    content: '""',
    position: 'absolute',
    inset: '0.15em 0.1em',
    border: `1px dashed ${MARK_COLOR}`,
    boxSizing: 'border-box',
  },
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
 * shows as the `°`, tab-arrow and word joiner tags); spaces only when `showWhitespace` is on.
 */
export const displayExtensions = ({showWhitespace = false} = {}) => [
  highlightSpecialChars({
    addSpecialChars: MARKED,
    render: renderSpecial,
  }),
  displayTheme,
  showWhitespace ? highlightWhitespace() : [],
]
