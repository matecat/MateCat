import React, {Component, createRef} from 'react'
import Tooltip from '../../common/Tooltip'

class QaCheckBlacklistHighlight extends Component {
  constructor(props) {
    super(props)
    this.contentRef = createRef()
  }
  render() {
    const {children} = this.props

    const text = children[0].props.text
    const term = this.props.blackListedTerms.find(
      ({matching_words: matchingWords}) =>
        matchingWords.find(
          (value) => value.toLowerCase() === text.toLowerCase(),
        ),
    )
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
