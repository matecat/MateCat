import React, {Component} from 'react'
import TooltipInfo from '../TooltipInfo/TooltipInfo.component'

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
    const {glossary, children} = this.props
    const text = children[0].props.text
    const glossaryTerm = glossary.find((gl) => gl.matching_words[0] === text)
    //Call Segment footer Action
    console.log('Highlight glossary item', glossaryTerm.term_id)
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
