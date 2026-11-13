// Glossary terms (activateGlossary) and termbase terms missing in the target (activateQaCheckGlossary), matched with
// the cattool's glossary regex over the visible text. Each tag is one opaque character there, so a term never runs
// across a tag; a match touching one is split around it, as Draft splits it around an entity.
import TextUtils from '../../../../utils/textUtils'
import {OPAQUE_TAG} from '../../../segmentView/segmentModel'
import {visibleDoc} from '../../core/docModel'
import {visibleToDoc} from '../../core/layers'

export const GLOSSARY_CLASS = 'glossaryItem'
export const QA_GLOSSARY_CLASS = 'qaCheckGlossaryItem'

// Longest first among words sharing a prefix (['Guest favorite', 'guest']), as the Draft decorators sort them.
const sortedWords = (terms) =>
  [...new Set(terms.flatMap(({matching_words: words = []}) => words))].sort(
    (a, b) => (a.toLowerCase() < b.toLowerCase() ? 1 : -1),
  )

const termFor = (terms, words) =>
  terms.find(({matching_words: matchingWords = []}) =>
    matchingWords.some((value) => value.toLowerCase() === words.toLowerCase()),
  )

/**
 * Where `terms` occur in `text`, as `{start, end, term}`; `term` is the entry whose matching words include the match.
 * Exported for the tests: the regex and its fallbacks (no lookbehind, CJK) are the cattool's.
 */
export const findTerms = (text, terms) => {
  const words = sortedWords(terms)
  if (!words.length) return []
  const {regex, regexCallback} = TextUtils.getGlossaryMatchRegex(words)
  if (!regex || !regexCallback) return []
  const block = {
    getText: () => text,
    getEntityAt: (i) => (text[i] === OPAQUE_TAG ? 'tag' : null),
  }
  const out = []
  regexCallback(regex, block, (start, end) => {
    if (end <= start) return
    out.push({start, end, term: termFor(terms, text.slice(start, end))})
  })
  return out
}

const glossaryLayer = (state, terms, className, type, sid) =>
  visibleToDoc(state, findTerms(visibleDoc(state).text, terms)).map(
    ({from, to, term}) => ({
      from,
      to,
      className,
      data: {type, sid, termId: term?.term_id},
    }),
  )

/** Glossary hits, blacklisted entries left out (they belong to the target). */
export const glossaryRanges = (state, {glossary, sid}) =>
  glossary?.length
    ? glossaryLayer(
        state,
        glossary.filter(
          ({isBlacklist, missingTerm}) => !isBlacklist && !missingTerm,
        ),
        GLOSSARY_CLASS,
        'glossary',
        sid,
      )
    : []

/** Source terms whose termbase translation is missing in the target. */
export const qaCheckGlossaryRanges = (state, {glossary, sid}) => {
  const missing = (glossary ?? []).filter(({missingTerm}) => missingTerm)
  return missing.length
    ? glossaryLayer(state, missing, QA_GLOSSARY_CLASS, 'check', sid)
    : []
}
