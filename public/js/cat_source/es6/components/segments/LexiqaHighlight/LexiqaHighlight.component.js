import React, {Component} from 'react'
import _ from 'lodash'

import LexiqaTooltipInfo from '../TooltipInfo/LexiqaTooltipInfo.component'
import LexiqaUtils from '../../../utils/lxq.main'

class LexiqaHighlight extends Component {
  constructor(props) {
    super(props)
    this.state = {
      showTooltip: false,
    }
    this.delayHideLoop = null
    this.delayShowLoop = null
  }

  clearTimer() {
    clearTimeout(this.delayHideLoop)
    clearTimeout(this.delayShowLoop)
  }

  toggleTooltip = (show = false) => {
    this.setState({
      showTooltip: show,
    })
  }

  showTooltip = (delayShow) => {
    this.clearTimer()
    if (delayShow) {
      this.delayShowLoop = setTimeout(
        () => this.toggleTooltip(true),
        parseInt(delayShow, 10),
      )
    } else {
      this.toggleTooltip(true)
    }
  }

  hideTooltip = (delayHide) => {
    this.clearTimer()

    if (delayHide) {
      this.delayHideLoop = setTimeout(
        this.toggleTooltip,
        parseInt(delayHide, 10),
      )
    } else {
      this.toggleTooltip()
    }
  }

  getWarning = () => {
    let {blockKey, start, end, warnings, isSource, sid} = this.props
    // Every block starts from offset 0, so we have to check warnings's blockKey
    let warning = _.find(
      warnings,
      (warn) =>
        warn.start === start && warn.end === end && warn.blockKey === blockKey,
    )
    if (warning && warning.myClass && warning.errorid) {
      warning.messages = LexiqaUtils.buildTooltipMessages(
        warning,
        sid,
        isSource,
      )
    }
    return warning
  }

  componentWillUnmount() {
    this.clearTimer()
  }

  render() {
    const {children, sid, getUpdatedSegmentInfo} = this.props
    const {showTooltip} = this.state
    const {segmentOpened} = getUpdatedSegmentInfo()
    const warning = this.getWarning()
    return warning ? (
      <div
        className="lexiqahighlight"
        onMouseEnter={() => this.showTooltip(300)}
        onMouseLeave={() => this.hideTooltip(300)}
      >
        {showTooltip && segmentOpened && warning && warning.messages && (
          <LexiqaTooltipInfo messages={warning.messages} />
        )}
        <span style={{backgroundColor: warning.messages ? warning.color : ''}}>
          {children}
        </span>
      </div>
    ) : null
  }
}

export default LexiqaHighlight
