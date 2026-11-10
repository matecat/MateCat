// The tooltip of a trimmed or compressed tag chip in the editors: the segment view's (segmentView/tagTooltip.js), fed
// by the chip events of `tagEventSink`, with the compression the cattool store holds.
import {EditorView} from '@codemirror/view'
import CatToolStore from '../../../stores/CatToolStore'
import {createTagTooltip as createChipTooltip} from '../../segmentView/tagTooltip'

/**
 * The tag tooltip of one editor: `onEvent` takes the chip events of `tagEventSink`, `extension` hides the tooltip
 * when the document changes under it, `destroy` removes it.
 */
export const createTagTooltip = () => {
  const {onEvent, hide} = createChipTooltip({
    isCompressed: () => CatToolStore.isTagsCompressed(),
  })
  // A rebuilt chip never reports the mouse leaving it.
  const extension = EditorView.updateListener.of((update) => {
    if (update.docChanged) hide()
  })
  return {onEvent, extension, destroy: hide}
}
