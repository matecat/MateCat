import React from 'react'
import {each} from 'lodash/collection'
import {UNIT_COUNT} from '../../constants/Constants'

class JobAnalyzeHeader extends React.Component {
  constructor(props) {
    super(props)
  }

  calculateWords() {
    this.total = 0
    this.payable = 0
    let self = this

    each(this.props.jobInfo.chunks, function (chunk) {
      self.payable = self.payable + chunk.total_equivalent
      self.total = self.total + chunk.total_raw
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
          <div className="source-box">{this.props.jobInfo.source_name}</div>
          <div className="in-to">
            <i className="icon-chevron-right icon" />
          </div>
          <div className="target-box">{this.props.jobInfo.target_name}</div>
        </div>
        {/*<div className="job-not-payable">*/}
        {/*<span id="raw-words">{parseInt(this.total)}</span> Total words*/}
        {/*</div>*/}
        <div className="job-payable">
          <span id="words">{parseInt(this.payable)}</span>
          {this.props.jobInfo.count_unit === UNIT_COUNT.WORDS
            ? ' Matecat Weighted words'
            : ' Matecat weighted characters'}
        </div>
      </div>
    )
  }
}

export default JobAnalyzeHeader
