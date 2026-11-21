// Display-only marks for characters the translator cannot otherwise see. Nothing here changes the document.
import {
  EditorView,
  highlightSpecialChars,
  highlightWhitespace,
} from '@codemirror/view'

const NBSP = 0xa0
const NBSP_LABEL = '°' // the nbsp tag placeholder of the cattool

const renderSpecial = (code, description, placeholder) => {
  const el = document.createElement('span')
  if (code === NBSP) {
    el.className = 'cm-specialChar cm-nbsp'
    el.textContent = NBSP_LABEL
  } else {
    el.className = 'cm-specialChar'
    el.textContent = placeholder
  }
  el.title = description
  return el
}

const displayTheme = EditorView.baseTheme({
  '.cm-nbsp': {color: 'inherit', opacity: '0.6'},
  '.cm-tab': {position: 'relative'},
  '.cm-tab::before': {
    content: '"\\21E5"',
    position: 'absolute',
    left: '0',
    opacity: '0.6',
  },
})

/**
 * nbsp and tab are always marked (they are what the cattool shows as the `°` and tab-arrow tags); spaces only when
 * `showWhitespace` is on.
 */
export const displayExtensions = ({showWhitespace = false} = {}) => [
  highlightSpecialChars({
    addSpecialChars: /[\u00a0\t]/,
    render: renderSpecial,
  }),
  displayTheme,
  showWhitespace ? highlightWhitespace() : [],
]
