import React, {Component} from 'react'
import TooltipInfo from '../TooltipInfo/TooltipInfo.component'

class QaCheckBlacklistHighlight extends Component {
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
  render() {
    const {children} = this.props
    const {showTooltip} = this.state

    const text = children[0].props.text
    const term = this.props.blackListedTerms.find(
      ({matching_words: matchingWords}) =>
        matchingWords.find(
          (value) => value.toLowerCase() === text.toLowerCase(),
        ),
    )
    const {source, target} = term

    return (
      <div className="blacklistItem">
        {showTooltip && (
          <TooltipInfo
            text={
              source.term
                ? `${target.term} is flagged as a forbidden translation for ${source.term}`
                : `${target.term} is flagged as a forbidden word`
            }
          />
        )}
        <span
          onMouseEnter={() => this.tooltipToggle()}
          onMouseLeave={() => this.removeTooltip()}
        >
          {children}
        </span>
      </div>
    )
  }
}

export default QaCheckBlacklistHighlight
