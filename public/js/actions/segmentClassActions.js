import AppDispatcher from '../stores/AppDispatcher'
import SegmentConstants from '../constants/SegmentConstants'

export const addClassToSegment = (sid, newClass) => {
  setTimeout(function () {
    AppDispatcher.dispatch({
      actionType: SegmentConstants.ADD_SEGMENT_CLASS,
      id: sid,
      newClass: newClass,
    })
  }, 0)
}

export const removeClassToSegment = (sid, className) => {
  if (sid) {
    setTimeout(function () {
      AppDispatcher.dispatch({
        actionType: SegmentConstants.REMOVE_SEGMENT_CLASS,
        id: sid,
        className: className,
      })
    }, 0)
  }
}
