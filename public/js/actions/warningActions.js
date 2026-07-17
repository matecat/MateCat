import AppDispatcher from '../stores/AppDispatcher'
import SegmentConstants from '../constants/SegmentConstants'

export const updateGlobalWarnings = (warnings) => {
  AppDispatcher.dispatch({
    actionType: SegmentConstants.UPDATE_GLOBAL_WARNINGS,
    warnings: warnings,
  })
}
