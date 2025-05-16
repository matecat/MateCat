import React, {Component, createRef} from 'react'
import Tooltip from '../../common/Tooltip'
import {tagSignatures} from '../utils/DraftMatecatUtils/tagModel'
import TEXT_UTILS from '../../../utils/textUtils'

class QaCheckBlacklistHighlight extends Component {
  constructor(props) {
    super(props)
    this.contentRef = createRef()
  }
  getTermDetails = () => {
    const {contentState, blackListedTerms, start, end, blockKey, children} =
      this.props
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
              new RegExp('​' + tagSignatures.space.placeholder + '​'),
              ' ',
            )

          if (startB === startAbsolute || endB === endAbsolute) {
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
  render() {
    const {children} = this.props

    const term = this.getTermDetails()

    const {source, target} = term

    return (
      <Tooltip
        stylePointerElement={{display: 'inline-block', position: 'relative'}}
        content={
          source.term
            ? `${target.term} is flagged as a forbidden translation for ${source.term}`
            : `${target.term} is flagged as a forbidden word`
        }
      >
        <div ref={this.contentRef} className="blacklistItem">
          <span>{children}</span>
        </div>
      </Tooltip>
    )
  }
}

export default QaCheckBlacklistHighlight
