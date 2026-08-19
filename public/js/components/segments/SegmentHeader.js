/**
 * React Component .

 */
import React, {useContext, useEffect, useRef, useState} from 'react'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'
import TranslationMatches from './utils/translationMatches'

const SegmentHeader = (props) => {
  const {repetition, splitted, segmentOpened, sid, saving} = props

  const {userInfo} = useContext(ApplicationWrapperContext)

  const [autopropagated, setAutopropagated] = useState(props.autopropagated)
  const [percentage, setPercentage] = useState('')
  const [classname, setClassname] = useState('')
  const [createdBy, setCreatedBy] = useState('')
  const [visible, setVisible] = useState(false)
  const [charactersCounter, setCharactersCounter] = useState({})
  const [isGroupByTransUnit, setIsGroupByTransUnit] = useState(false)
  const [isActiveCharactersCounter, setIsActiveCharactersCounter] =
    useState()

  // getDerivedStateFromProps equivalent — adjusting state during render so it affects the
  // SAME render pass, instead of introducing an extra render frame like an effect would.
  if (props.autopropagated && !autopropagated) {
    setAutopropagated(true)
  }

  // Mirrors `this.props.sid` never being a stale closure inside changePercentuage/hideHeader,
  // which are registered once (by reference) via SegmentStore.addListener and must keep a
  // stable identity across renders while still reading the CURRENT sid prop.
  const sidRef = useRef(sid)
  sidRef.current = sid

  const changePercentuageRef = useRef(
    (changedSid, segmentMatch, className, changedCreatedBy) => {
      if (sidRef.current == changedSid) {
        setPercentage(TranslationMatches.getPercentTextForMatch(segmentMatch))
        setClassname(className)
        setCreatedBy(changedCreatedBy)
        setVisible(true)
        setAutopropagated(false)
      }
    },
  )
  const changePercentuage = changePercentuageRef.current

  const hideHeaderRef = useRef((hiddenSid) => {
    if (sidRef.current == hiddenSid) {
      setAutopropagated(false)
      setVisible(false)
    }
  })
  const hideHeader = hideHeaderRef.current

  const onCharacterCounterRef = useRef((newCharactersCounter) => {
    setCharactersCounter(newCharactersCounter)
  })
  const onCharacterCounter = onCharacterCounterRef.current

  useEffect(() => {
    SegmentStore.addListener(
      SegmentConstants.SET_SEGMENT_HEADER,
      changePercentuage,
    )
    SegmentStore.addListener(SegmentConstants.HIDE_SEGMENT_HEADER, hideHeader)
    SegmentStore.addListener(
      SegmentConstants.CHARACTER_COUNTER,
      onCharacterCounter,
    )

    const prevInternalId = SegmentStore.getPrevSegment(sid)?.internal_id

    const internalId = SegmentStore.getSegmentByIdToJS(sid)?.internal_id

    const nextInternalId = SegmentStore.getNextSegment({
      current_sid: sid,
    })?.internal_id

    setIsGroupByTransUnit(
      internalId === prevInternalId || internalId === nextInternalId,
    )

    return () => {
      SegmentStore.removeListener(
        SegmentConstants.SET_SEGMENT_HEADER,
        changePercentuage,
      )
      SegmentStore.removeListener(
        SegmentConstants.HIDE_SEGMENT_HEADER,
        hideHeader,
      )
      SegmentStore.removeListener(
        SegmentConstants.CHARACTER_COUNTER,
        onCharacterCounter,
      )
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []) // intentionally empty — must run exactly once on mount/unmount, matching
  // componentDidMount/componentWillUnmount timing exactly; do not add deps here

  // No dependency array — runs after every render (including the first), matching
  // componentDidUpdate's unconditional re-sync of isActiveCharactersCounter from context.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => {
    setIsActiveCharactersCounter(
      userInfo && userInfo.metadata.character_counter,
    )
  })

  let autopropagatedHtml
  let percentageHtml
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
  const shouldDisplayCharactersCounter =
    charactersCounter?.sid === sid &&
    (isActiveCharactersCounter || charactersCounter.limit)

  return segmentOpened ? (
    <div className="header toggle" id={'segment-' + sid + '-header'}>
      {autopropagated ? autopropagatedHtml : percentageHtml}
      {/* Characters counter */}
      {!saving && shouldDisplayCharactersCounter && (
        <div
          className={`segment-counter ${
            charactersCounter.counter > charactersCounter.limit
              ? `segment-counter-limit-error`
              : charactersCounter > charactersCounter.limit - 20
                ? 'segment-counter-limit-warning'
                : ''
          }`}
        >
          {isGroupByTransUnit && (
            <div>
              <span>Segment characters: </span>{' '}
              <span>{charactersCounter.segmentCharacters}</span>
            </div>
          )}
          <div>
            <span>
              {isGroupByTransUnit ? 'Unit characters' : 'Characters'}:{' '}
            </span>
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

const MemoizedSegmentHeader = React.memo(SegmentHeader)
MemoizedSegmentHeader.displayName = 'SegmentHeader'

export default MemoizedSegmentHeader
