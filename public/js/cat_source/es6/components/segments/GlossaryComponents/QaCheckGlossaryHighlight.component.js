import React, {Component} from 'react'
import TooltipInfo from '../TooltipInfo/TooltipInfo.component'
import SegmentActions from '../../../actions/SegmentActions'

class QaCheckGlossaryHighlight extends Component {
  constructor(props) {
    super(props)
    this.state = {
      showTooltip: false,
    }
    this.tooltipDelay
  }
  tooltipToggle = () => {
    // this will trigger a rerender in the main Editor Component
    clearTimeout(this.tooltipDelay)
    this.tooltipDelay = setTimeout(() => {
      this.setState({
        showTooltip: true,
      })
    }, 400)
  }
  removeTooltip = () => {
    clearTimeout(this.tooltipDelay)
    this.setState({
      showTooltip: false,
    })
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
    const {showTooltip} = this.state
    return (
      <div className="qaCheckGlossaryItem">
        {showTooltip && (
          <TooltipInfo text={'Glossary translation not in target'} />
        )}
        <span
          onMouseEnter={() => this.tooltipToggle()}
          onMouseLeave={() => this.removeTooltip()}
          onClick={() => this.onClickTerm()}
        >
          {children}
        </span>
      </div>
    )
  }
}

export default QaCheckGlossaryHighlight
