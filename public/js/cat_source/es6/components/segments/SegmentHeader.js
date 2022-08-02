/**
 * React Component .

 */
import React from 'react'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import SegmentUtils from '../../utils/segmentUtils'

class SegmentHeader extends React.PureComponent {
  constructor(props) {
    super(props)
    this.state = {
      autopropagated: this.props.autopropagated,
      percentage: '',
      classname: '',
      createdBy: '',
      visible: false,
      isActiveCharactersCounter: SegmentUtils.isCharacterCounterEnable(),
      charactersCounter: {},
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
    SegmentStore.addListener(
      SegmentConstants.TOGGLE_CHARACTER_COUNTER,
      this.onToggleCharacterCounter,
    )
    SegmentStore.addListener(
      SegmentConstants.CHARACTER_COUNTER,
      this.onCharacterCounter,
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
    SegmentStore.removeListener(
      SegmentConstants.TOGGLE_CHARACTER_COUNTER,
      this.onToggleCharacterCounter,
    )
    SegmentStore.removeListener(
      SegmentConstants.CHARACTER_COUNTER,
      this.onCharacterCounter,
    )
    this.setState({
      charactersCounter: {},
    })
  }

  onToggleCharacterCounter = () => {
    const isActiveCharactersCounter = !this.state.isActiveCharactersCounter
    this.setState({
      isActiveCharactersCounter,
    })
    SegmentUtils.setCharacterCounterOptionValue(isActiveCharactersCounter)
  }

  onCharacterCounter = (charactersCounter) => {
    this.setState({
      charactersCounter,
    })
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
    const {isActiveCharactersCounter, charactersCounter} = this.state
    const shouldDisplayCharactersCounter =
      charactersCounter?.sid === sid &&
      (isActiveCharactersCounter || charactersCounter.limit)

    return segmentOpened ? (
      <div className="header toggle" id={'segment-' + sid + '-header'}>
        {autopropagated ? autopropagatedHtml : percentageHtml}
        {/* Characters counter */}
        {!autopropagated && !saving && shouldDisplayCharactersCounter && (
          <div
            className={`segment-counter ${
              charactersCounter.counter > charactersCounter.limit
                ? `segment-counter-limit-error`
                : charactersCounter > charactersCounter.limit - 20
                ? 'segment-counter-limit-warning'
                : ''
            }`}
          >
            <span>Character count: </span>
            <span className="segment-counter-current">
              {charactersCounter.counter}
            </span>
            {charactersCounter.limit > 0 && (
              <>
                /
                <span className={'segment-counter-limit'}>
                  {charactersCounter.limit}
                </span>
              </>
            )}
          </div>
        )}
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
