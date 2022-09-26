import React, {Component} from 'react'
import TooltipInfo from '../TooltipInfo/TooltipInfo.component'
import SegmentActions from '../../../actions/SegmentActions'

class GlossaryHighlight extends Component {
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
    const {glossary, children, sid} = this.props
    const text = children[0].props.text
    const glossaryTerm = glossary.find(({matching_words: matchingWords}) =>
      matchingWords.find((value) => value === text),
    )
    //Call Segment footer Action
    SegmentActions.highlightGlossaryTerm({sid, termId: glossaryTerm.term_id})
  }
  render() {
    const {children} = this.props
    const {showTooltip} = this.state
    return (
      <div className={'glossaryItem'}>
        {showTooltip && <TooltipInfo text={'Glossary term'} />}
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

export default GlossaryHighlight
