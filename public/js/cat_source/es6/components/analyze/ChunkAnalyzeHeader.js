import React from 'react'
import _ from 'lodash'

class ChunkAnalyzeHeader extends React.Component {
  constructor(props) {
    super(props)
    this.payableChange = false
    this.dataChange = {}
    this.containers = {}
  }

  checkWhatChanged() {
    if (this.total) {
      this.dataChange.TOTAL_PAYABLE = !this.total
        .get('TOTAL_PAYABLE')
        .equals(this.props.total.get('TOTAL_PAYABLE'))
      this.dataChange.NEW = !this.total
        .get('NEW')
        .equals(this.props.total.get('NEW'))
      this.dataChange.REPETITIONS = !this.total
        .get('REPETITIONS')
        .equals(this.props.total.get('REPETITIONS'))
      this.dataChange.INTERNAL_MATCHES = !this.total
        .get('INTERNAL_MATCHES')
        .equals(this.props.total.get('INTERNAL_MATCHES'))
      this.dataChange.TM_50_74 = !this.total
        .get('TM_50_74')
        .equals(this.props.total.get('TM_50_74'))
      this.dataChange.TM_75_84 = !this.total
        .get('TM_75_84')
        .equals(this.props.total.get('TM_75_84'))
      this.dataChange.TM_85_94 = !this.total
        .get('TM_85_94')
        .equals(this.props.total.get('TM_85_94'))
      this.dataChange.TM_95_99 = !this.total
        .get('TM_95_99')
        .equals(this.props.total.get('TM_95_99'))
      this.dataChange.TM_100 = !this.total
        .get('TM_100')
        .equals(this.props.total.get('TM_100'))
      this.dataChange.TM_100_PUBLIC = !this.total
        .get('TM_100_PUBLIC')
        .equals(this.props.total.get('TM_100_PUBLIC'))
      this.dataChange.ICE = !this.total
        .get('ICE')
        .equals(this.props.total.get('ICE'))
      this.dataChange.MT = !this.total
        .get('MT')
        .equals(this.props.total.get('MT'))
    }
    this.total = this.props.total
  }

  componentDidUpdate() {
    let self = this
    let changedData = _.pick(this.dataChange, function (item) {
      return item === true
    })
    if (_.size(changedData) > 0) {
      _.each(changedData, function (item, i) {
        self.containers[i].classList.add('updated-count')
        setTimeout(function () {
          self.containers[i].classList.remove('updated-count')
        }, 400)
      })
    }
  }

  shouldComponentUpdate() {
    return true
  }

  render() {
    let total = this.props.total
    this.checkWhatChanged()
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
            ({_.size(this.props.jobInfo.files)})
          </div>
        </div>
        <div className="single-analysis">
          <div className="single total">
            {this.props.jobInfo.total_raw_word_count_print}
          </div>

          <div
            className="single payable-words"
            ref={(container) => (this.containers['TOTAL_PAYABLE'] = container)}
          >
            {total.get('TOTAL_PAYABLE').get(1)}
          </div>

          <div
            className="single new"
            ref={(container) => (this.containers['NEW'] = container)}
          >
            {total.get('NEW').get(1)}
          </div>

          <div
            className="single repetition"
            ref={(container) => (this.containers['REPETITIONS'] = container)}
          >
            {total.get('REPETITIONS').get(1)}
          </div>

          <div
            className="single internal-matches"
            ref={(container) =>
              (this.containers['INTERNAL_MATCHES'] = container)
            }
          >
            {total.get('INTERNAL_MATCHES').get(1)}
          </div>

          <div
            className="single p-50-74"
            ref={(container) => (this.containers['TM_50_74'] = container)}
          >
            {total.get('TM_50_74').get(1)}
          </div>

          <div
            className="single p-75-84"
            ref={(container) => (this.containers['TM_75_84'] = container)}
          >
            {total.get('TM_75_84').get(1)}
          </div>

          <div
            className="single p-85-94"
            ref={(container) => (this.containers['TM_85_94'] = container)}
          >
            {total.get('TM_85_94').get(1)}
          </div>

          <div
            className="single p-95-99"
            ref={(container) => (this.containers['TM_95_99'] = container)}
          >
            {total.get('TM_95_99').get(1)}
          </div>

          <div
            className="single tm-100"
            ref={(container) => (this.containers['TM_100'] = container)}
          >
            {total.get('TM_100').get(1)}
          </div>

          <div
            className="single tm-public"
            ref={(container) => (this.containers['TM_100_PUBLIC'] = container)}
          >
            {total.get('TM_100_PUBLIC').get(1)}
          </div>

          <div
            className="single tm-context"
            ref={(container) => (this.containers['ICE'] = container)}
          >
            {total.get('ICE').get(1)}
          </div>

          <div
            className="single machine-translation"
            ref={(container) => (this.containers['MT'] = container)}
          >
            {total.get('MT').get(1)}
          </div>
        </div>
      </div>
    )
  }
}

export default ChunkAnalyzeHeader
