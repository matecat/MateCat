// Search hits in the source and the target: the marks the closed segment shows (SegmentWrapper →
// SearchUtils.getSearchMarks), on the same markable text, so an open and a closed segment highlight the same
// occurrences. A tag is one opaque character there, searched as its text in the cattool's search text
// (searchedTagText), so a hit inside a tag takes the number the cattool gives it; it is marked on its chip instead, as
// TagEntity's markSearch marks the tag label.
import SearchUtils from '../../../header/cattol/search/searchUtils'
import {visibleDoc} from '../../core/docModel'
import {visibleToDoc} from '../../core/layers'
import {chipTokens} from '../../core/tags'
import {OPAQUE_TAG, searchedTagText} from '../../../segmentView/segmentModel'

export const SEARCH_CHIP_CLASS = 'tag-search-match'
export const SEARCH_CHIP_CURRENT_CLASS = 'tag-search-current'

// The SearchUtils marks of one side and the visible text they refer to: each `OPAQUE_TAG` is searched as the text of
// the tag at its position in the cattool's search text.
const searchMarks = (state, segment, isSource) => {
  const doc = visibleDoc(state)
  const labelAt = new Map(
    chipTokens(state).map(({from, token}) => [
      from,
      searchedTagText(token.raw),
    ]),
  )
  const labels = []
  for (let i = 0; i < doc.text.length; i++) {
    if (doc.text[i] === OPAQUE_TAG) labels.push(labelAt.get(doc.pos[i]))
  }
  const marks = SearchUtils.getSearchMarks(
    doc.text,
    isSource,
    segment.sid,
    labels,
  )
  return {doc, marks}
}

/** The search layer ranges of the source (or, `isSource` false, the target) of `segment`; none out of search. */
export const searchRanges = (state, segment, isSource = true) => {
  if (!segment.inSearch) return []
  const {marks} = searchMarks(state, segment, isSource)
  return visibleToDoc(
    state,
    marks.filter((mark) => !mark.tag),
  ).map(({from, to, className}) => ({
    from,
    to,
    className,
    tagName: 'mark',
  }))
}

/**
 * The chips whose label holds the searched text of their side, as tag states `{from, to, className}`: Draft marks the
 * matching part of the tag text; here the chip label is marked. The chip holding the current hit is marked current
 * too, as markSearch colours its part as the current one.
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
  const {doc, marks} = searchMarks(state, segment, isSource)
  const current = marks.filter(
    (mark) => mark.tag && mark.className.includes('currSearchItem'),
  )
  return chipTokens(state).flatMap(({from, to, token}) => {
    const isCurrent = current.some(
      ({start, end}) => from >= doc.pos[start] && from <= doc.pos[end - 1],
    )
    if (!isCurrent && !(token.label && token.label.search(regex) >= 0))
      return []
    const className = isCurrent
      ? `${SEARCH_CHIP_CLASS} ${SEARCH_CHIP_CURRENT_CLASS}`
      : SEARCH_CHIP_CLASS
    return [{from, to, className}]
  })
}
