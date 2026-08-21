import React, {useRef} from 'react'
import {find} from 'lodash'

import LexiqaTooltipInfo from '../TooltipInfo/LexiqaTooltipInfo.component'
import LexiqaUtils from '../../../utils/lxq.main'
import Tooltip from '../../common/Tooltip'

const LexiqaHighlight = (props) => {
  const contentRef = useRef(null)

  const getWarning = () => {
    let {blockKey, start, end, warnings, isSource, sid} = props
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

  const {children, getUpdatedSegmentInfo} = props
  const {segmentOpened} = getUpdatedSegmentInfo()
  const warning = getWarning()

  return (
    warning && (
      <Tooltip
        stylePointerElement={{display: 'inline-block', position: 'relative'}}
        content={
          segmentOpened &&
          warning &&
          warning.messages && (
            <LexiqaTooltipInfo
              messages={warning.messages}
              onReplaceWord={props.replaceWordAt}
            />
          )
        }
        isInteractiveContent={true}
      >
        <div ref={contentRef} className="lexiqahighlight">
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

export default LexiqaHighlight
