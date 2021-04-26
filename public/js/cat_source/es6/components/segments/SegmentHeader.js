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
    var autopropagated = ''
    var percentageHtml = ''
    if (this.state.autopropagated) {
      autopropagated = <span className="repetition">Autopropagated</span>
    } else if (this.props.repetition) {
      autopropagated = <span className="repetition">Repetition</span>
    } else if (this.state.visible && this.state.percentage != '') {
      percentageHtml = (
        <h2
          title={'Created by ' + this.state.createdBy}
          className={' visible percentuage ' + this.state.classname}
        >
          {this.state.percentage}
        </h2>
      )
    }

    return this.props.segmentOpened ? (
      <div
        className="header toggle"
        id={'segment-' + this.props.sid + '-header'}
      >
        {autopropagated}
        {percentageHtml}
      </div>
    ) : this.state.autopropagated || this.props.repetition ? (
      <div className={'header header-closed'}>{autopropagated}</div>
    ) : null
  }
}

export default SegmentHeader
