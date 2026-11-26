// Copy, cut, paste and drop. A copy carries the raw segment slice in the `text/html` flavour (the format SegmentView
// and the Draft editors use), so tags survive a copy between segment editors; outside text is pasted with its tag
// markup stripped.
import {EditorView} from '@codemirror/view'
import {
  readSegmentClipboard,
  segmentClipboardHtml,
  sliceSegment,
  stripTagMarkup,
} from '../../segmentView/segmentModel'
import {
  addTags,
  insertContent,
  serialize,
  snapToTags,
  tagAround,
  toDoc,
} from './docModel'
import {TAG_SELECTOR} from './tags'

/** The selected range, grown to whole tags, as `{from, to, raw, plain}`; null when empty. */
export const clipboardSlice = (state) => {
  const sel = state.selection.main
  if (sel.empty) return null
  const {from, to} = snapToTags(state, sel.from, sel.to)
  const raw = serialize(state, from, to)
  return {from, to, raw, plain: sliceSegment(raw, 0, Infinity).text}
}

/** External plain text -> document text: tag markup dropped; a `°` stays a degree sign. */
export const externalText = (plain) =>
  stripTagMarkup(plain).replace(/\r\n?/g, '\n')

/**
 * What a data transfer (clipboard or drop) inserts: the segment slice it carries, tags included, or else its plain
 * text, which never makes tags.
 * @returns {{text: string, tags: Array<{from: number, to: number}>}} tag ranges relative to `text`
 */
export const transferContent = (data) => {
  const raw = readSegmentClipboard(data.getData('text/html'))
  if (raw !== null) return toDoc(raw)
  return {text: externalText(data.getData('text/plain') || ''), tags: []}
}

const writeSlice = (data, slice) => {
  data.clearData()
  data.setData('text/plain', slice.plain)
  data.setData(
    'text/html',
    segmentClipboardHtml({raw: slice.raw, text: slice.plain}),
  )
}

const copyHandlers = EditorView.domEventHandlers({
  copy(event, view) {
    const slice = clipboardSlice(view.state)
    if (!slice || !event.clipboardData) return false
    event.preventDefault()
    writeSlice(event.clipboardData, slice)
    return true
  },
  cut(event, view) {
    const slice = clipboardSlice(view.state)
    if (!slice || !event.clipboardData) return false
    event.preventDefault()
    writeSlice(event.clipboardData, slice)
    if (!view.state.readOnly) {
      view.dispatch({
        changes: {from: slice.from, to: slice.to},
        userEvent: 'delete.cut',
      })
    }
    return true
  },
})

const pasteHandler = EditorView.domEventHandlers({
  paste(event, view) {
    if (view.state.readOnly || !event.clipboardData) return false
    event.preventDefault()
    const content = transferContent(event.clipboardData)
    if (content.text) {
      const {from, to} = view.state.selection.main
      view.dispatch(insertContent(view.state, from, to, content), {
        userEvent: 'input.paste',
        scrollIntoView: true,
      })
    }
    return true
  },
})

// CodeMirror's own dragstart writes text/plain from the document, raw tag markup included: give it the plain text.
const plainDragText = EditorView.clipboardOutputFilter.of(
  (text, state) => clipboardSlice(state)?.plain ?? text,
)

const dragHandlers = () => {
  let dragged = null // the range dragged out of this editor: a drop back into it moves it
  return EditorView.domEventHandlers({
    dragstart(event, view) {
      const slice = clipboardSlice(view.state)
      if (!slice || !event.dataTransfer) return false
      dragged = {from: slice.from, to: slice.to}
      event.dataTransfer.setData(
        'text/html',
        segmentClipboardHtml({raw: slice.raw, text: slice.plain}),
      )
      return false
    },
    dragend() {
      dragged = null
      return false
    },
    dragover(event, view) {
      if (!view.state.readOnly) event.preventDefault()
      return false
    },
    drop(event, view) {
      if (view.state.readOnly || !event.dataTransfer) return false
      event.preventDefault()
      const source = dragged
      dragged = null
      const pos = view.posAtCoords({x: event.clientX, y: event.clientY}, false)
      // Nothing may be dropped onto a tag.
      if (event.target.closest?.(TAG_SELECTOR) || tagAround(view.state, pos))
        return true
      const move = source && !(event.altKey || event.ctrlKey)
      if (move && pos >= source.from && pos <= source.to) return true
      const content = transferContent(event.dataTransfer)
      if (!content.text) return true
      if (move) {
        // Delete and insert in one transaction: one undo step.
        const changes = view.state.changes([
          {from: source.from, to: source.to},
          {from: pos, insert: content.text},
        ])
        const at = changes.mapPos(pos, -1)
        view.dispatch({
          changes,
          effects: addTags.of(
            content.tags.map((r) => ({from: at + r.from, to: at + r.to})),
          ),
          selection: {anchor: at, head: at + content.text.length},
          userEvent: 'move.drop',
        })
      } else {
        view.dispatch(insertContent(view.state, pos, pos, content, true), {
          userEvent: 'input.drop',
        })
      }
      view.focus()
      return true
    },
  })
}

/** `editable`: the target also takes paste and drop. Both copy and drag out. */
export const clipboardExtensions = ({editable}) => [
  copyHandlers,
  plainDragText,
  dragHandlers(),
  editable ? pasteHandler : [],
]
