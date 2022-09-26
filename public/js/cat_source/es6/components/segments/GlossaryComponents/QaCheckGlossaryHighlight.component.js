import React, {Component} from 'react'
import TooltipInfo from '../TooltipInfo/TooltipInfo.component'
import SegmentActions from '../../../actions/SegmentActions'

class QaCheckGlossaryHighlight extends Component {
  constructor(props) {
    super(props)
    this.state = {
      showTooltip: false,
    }
  }
  tooltipToggle = () => {
    // this will trigger a rerender in the main Editor Component
    const {showTooltip} = this.state
    this.setState({
      showTooltip: !showTooltip,
    })
  }
  onClickTerm = () => {
    const {missingTerms, children, sid} = this.props
    const text = children[0].props.text
    const glossaryTerm = missingTerms.find(({matching_words: matchingWords}) =>
      matchingWords.find((value) => value === text),
    )
    //Call Segment footer Action
    SegmentActions.highlightGlossaryTerm({sid, termId: glossaryTerm.term_id})
  }
  render() {
    const {children} = this.props
    const {showTooltip} = this.state
    return (
      <div className="qaCheckGlossaryItem">
        {showTooltip && <TooltipInfo text={'Unused glossary term'} />}
        <span
          onMouseEnter={() => this.tooltipToggle()}
          onMouseLeave={() => this.tooltipToggle()}
          onClick={() => this.onClickTerm()}
        >
          {children}
        </span>
      </div>
    )
  }
}

export default QaCheckGlossaryHighlight
