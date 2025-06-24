import React, {Component} from 'react'
import {each} from 'lodash'

import LXQ from '../../../utils/lxq.main'

class LexiqaTooltipInfo extends Component {
  ignoreError(message) {
    if (message.error) {
      LXQ.ignoreError(message.error)
    }
  }

  buildTooltipError = () => {
    let messages = this.props.messages
    let html = []
    each(messages, (message, i) => {
      html.push(
        <div className="tooltip-error-container" key={i}>
          <span className="tooltip-error-category">{message.msg}</span>
          <div
            className="tooltip-error-ignore"
            onClick={() => this.ignoreError(message)}
          >
            <span className="icon-cancel-circle" />
            <span className="tooltip-error-ignore-text">Ignore</span>
          </div>
        </div>,
      )
    })
    return html
  }

  render() {
    const html = this.buildTooltipError()
    return (
      <div className="lexiqa-tooltip">
        <div className="tooltip-error-wrapper">{html}</div>
      </div>
    )
  }
}

export default LexiqaTooltipInfo
