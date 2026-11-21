import {hasDataOriginalTags} from '../utils/segmentText/tagUtils'
import {setSegmentAsTagged} from './segmentDispatchActions'
import SegmentStore from '../stores/SegmentStore'
import {checkTPEnabled} from '../utils/tagProjectionUtils'

export const disableTPOnSegment = (segmentObj) => {
  var currentSegment = segmentObj
    ? segmentObj
    : SegmentStore.getCurrentSegment()

  if (!currentSegment) return

  var tagProjectionEnabled =
    hasDataOriginalTags(currentSegment.segment) && !currentSegment.tagged
  if (checkTPEnabled() && tagProjectionEnabled) {
    setSegmentAsTagged(currentSegment.sid, currentSegment.id_file)
  }
}
