import React from 'react'
import _ from 'lodash'

class ChunkAnalyzeFile extends React.Component {
  constructor(props) {
    super(props)
    this.dataChange = {}
    this.containers = {}
  }

  checkWhatChanged() {
    if (this.file) {
      this.dataChange.TOTAL_PAYABLE = !this.file
        .get('TOTAL_PAYABLE')
        .equals(this.props.file.get('TOTAL_PAYABLE'))
      this.dataChange.NEW = !this.file
        .get('NEW')
        .equals(this.props.file.get('NEW'))
      this.dataChange.REPETITIONS = !this.file
        .get('REPETITIONS')
        .equals(this.props.file.get('REPETITIONS'))
      this.dataChange.INTERNAL_MATCHES = !this.file
        .get('INTERNAL_MATCHES')
        .equals(this.props.file.get('INTERNAL_MATCHES'))
      this.dataChange.TM_50_74 = !this.file
        .get('TM_50_74')
        .equals(this.props.file.get('TM_50_74'))
      this.dataChange.TM_75_84 = !this.file
        .get('TM_75_84')
        .equals(this.props.file.get('TM_75_84'))
      this.dataChange.TM_85_94 = !this.file
        .get('TM_85_94')
        .equals(this.props.file.get('TM_85_94'))
      this.dataChange.TM_95_99 = !this.file
        .get('TM_95_99')
        .equals(this.props.file.get('TM_95_99'))
      this.dataChange.TM_100 = !this.file
        .get('TM_100')
        .equals(this.props.file.get('TM_100'))
      this.dataChange.TM_100_PUBLIC = !this.file
        .get('TM_100_PUBLIC')
        .equals(this.props.file.get('TM_100_PUBLIC'))
      this.dataChange.ICE = !this.file
        .get('ICE')
        .equals(this.props.file.get('ICE'))
      this.dataChange.MT = !this.file
        .get('MT')
        .equals(this.props.file.get('MT'))
    }
    this.file = this.props.file
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
    var file = this.props.file.toJS()
    this.checkWhatChanged()
    return (
      <div className="chunk-detail sixteen wide column pad-right-10">
        <div className="left-box">
          <i className="icon-make-group icon"></i>
          <div className="file-title-details">
            {this.props.fileInfo.filename}
            {/*(<span className="f-details-number">2</span>)*/}
          </div>
        </div>
        <div className="single-analysis">
          <div className="single total">
            {this.props.fileInfo.file_raw_word_count}
          </div>
          <div
            className="single payable-words"
            ref={(container) => (this.containers['TOTAL_PAYABLE'] = container)}
          >
            {file.TOTAL_PAYABLE[1]}
          </div>
          <div
            className="single new"
            ref={(container) => (this.containers['NEW'] = container)}
          >
            {file.NEW[1]}
          </div>
          <div
            className="single repetition"
            ref={(container) => (this.containers['REPETITIONS'] = container)}
          >
            {file.REPETITIONS[1]}
          </div>
          <div
            className="single internal-matches"
            ref={(container) =>
              (this.containers['INTERNAL_MATCHES'] = container)
            }
          >
            {file.INTERNAL_MATCHES[1]}
          </div>
          <div
            className="single p-50-74"
            ref={(container) => (this.containers['TM_50_74'] = container)}
          >
            {file.TM_50_74[1]}
          </div>
          <div
            className="single p-75-84"
            ref={(container) => (this.containers['TM_75_84'] = container)}
          >
            {file.TM_75_84[1]}
          </div>
          <div
            className="single p-84-94"
            ref={(container) => (this.containers['TM_85_94'] = container)}
          >
            {file.TM_85_94[1]}
          </div>
          <div
            className="single p-95-99"
            ref={(container) => (this.containers['TM_95_99'] = container)}
          >
            {file.TM_95_99[1]}
          </div>
          <div
            className="single tm-100"
            ref={(container) => (this.containers['TM_100'] = container)}
          >
            {file.TM_100[1]}
          </div>
          <div
            className="single tm-public"
            ref={(container) => (this.containers['TM_100_PUBLIC'] = container)}
          >
            {file.TM_100_PUBLIC[1]}
          </div>
          <div
            className="single tm-context"
            ref={(container) => (this.containers['ICE'] = container)}
          >
            {file.ICE[1]}
          </div>
          <div
            className="single machine-translation"
            ref={(container) => (this.containers['MT'] = container)}
          >
            {file.MT[1]}
          </div>
        </div>
      </div>
    )
  }
}

export default ChunkAnalyzeFile
