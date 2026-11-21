import {encodeEditorText} from './decodeSegment'

const ZWSP = new RegExp(String.fromCharCode(parseInt('200B', 16)), 'g')

/**
 * The selected part of the editor as a raw segment slice, the clipboard format
 * of SegmentView (see segmentView/README.md, Copy). A tag entity counts whole
 * when any of its characters is selected.
 * @returns {{raw: string, text: string}|null} `raw` is the slice as a segment
 *   string (tags as their markup), `text` its plain text as shown; null when
 *   nothing is selected
 */
const getSegmentSlice = (
  editorState,
  selection = editorState.getSelection(),
) => {
  if (selection.isCollapsed()) return null
  const content = editorState.getCurrentContent()
  const startKey = selection.getStartKey()
  const endKey = selection.getEndKey()

  let editorText = ''
  let text = ''
  let inRange = false
  content.getBlockMap().forEach((block, key) => {
    if (key === startKey) inRange = true
    if (!inRange) return
    const from = key === startKey ? selection.getStartOffset() : 0
    const to = key === endKey ? selection.getEndOffset() : block.getLength()
    const blockText = block.getText()
    block.findEntityRanges(
      () => true,
      (start, end) => {
        if (end <= from || start >= to) return
        const entityKey = block.getEntityAt(start)
        if (entityKey) {
          editorText += content.getEntity(entityKey).getData().encodedText
          text += blockText.slice(start, end)
        } else {
          const part = blockText.slice(Math.max(start, from), Math.min(end, to))
          editorText += part
          text += part
        }
      },
    )
    if (key === endKey) return false
    editorText += '\n'
    text += '\n'
  })

  return {
    raw: encodeEditorText(editorText),
    text: text.replace(ZWSP, '').replace(/·/g, ' '),
  }
}

export default getSegmentSlice
