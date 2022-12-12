import React from 'react'
import _ from 'lodash'

class JobAnalyzeHeader extends React.Component {
  constructor(props) {
    super(props)
  }

  calculateWords() {
    this.total = 0
    this.payable = 0
    let self = this
    this.props.totals.forEach(function (chunk) {
      self.payable = self.payable + chunk.get('TOTAL_PAYABLE').get(0)
    })

    _.each(this.props.jobInfo.chunks, function (chunk) {
      self.total = self.total + chunk.total_raw_word_count
    })
  }

  shouldComponentUpdate() {
    return true
  }

  render() {
    this.calculateWords()
    return (
      <div className="head-chunk sixteen wide column pad-right-10 shadow-1">
        <div className="source-target">
          <div className="source-box">{this.props.jobInfo.source}</div>
          <div className="in-to">
            <i className="icon-chevron-right icon" />
          </div>
          <div className="target-box">{this.props.jobInfo.target}</div>
        </div>
        {/*<div className="job-not-payable">*/}
        {/*<span id="raw-words">{parseInt(this.total)}</span> Total words*/}
        {/*</div>*/}
        <div className="job-payable">
          <span id="words">{parseInt(this.payable)}</span>
          {!config.isCJK
            ? ' Matecat Weighted words'
            : ' Matecat weighted characters'}
        </div>
      </div>
    )
  }
}

export default JobAnalyzeHeader
