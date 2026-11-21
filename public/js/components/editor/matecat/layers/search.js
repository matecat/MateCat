// Search hits in the source: the marks the closed segment shows (SegmentWrapper → SearchUtils.getSearchMarks), on the
// same markable text, so an open and a closed segment highlight the same occurrences.
import SearchUtils from '../../../header/cattol/search/searchUtils'
import {visibleDoc} from '../../core/docModel'
import {visibleToDoc} from '../../core/layers'

/** The search layer ranges of the source editor for segment `sid`; none when the segment is not in search. */
export const searchRanges = (state, segment) => {
  if (!segment.inSearch) return []
  const marks = SearchUtils.getSearchMarks(
    visibleDoc(state).text,
    true,
    segment.sid,
  )
  return visibleToDoc(state, marks).map(({from, to, className}) => ({
    from,
    to,
    className,
    tagName: 'mark',
  }))
}
