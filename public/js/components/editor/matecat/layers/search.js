// Search hits in the source and the target: the marks the closed segment shows (SegmentWrapper →
// SearchUtils.getSearchMarks), on the same markable text, so an open and a closed segment highlight the same
// occurrences. A tag is one opaque character there, so a hit inside a tag label is marked on its chip instead, as
// TagEntity's markSearch does.
import SearchUtils from '../../../header/cattol/search/searchUtils'
import {visibleDoc} from '../../core/docModel'
import {visibleToDoc} from '../../core/layers'
import {chipTokens} from '../../core/tags'

export const SEARCH_CHIP_CLASS = 'tag-search-match'

/** The search layer ranges of the source (or, `isSource` false, the target) of `segment`; none out of search. */
export const searchRanges = (state, segment, isSource = true) => {
  if (!segment.inSearch) return []
  const marks = SearchUtils.getSearchMarks(
    visibleDoc(state).text,
    isSource,
    segment.sid,
  )
  return visibleToDoc(state, marks).map(({from, to, className}) => ({
    from,
    to,
    className,
    tagName: 'mark',
  }))
}

/**
 * The chips whose label holds the searched text of their side, as tag states `{from, to, className}`: Draft marks the
 * matching part of the tag text; here the chip label is marked.
 */
export const searchChipRanges = (state, segment, isSource = true) => {
  const {inSearch, searchParams} = segment
  const term = isSource ? searchParams?.source : searchParams?.target
  if (!inSearch || !term) return []
  const regex = SearchUtils.getSearchRegExp(
    term,
    searchParams.ingnoreCase,
    searchParams.exactMatch,
  )
  return chipTokens(state)
    .filter(({token}) => token.label && token.label.search(regex) >= 0)
    .map(({from, to}) => ({from, to, className: SEARCH_CHIP_CLASS}))
}
