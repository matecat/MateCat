import React from 'react'
import classnames from 'classnames'
import SegmentActions from '../../../../actions/SegmentActions'
import SegmentConstants from '../../../../constants/SegmentConstants'
import SegmentStore from '../../../../stores/SegmentStore'
import CatToolActions from '../../../../actions/CatToolActions'

class BulkSelectionBar extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      count: 0,
      segmentsArray: [],
      changingStatus: false,
    }

    this.countInBulkElements = this.countInBulkElements.bind(this)
    this.setSegmentsinBulk = this.setSegmentsinBulk.bind(this)
    this.toggleSegment = this.toggleSegment.bind(this)
    this.removeAll = this.removeAll.bind(this)
    this.onClickBulk = this.onClickBulk.bind(this)
    this.onClickBack = this.onClickBack.bind(this)
  }

  countInBulkElements(segments) {
    let segmentsArray = this.state.segmentsArray
    if (segments && segments.size > 0) {
      segments.map(function (segment) {
        let index = segmentsArray.indexOf(segment.get('sid'))
        if (segment.get('inBulk') && index === -1) {
          segmentsArray.push(segment.get('sid'))
        } else if (!segment.get('inBulk') && index > -1) {
          segmentsArray.splice(index, 1)
        }
      })
    }
    this.setState({
      count: segmentsArray.length,
      segmentsArray: segmentsArray,
    })
  }
  setSegmentsinBulk(segments) {
    let segmentsArray = segments

    this.setState({
      count: segmentsArray.length,
      segmentsArray: segmentsArray,
    })
  }
  removeAll() {
    this.setState({
      count: 0,
      segmentsArray: [],
    })
  }
  toggleSegment(sid) {
    let index = this.state.segmentsArray.indexOf(sid)
    let array = this.state.segmentsArray.slice(0)
    if (index > -1) {
      array.splice(index, 1)
    } else {
      array.push(sid)
    }
    this.setState({
      count: array.length,
      segmentsArray: array,
    })
  }
  onClickBack() {
    SegmentActions.removeSegmentsOnBulk()
    this.setState({
      changingStatus: false,
    })
  }

  onClickBulk() {
    this.setState({
      changingStatus: true,
    })
    if (this.props.isReview) {
      SegmentActions.approveFilteredSegments(this.state.segmentsArray).then(
        () => {
          this.onClickBack()
          CatToolActions.onRender({segmentToOpen: this.state.segmentsArray[0]})
          CatToolActions.reloadQualityReport()
        },
      )
    } else {
      SegmentActions.translateFilteredSegments(this.state.segmentsArray).then(
        () => {
          CatToolActions.onRender({segmentToOpen: this.state.segmentsArray[0]})
          this.onClickBack()
        },
      )
    }
    // SegmentActions.closeSegment(UI.currentSegmentId);
  }

  componentDidMount() {
    // SegmentStore.addListener(SegmentConstants.RENDER_SEGMENTS, this.countInBulkElements);
    SegmentStore.addListener(
      SegmentConstants.TOGGLE_SEGMENT_ON_BULK,
      this.toggleSegment,
    )
    SegmentStore.addListener(
      SegmentConstants.REMOVE_SEGMENTS_ON_BULK,
      this.removeAll,
    )
    SegmentStore.addListener(
      SegmentConstants.SET_BULK_SELECTION_SEGMENTS,
      this.setSegmentsinBulk,
    )
  }

  componentWillUnmount() {
    // SegmentStore.removeListener(SegmentConstants.RENDER_SEGMENTS, this.countInBulkElements);
    SegmentStore.removeListener(
      SegmentConstants.TOGGLE_SEGMENT_ON_BULK,
      this.toggleSegment,
    )
    SegmentStore.removeListener(
      SegmentConstants.REMOVE_SEGMENTS_ON_BULK,
      this.removeAll,
    )
    SegmentStore.removeListener(
      SegmentConstants.SET_BULK_SELECTION_SEGMENTS,
      this.setSegmentsinBulk,
    )
  }

  render() {
    let buttonClass = classnames({
      'ui button approve-all-segments': true,
      'translated-all-bulked': !this.props.isReview,
      'approved-all-bulked': this.props.isReview,
      'approved-2nd-pass':
        config.secondRevisionsCount &&
        config.revisionNumber &&
        config.revisionNumber === 2,
    })
    return this.state.count > 0 ? (
      <div className="bulk-approve-bar">
        <div className="bulk-back-info">
          <div className="bulk-back">
            <button className="ui button back-bulk" onClick={this.onClickBack}>
              {' '}
              <i className="icon-arrow-left2 icon" /> back
            </button>
          </div>
          {this.state.count === 1 ? (
            <div className="bulk-info">
              <b>{this.state.count} Segment selected</b>
            </div>
          ) : (
            <div className="bulk-info">
              <b>{this.state.count} Segments selected</b>
            </div>
          )}
        </div>

        {this.state.changingStatus ? (
          <div className="bulk-activity-icons">
            <div className="label-filters labl">
              Applying changes
              <div className="loader" />
            </div>
          </div>
        ) : (
          <div className="bulk-activity-icons">
            <button className={buttonClass} onClick={this.onClickBulk}>
              <i className="icon-checkmark5 icon" />{' '}
              {this.props.isReview ? 'MARK AS APPROVED' : 'MARK AS TRANSLATED'}
            </button>
          </div>
        )}
      </div>
    ) : null
  }
}

export default BulkSelectionBar
