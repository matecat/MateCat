/**
 * React Component .

 */
import React from 'react'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'

class SegmentHeader extends React.PureComponent {
  constructor(props) {
    super(props)
    this.state = {
      autopropagated: this.props.autopropagated,
      percentage: '',
      classname: '',
      createdBy: '',
      visible: false,
    }
    this.changePercentuage = this.changePercentuage.bind(this)
    this.hideHeader = this.hideHeader.bind(this)
  }

  changePercentuage(sid, perc, className, createdBy) {
    if (this.props.sid == sid) {
      this.setState({
        percentage: perc,
        classname: className,
        createdBy: createdBy,
        visible: true,
        autopropagated: false,
      })
    }
  }

  hideHeader(sid) {
    if (this.props.sid == sid) {
      this.setState({
        autopropagated: false,
        visible: false,
      })
    }
  }

  componentDidMount() {
    SegmentStore.addListener(
      SegmentConstants.SET_SEGMENT_HEADER,
      this.changePercentuage,
    )
    SegmentStore.addListener(
      SegmentConstants.HIDE_SEGMENT_HEADER,
      this.hideHeader,
    )
  }

  componentWillUnmount() {
    SegmentStore.removeListener(
      SegmentConstants.SET_SEGMENT_HEADER,
      this.changePercentuage,
    )
    SegmentStore.removeListener(
      SegmentConstants.HIDE_SEGMENT_HEADER,
      this.hideHeader,
    )
  }

  static getDerivedStateFromProps(props, state) {
    if (props.autopropagated) {
      return {
        autopropagated: true,
      }
    }
    return null
  }

  allowHTML(string) {
    return {__html: string}
  }

  render() {
    let autopropagatedHtml
    let percentageHtml
    const {repetition, splitted, segmentOpened, sid, saving} = this.props
    const {autopropagated, visible, percentage, createdBy, classname} =
      this.state
    if (autopropagated && !splitted) {
      autopropagatedHtml = <span className="repetition">Autopropagated</span>
    } else if (repetition && !splitted) {
      autopropagatedHtml = <span className="repetition">Repetition</span>
    }
    if (visible && percentage != '') {
      percentageHtml = (
        <h2
          title={'Created by ' + createdBy}
          className={' visible percentuage ' + classname}
        >
          {percentage}
        </h2>
      )
    }
    const savingHtml = (
      <div className={'header-segment-saving'}>
        <div className={'header-segment-saving-loader'} />
        <span>Saving</span>
      </div>
    )
    return segmentOpened ? (
      <div className="header toggle" id={'segment-' + sid + '-header'}>
        {autopropagated}
        {percentageHtml}
        {saving ? savingHtml : null}{' '}
      </div>
    ) : autopropagated || repetition ? (
      <div className={'header header-closed'}>
        {autopropagatedHtml}
        {saving ? savingHtml : null}
      </div>
    ) : (
      <div className={'header header-closed'}>{saving ? savingHtml : null}</div>
    )
  }
}

export default SegmentHeader
