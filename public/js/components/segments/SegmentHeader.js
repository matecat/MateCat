import React, {useContext, useEffect, useState} from 'react'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'
import TranslationMatches from './utils/translationMatches'

const SegmentHeader = ({
  sid,
  autopropagated,
  segmentOpened,
  repetition,
  splitted,
  saving,
}) => {
  const {userInfo} = useContext(ApplicationWrapperContext)

  const [match, setMatch] = useState({
    percentage: '',
    classname: '',
    createdBy: '',
    visible: false,
  })
  const [charactersCounter, setCharactersCounter] = useState({})
  const [isGroupByTransUnit, setIsGroupByTransUnit] = useState(false)
  const [isAutopropagated, setIsAutopropagated] = useState(autopropagated)

  // Replaces getDerivedStateFromProps: the prop can only ever force the flag
  // on, while the store events below turn it off.
  if (autopropagated && !isAutopropagated) setIsAutopropagated(true)

  // Built inside the effect so each listener compares against the sid it was
  // registered with rather than the one from the first render.
  useEffect(() => {
    const changePercentuage = (
      updatedSid,
      segmentMatch,
      className,
      createdBy,
    ) => {
      if (sid == updatedSid) {
        setMatch({
          percentage: TranslationMatches.getPercentTextForMatch(segmentMatch),
          classname: className,
          createdBy,
          visible: true,
        })
        setIsAutopropagated(false)
      }
    }

    const hideHeader = (updatedSid) => {
      if (sid == updatedSid) {
        setMatch((current) => ({...current, visible: false}))
        setIsAutopropagated(false)
      }
    }

    SegmentStore.addListener(
      SegmentConstants.SET_SEGMENT_HEADER,
      changePercentuage,
    )
    SegmentStore.addListener(SegmentConstants.HIDE_SEGMENT_HEADER, hideHeader)
    SegmentStore.addListener(
      SegmentConstants.CHARACTER_COUNTER,
      setCharactersCounter,
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
        setCharactersCounter,
      )
    }
  }, [sid])

  const {percentage, classname, createdBy, visible} = match

  // The class kept this in state and refreshed it from componentDidUpdate,
  // which never runs before the first store event reaches the counter anyway.
  const isActiveCharactersCounter =
    userInfo && userInfo.metadata.character_counter

  let autopropagatedHtml
  if (isAutopropagated && !splitted) {
    autopropagatedHtml = <span className="repetition">Autopropagated</span>
  } else if (repetition && !splitted) {
    autopropagatedHtml = <span className="repetition">Repetition</span>
  }

  let percentageHtml
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

  const {counter, limit} = charactersCounter
  const counterLimitClass =
    counter > limit
      ? 'segment-counter-limit-error'
      : limit > 0 && counter > limit - 20
        ? 'segment-counter-limit-warning'
        : ''

  if (!segmentOpened) {
    return isAutopropagated || repetition ? (
      <div className={'header header-closed'}>
        {autopropagatedHtml}
        {saving ? savingHtml : null}
      </div>
    ) : (
      <div className={'header header-closed'}>{saving ? savingHtml : null}</div>
    )
  }

  return (
    <div className="header toggle" id={'segment-' + sid + '-header'}>
      {isAutopropagated ? autopropagatedHtml : percentageHtml}
      {/* Characters counter */}
      {!saving && shouldDisplayCharactersCounter && (
        <div className={`segment-counter ${counterLimitClass}`}>
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
  )
}

export default SegmentHeader
