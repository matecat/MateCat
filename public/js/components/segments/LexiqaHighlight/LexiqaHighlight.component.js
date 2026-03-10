import React, {Component, createRef} from 'react'
import {find} from 'lodash'

import LexiqaTooltipInfo from '../TooltipInfo/LexiqaTooltipInfo.component'
import LexiqaUtils from '../../../utils/lxq.main'
import Tooltip from '../../common/Tooltip'

class LexiqaHighlight extends Component {
  constructor(props) {
    super(props)
    this.contentRef = createRef()
  }

  getWarning = () => {
    let {blockKey, start, end, warnings, isSource, sid} = this.props
    // Every block starts from offset 0, so we have to check warnings's blockKey
    let warning = find(
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

  render() {
    const {children, getUpdatedSegmentInfo} = this.props
    const {segmentOpened} = getUpdatedSegmentInfo()
    const warning = this.getWarning()

    return (
      warning && (
        <Tooltip
          stylePointerElement={{display: 'inline-block', position: 'relative'}}
          content={
            segmentOpened &&
            warning &&
            warning.messages && (
              <LexiqaTooltipInfo messages={warning.messages} />
            )
          }
          isInteractiveContent={true}
        >
          <div ref={this.contentRef} className="lexiqahighlight">
            <span
              style={{backgroundColor: warning.messages ? warning.color : ''}}
            >
              {children}
            </span>
          </div>
        </Tooltip>
      )
    )
  }
}

export default LexiqaHighlight
