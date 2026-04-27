import {hasDataOriginalTags} from '../components/segments/utils/DraftMatecatUtils/tagUtils'
import {setSegmentAsTagged} from './segmentDispatchActions'
import SegmentStore from '../stores/SegmentStore'
import SegmentUtils from '../utils/segmentUtils'

export const disableTPOnSegment = (segmentObj) => {
  var currentSegment = segmentObj
    ? segmentObj
    : SegmentStore.getCurrentSegment()

  if (!currentSegment) return

  var tagProjectionEnabled =
    hasDataOriginalTags(currentSegment.segment) && !currentSegment.tagged
  if (SegmentUtils.checkTPEnabled() && tagProjectionEnabled) {
    setSegmentAsTagged(currentSegment.sid, currentSegment.id_file)
  }
}
