import React, {Component, createRef} from 'react'
import SegmentActions from '../../../actions/SegmentActions'
import Tooltip from '../../common/Tooltip'

class QaCheckGlossaryHighlight extends Component {
  constructor(props) {
    super(props)
    this.contentRef = createRef()
  }
  onClickTerm = () => {
    const {missingTerms, children, sid} = this.props
    const text = children[0].props.text.trim()
    const glossaryTerm = missingTerms.find(({matching_words: matchingWords}) =>
      matchingWords.find((value) => value.toLowerCase() === text.toLowerCase()),
    )
    //Call Segment footer Action
    SegmentActions.highlightGlossaryTerm({
      sid,
      termId: glossaryTerm.term_id,
      type: 'check',
    })
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
