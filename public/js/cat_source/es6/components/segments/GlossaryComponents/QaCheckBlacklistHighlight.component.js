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
  render() {
    const {children} = this.props
    const {showTooltip} = this.state
    return (
      <div className="blacklistItem">
        {showTooltip && <TooltipInfo text={'Blacklisted term'} />}
        <span
          onMouseEnter={() => this.tooltipToggle()}
          onMouseLeave={() => this.tooltipToggle()}
        >
          {children}
        </span>
      </div>
    )
  }
}

export default QaCheckBlacklistHighlight
