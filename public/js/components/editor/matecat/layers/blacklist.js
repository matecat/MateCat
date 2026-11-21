// Blacklisted terms in the target (activateQaCheckBlacklist): the termbase translations the QA check flags as
// forbidden, matched with the cattool's glossary regex over the visible text, as the glossary terms of the source.
import {visibleDoc} from '../../core/docModel'
import {visibleToDoc} from '../../core/layers'
import {findTerms} from './glossary'

export const BLACKLIST_CLASS = 'blacklistItem'

/** The blacklist layer of the target of `segment` (`qaBlacklistGlossary`): only matches that name an entry. */
export const blacklistRanges = (state, {qaBlacklistGlossary, sid}) =>
  qaBlacklistGlossary?.length
    ? visibleToDoc(
        state,
        findTerms(visibleDoc(state).text, qaBlacklistGlossary),
      )
        .filter(({term}) => term)
        .map(({from, to, term}) => ({
          from,
          to,
          className: BLACKLIST_CLASS,
          data: {type: 'blacklist', sid, term},
        }))
    : []

/** What the tooltip of a blacklisted term says (QaCheckBlacklistHighlight). */
export const blacklistMessage = ({source, target}) =>
  source?.term
    ? `${target.term} is flagged as a forbidden translation for ${source.term}`
    : `${target.term} is flagged as a forbidden word`
