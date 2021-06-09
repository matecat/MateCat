import React from 'react'
import classnames from 'classnames'

import SegmentConstants from '../../../../constants/SegmentConstants'
import SegmentStore from '../../../../stores/SegmentStore'
import SegmentActions from '../../../../actions/SegmentActions'

export class BulkSelectionBar extends React.Component {
  state = {
    count: 0,
    segmentsArray: [],
    changingStatus: false,
  }

  countInBulkElements = (segments) => {
    let segmentsArray = this.state.segmentsArray
    if (segments && segments.size > 0) {
      segments.map((segment) => {
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

  setSegmentsinBulk = (segments) => {
    this.setState({
      count: segments.length,
      segmentsArray: segments,
    })
  }

  removeAll = () => {
    this.setState({
      count: 0,
      segmentsArray: [],
    })
  }

  toggleSegment = (sid) => {
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

  onClickBack = () => {
    SegmentActions.removeSegmentsOnBulk()
    this.setState({
      changingStatus: false,
    })
  }

  onClickBulk = () => {
    this.setState({
      changingStatus: true,
    })

    if (this.props.isReview) {
      SegmentActions.approveFilteredSegments(this.state.segmentsArray).then(
        () => {
          this.onClickBack()
          UI.reloadQualityReport()
        },
      )
    } else {
      SegmentActions.translateFilteredSegments(this.state.segmentsArray).then(
        () => {
          this.onClickBack()
        },
      )
    }
  }

  componentDidMount() {
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
    const {isReview} = this.props
    const {count, changingStatus} = this.state

    let buttonClass = classnames({
      'ui button approve-all-segments': true,
      'translated-all-bulked': !isReview,
      'approved-all-bulked': isReview,
      'approved-2nd-pass':
        config.secondRevisionsCount && config.revisionNumber === 2,
    })

    console.log(this.props)
    console.log(this.state)

    return count == 0 ? null : (
      <div className="bulk-approve-bar">
        <div className="bulk-back-info">
          <div className="bulk-back">
            <button className="ui button back-bulk" onClick={this.onClickBack}>
              {' '}
              <i className="icon-arrow-left2 icon" /> back
            </button>
          </div>

          {count === 1 ? (
            <div className="bulk-info">
              <b>{count} Segment selected</b>
            </div>
          ) : (
            <div className="bulk-info">
              <b>{count} Segments selected</b>
            </div>
          )}
        </div>

        {changingStatus ? (
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
              {isReview ? 'MARK AS APPROVED' : 'MARK AS TRANSLATED'}
            </button>
          </div>
        )}
      </div>
    )
  }
}
