import React, {Component} from 'react'
import SegmentActions from '../../../actions/SegmentActions'
import TooltipInfo from '../TooltipInfo/TooltipInfo.component'

class QaCheckBlacklistHighlight extends Component {
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
    const {blackListedTerms, children, sid} = this.props
    const text = children[0].props.text
    const glossaryTerm = blackListedTerms.find(
      ({matching_words: matchingWords}) =>
        matchingWords.find((value) => value === text),
    )
    //Call Segment footer Action
    SegmentActions.highlightGlossaryTerm({
      sid,
      termId: glossaryTerm.term_id,
      type: 'blacklist',
      isTarget: true,
    })
  }
  render() {
    const {children} = this.props
    const {showTooltip} = this.state
    return (
      <div className="blacklistItem">
        {showTooltip && <TooltipInfo text={'Blacklisted term'} />}
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

export default QaCheckBlacklistHighlight
