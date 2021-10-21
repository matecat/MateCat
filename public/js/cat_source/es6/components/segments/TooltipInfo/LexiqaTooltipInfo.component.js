import React, {Component} from 'react'
import _ from 'lodash'

import LXQ from '../../../utils/lxq.main'

class LexiqaTooltipInfo extends Component {
  ignoreError(message) {
    LXQ.ignoreError(message.error)
  }

  buildTooltipError = () => {
    const {messages} = this.props
    const suggestions = messages.filter((item) => item.type === 'suggestion')
    const errors = messages.filter((item) => item.type !== 'suggestion')

    const errorsHtml = errors.map((error, i) => (
      <div className="tooltip-error-container" key={i}>
        <span className="tooltip-error-category">{error.msg}</span>
        <div
          className="tooltip-error-ignore"
          onClick={() => this.ignoreError(error)}
        >
          <span className="icon-cancel-circle" />
          <span className="tooltip-error-ignore-text">Ignore</span>
        </div>
      </div>
    ))

    const suggestionsList = suggestions.map((suggestion, i) => (
      <li
        key={i}
        onClick={() =>
          this.replaceWord({
            newWord: suggestion.msg,
            start: suggestion.start,
            end: suggestion.end,
          })
        }
      >
        {suggestion.msg}
      </li>
    ))

    const suggestionsHtml = suggestions.length > 0 && (
      <div className="tooltip-suggestion-container">
        <ul>{suggestionsList}</ul>
      </div>
    )

    return (
      <>
        {errorsHtml}
        {suggestionsHtml}
      </>
    )
  }

  replaceWord = ({newWord, start, end}) => {
    this.props.onReplaceWord({newWord, start, end})
    //LXQ.redoHighlighting(segmentId, false)
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
