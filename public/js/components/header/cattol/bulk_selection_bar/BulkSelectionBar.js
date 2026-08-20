import React from 'react'
import classnames from 'classnames'
import SegmentActions from '../../../../actions/SegmentActions'
import SegmentConstants from '../../../../constants/SegmentConstants'
import SegmentStore from '../../../../stores/SegmentStore'
import CatToolActions from '../../../../actions/CatToolActions'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../../common/Button/Button'
import IconChevronLeft from '../../../../../img/icons/IconChevronLeft'
import IconTick from '../../../../../img/icons/IconTick'
import Checkmark from '../../../../../img/icons/Checkmark'

// The bulk endpoints reject with {response, errors}; a plain Error can also reach us if the request
// never left the browser.
const describeBulkFailure = (error) => {
  const message = error?.errors?.[0]?.message ?? error?.errors?.message
  if (message) return message
  if (error?.response?.status) return `server answered ${error.response.status}`
  return error?.message ?? 'the request failed'
}

const MAX_LISTED_SEGMENTS = 20

const listSegments = (segments) =>
  segments.length > MAX_LISTED_SEGMENTS
    ? `${segments.slice(0, MAX_LISTED_SEGMENTS).join(', ')} and ${
        segments.length - MAX_LISTED_SEGMENTS
      } more`
    : segments.join(', ')

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
    this.onBulkFailed = this.onBulkFailed.bind(this)
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

  onBulkFailed(error) {
    // Without this the bar keeps the spinner of a change that is not happening, and the selection is
    // silently left as it was. The selection is kept so the user can retry it, and the toast names
    // the segments that stayed behind and stays up until it is closed.
    this.setState({
      changingStatus: false,
    })
    const segments = this.state.segmentsArray
    CatToolActions.addNotification({
      title: 'The status of the selected segments was not changed',
      text: (
        <>
          <p>
            {segments.length} segments kept their status: the change to{' '}
            {this.props.isReview ? 'APPROVED' : 'TRANSLATED'} was refused
            because {describeBulkFailure(error)}.
          </p>
          <p>
            Job {config.id_job}
            {config.revisionNumber
              ? `, revision ${config.revisionNumber}`
              : ''}{' '}
            — segments {listSegments(segments)}
          </p>
        </>
      ),
      type: 'error',
      position: 'bl',
      autoDismiss: false,
    })
  }

  onClickBulk() {
    this.setState({
      changingStatus: true,
    })
    if (this.props.isReview) {
      SegmentActions.approveFilteredSegments(this.state.segmentsArray)
        .then(() => {
          this.onClickBack()
          CatToolActions.onRender({segmentToOpen: this.state.segmentsArray[0]})
          CatToolActions.reloadQualityReport()
        })
        .catch((error) => this.onBulkFailed(error))
    } else {
      SegmentActions.translateFilteredSegments(this.state.segmentsArray)
        .then(() => {
          CatToolActions.onRender({segmentToOpen: this.state.segmentsArray[0]})
          this.onClickBack()
        })
        .catch((error) => this.onBulkFailed(error))
    }
    // SegmentActions.closeSegment(SegmentStore.getCurrentSegmentId());
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
      'approve-all-segments': true,
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
            <Button
              mode={BUTTON_MODE.GHOST}
              size={BUTTON_SIZE.SMALL}
              onClick={this.onClickBack}
            >
              <IconChevronLeft size={16} /> back
            </Button>
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
            <Button
              className={`mark-button ${buttonClass}`}
              type={BUTTON_TYPE.PRIMARY}
              mode={BUTTON_MODE.OUTLINE}
              onClick={this.onClickBulk}
            >
              <div>
                <Checkmark size={16} />
              </div>
              {this.props.isReview ? 'MARK AS APPROVED' : 'MARK AS TRANSLATED'}
            </Button>
          </div>
        )}
      </div>
    ) : null
  }
}

export default BulkSelectionBar
