import React from 'react'
import {size} from 'lodash'

class ChunkAnalyzeHeader extends React.Component {
  constructor(props) {
    super(props)
    this.dataChange = {}
    this.containers = {}
  }

  shouldComponentUpdate() {
    return true
  }

  render() {
    let total = this.props.total
    let id =
      this.props.chunksSize > 1 ? (
        <div className="job-id">{'Chunk ' + this.props.index}</div>
      ) : (
        ''
      )
    return (
      <div className="chunk sixteen wide column pad-right-10">
        <div className="left-box">
          {id}
          <div className="file-details" onClick={this.props.showFiles}>
            File <span className="details">details </span>
          </div>
          <div className="f-details-number">
            ({size(this.props.jobInfo.files)})
          </div>
        </div>
        <div className="single-analysis">
          <div className="single total">{this.props.jobInfo.total_raw}</div>

          <div className="single payable-words">
            {this.props.jobInfo.total_equivalent}
          </div>

          <div className="single new">
            {total.find((item) => item.type === 'new').equivalent}
          </div>

          <div className="single repetition">
            {total.find((item) => item.type === 'repetitions').equivalent}
          </div>

          <div className="single internal-matches">
            {total.find((item) => item.type === 'internal').equivalent}
          </div>

          <div className="single p-50-74">
            {total.find((item) => item.type === '50_74').equivalent}
          </div>

          <div className="single p-75-84">
            {total.find((item) => item.type === '75_84').equivalent}
          </div>

          <div className="single p-85-94">
            {total.find((item) => item.type === '85_94').equivalent}
          </div>

          <div className="single p-95-99">
            {total.find((item) => item.type === '95_99').equivalent}
          </div>

          <div className="single tm-100">
            {total.find((item) => item.type === '100').equivalent}
          </div>

          <div className="single tm-public">
            {total.find((item) => item.type === '100_public').equivalent}
          </div>

          <div className="single tm-context">
            {total.find((item) => item.type === 'ice').equivalent}
          </div>

          <div className="single machine-translation">
            {total.find((item) => item.type === 'MT').equivalent}
          </div>
        </div>
      </div>
    )
  }
}

export default ChunkAnalyzeHeader
