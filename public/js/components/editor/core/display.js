// Display-only marks for characters the translator cannot otherwise see. Nothing here changes the document.
import {Compartment, RangeSetBuilder, StateField} from '@codemirror/state'
import {
  Decoration,
  EditorView,
  WidgetType,
  highlightSpecialChars,
} from '@codemirror/view'
import {
  LINE_BREAK,
  TAB,
  specialCharMark,
  specialCharsPattern,
} from '../../../utils/segmentText/specialChars'

// The marks are drawn by the editor, never by a glyph of the font: a font with no glyph for the character (LSEP in
// Chrome on macOS) would show nothing. The table and the classes are shared with SegmentView
// (utils/segmentText/specialChars.js), and so are the styles (css/sass/components/segment/SpecialCharMarks.scss).

// A visual part of a mark: drawn by its own `::before` (a label) or its border (a shape), so it holds no text that a
// selection, a copy or a screen reader could pick up.
const part = (className, label) => {
  const el = document.createElement('span')
  el.className = className
  el.setAttribute('aria-hidden', 'true')
  if (label) el.dataset.label = label
  return el
}

const renderSpecial = (showWhitespace) => (code, description, placeholder) => {
  const el = document.createElement('span')
  const ch = String.fromCodePoint(code)
  const mark = specialCharMark(ch, {showWhitespace})
  if (mark) {
    el.className = `cm-specialChar ${mark.className}`
    if (mark.title) el.setAttribute('aria-label', mark.title)
    // a mark that keeps its width holds the character; any other replaces it
    if (mark.keepsWidth) el.textContent = ch
    if (mark.label) el.append(part('special-char-mark-label', mark.label))
    if (mark.shape) el.append(part(`special-char-mark-${mark.shape}`))
  } else {
    // bidi and control characters: CodeMirror's own mark
    el.className = 'cm-specialChar'
    el.textContent = placeholder
    el.setAttribute('aria-label', description)
  }
  return el
}

// The marked characters: the table and the tab always, the space with `showWhitespace`. A space mark is a replace
// widget like the others (it holds the space, so the line still wraps there): a mark decoration would join a run of
// spaces into one element, with one dot for all of them.
const specialChars = (showWhitespace) =>
  highlightSpecialChars({
    addSpecialChars: new RegExp(specialCharsPattern({showWhitespace})),
    render: renderSpecial(showWhitespace),
  })

class LineBreakWidget extends WidgetType {
  eq() {
    return true
  }
  toDOM() {
    const el = document.createElement('span')
    el.className = LINE_BREAK.className
    el.setAttribute('aria-label', LINE_BREAK.title)
    el.append(part('special-char-mark-label', LINE_BREAK.label))
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

// The tab is CodeMirror's own widget (it sizes it to the next tab stop): only its arrow is drawn here, with the label
// and the color of the shared tab mark.
const displayTheme = EditorView.baseTheme({
  '.cm-tab': {position: 'relative'},
  '.cm-tab::before': {
    content: JSON.stringify(TAB.label),
    position: 'absolute',
    left: '0',
    color: 'var(--color-blue-500, #0099cc)',
  },
})

// The show whitespace option can change while the editor lives (the user's setting): it sits in a compartment.
const whitespaceCompartment = new Compartment()

/** The effect that turns the space marks on or off in a live editor. */
export const setShowWhitespaceEffect = (showWhitespace) =>
  whitespaceCompartment.reconfigure(specialChars(showWhitespace))

/**
 * The characters of `SPECIAL_CHARS` and the tab are always marked (nbsp, tab and word joiner are what the cattool
 * shows as the `°`, tab-arrow and word joiner tags), and so is every line break (the cattool line feed glyph);
 * spaces only when `showWhitespace` is on.
 */
export const displayExtensions = ({showWhitespace = false} = {}) => [
  whitespaceCompartment.of(specialChars(showWhitespace)),
  lineBreaks,
  displayTheme,
]
