import React, {Component, createRef} from 'react'
import SegmentActions from '../../../actions/SegmentActions'
import Tooltip from '../../common/Tooltip'
import {tagSignatures} from '../utils/DraftMatecatUtils/tagModel'
import TEXT_UTILS from '../../../utils/textUtils'

class QaCheckGlossaryHighlight extends Component {
  constructor(props) {
    super(props)
    this.contentRef = createRef()
  }
  getTermDetails = () => {
    const {contentState, missingTerms, start, end, blockKey, children} =
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

      const matches = missingTerms
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
            result = missingTerms.find(({matching_words: matchingWords}) =>
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
      const result = missingTerms.find(({matching_words: matchingWords}) =>
        matchingWords.find(
          (value) => value.toLowerCase() === text.toLowerCase(),
        ),
      )
      return result
    }
  }
  onClickTerm = () => {
    const {sid} = this.props
    const glossaryTerm = this.getTermDetails()
    //Call Segment footer Action
    if (glossaryTerm) {
      SegmentActions.highlightGlossaryTerm({
        sid,
        termId: glossaryTerm.term_id,
        type: 'check',
      })
    }
  }
  render() {
    const {children} = this.props

    return (
      <Tooltip
        stylePointerElement={{display: 'inline-block', position: 'relative'}}
        content="Glossary translation not in target"
      >
        <div ref={this.contentRef} className="qaCheckGlossaryItem">
          <span onClick={() => this.onClickTerm()}>{children}</span>
        </div>
      </Tooltip>
    )
  }
}

export default QaCheckGlossaryHighlight
