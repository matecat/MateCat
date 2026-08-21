import React, {useRef} from 'react'
import Tooltip from '../../common/Tooltip'
import {tagSignatures} from '../utils/DraftMatecatUtils/tagModel'
import TEXT_UTILS from '../../../utils/textUtils'

const QaCheckBlacklistHighlight = (props) => {
  const contentRef = useRef(null)

  const getTermDetails = () => {
    const {contentState, blackListedTerms, start, end, blockKey, children} =
      props
    if (tagSignatures.space) {
      const getBlocksBefore = (key) => {
        const blocks = []

        const iterate = (key) => {
          const block = contentState.getBlockBefore(key)
          if (block) {
            blocks.unshift(block)
            iterate(block.getKey())
          }
        }

        iterate(key)

        return blocks
      }

      const differenceIndex = getBlocksBefore(blockKey).reduce((acc, cur) => {
        return acc + cur.getLength() + 1
      }, 0)

      const startAbsolute = start + differenceIndex
      const endAbsolute = end + differenceIndex

      const fakeContentBlock = {
        getText: () => contentState.getPlainText(),
        getEntityAt: () => false,
      }

      const matches = blackListedTerms
        .reduce(
          (acc, {matching_words}) => [
            ...acc,
            ...matching_words.map((words) =>
              tagSignatures.space
                ? words.replace(
                    tagSignatures.space.regex,
                    '​' + tagSignatures.space.placeholder + '​',
                  )
                : words,
            ),
          ],
          [],
        )
        .sort((a, b) => (a.toLowerCase() < b.toLowerCase() ? 1 : -1)) // Order words alphabetically descending to prioritize composite terms ex. ['Guest favorite', 'guest']

      if (matches.length) {
        const {regex, regexCallback} = TEXT_UTILS.getGlossaryMatchRegex(matches)
        let result
        const callback = (startB, endB) => {
          const words = fakeContentBlock
            .getText()
            .substring(startB, endB)
            .replace(
              new RegExp('​' + tagSignatures.space.placeholder + '​', 'g'),
              ' ',
            )

          if (
            startB === startAbsolute ||
            endB === endAbsolute ||
            (startAbsolute > startB && endAbsolute < endB)
          ) {
            result = blackListedTerms.find(({matching_words: matchingWords}) =>
              matchingWords.find(
                (value) => value.toLowerCase() === words.toLowerCase(),
              ),
            )
          }
        }
        regexCallback(regex, fakeContentBlock, callback)
        return result
      }
    } else {
      const text = children[0].props.text.trim()
      const result = blackListedTerms.find(({matching_words: matchingWords}) =>
        matchingWords.find(
          (value) => value.toLowerCase() === text.toLowerCase(),
        ),
      )
      return result
    }
  }

  const {children} = props

  const term = getTermDetails()

  const {source, target} = term || {}

  return term ? (
    <Tooltip
      stylePointerElement={{display: 'inline-block', position: 'relative'}}
      content={
        source.term
          ? `${target.term} is flagged as a forbidden translation for ${source.term}`
          : `${target.term} is flagged as a forbidden word`
      }
    >
      <div ref={contentRef} className="blacklistItem">
        <span>{children}</span>
      </div>
    </Tooltip>
  ) : null
}

export default QaCheckBlacklistHighlight
