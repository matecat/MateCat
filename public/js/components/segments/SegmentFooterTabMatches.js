import React, {
  createRef,
  memo,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react'
import PropTypes from 'prop-types'
import {isUndefined} from 'lodash'
import {fromJS} from 'immutable'
import $ from 'jquery'

import SegmentConstants from '../../constants/SegmentConstants'
import SegmentStore from '../../stores/SegmentStore'
import TranslationMatches from './utils/translationMatches'
import TextUtils from '../../utils/textUtils'
import SegmentActions from '../../actions/SegmentActions'
import CatToolStore from '../../stores/CatToolStore'
import CatToolConstants from '../../constants/CatToolConstants'
import {SegmentContext} from './SegmentContext'
import {SegmentFooterTabError} from './SegmentFooterTabError'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../common/Button/Button'
import {NUM_CONTRIBUTION_RESULTS} from '../../constants/Constants'
import Tooltip from '../common/Tooltip'
import IconDown from '../../../img/icons/IconDown'
import matchInfo from './matchInfo'

const MAX_ITEMS_TO_DISPLAY_NOT_EXTENDED = 3
const SUGGESTION_SHORTCUT_LABEL = 'CTRL+'

const allowHTML = (string) => ({__html: string})

const SegmentFooterTabMatches = ({code, active_class, tab_class, segment}) => {
  const {clientConnected, multiMatchLangs} = useContext(SegmentContext)

  const [tmKeys, setTmKeys] = useState(() => CatToolStore.getJobTmKeys())
  const [numContributionsToShow, setNumContributionsToShow] = useState(
    MAX_ITEMS_TO_DISPLAY_NOT_EXTENDED,
  )

  // The listeners are registered once, so they read the current segment through
  // a ref rather than closing over the first one.
  const segmentRef = useRef(segment)
  segmentRef.current = segment

  useEffect(() => {
    const chooseSuggestion = (sid, index) => {
      if (segmentRef.current.sid === sid) {
        suggestionDblClick(index)
      }
    }
    const setJobTmKeys = (keys) => setTmKeys(keys)

    SegmentActions.getContributions(segment.sid, multiMatchLangs)
    SegmentStore.addListener(
      SegmentConstants.CHOOSE_CONTRIBUTION,
      chooseSuggestion,
    )
    CatToolStore.addListener(CatToolConstants.UPDATE_TM_KEYS, setJobTmKeys)

    return () => {
      SegmentStore.removeListener(
        SegmentConstants.CHOOSE_CONTRIBUTION,
        chooseSuggestion,
      )
      CatToolStore.removeListener(CatToolConstants.UPDATE_TM_KEYS, setJobTmKeys)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // Was componentDidUpdate: ask again once the segment becomes editable.
  const wasUnlocked = useRef(segment.unlocked)
  useEffect(() => {
    if (!wasUnlocked.current && segment.unlocked) {
      SegmentActions.getContribution(segment.sid, multiMatchLangs)
    }
    wasUnlocked.current = segment.unlocked
  }, [segment.unlocked, segment.sid, multiMatchLangs])

  const processContributions = (matches) => {
    const matchesProcessed = []
    $.each(matches, function () {
      const item = {}
      item.id = this.id
      item.disabled = this.id == '0' ? true : false
      item.cb = this.created_by.split('MT-').pop()
      item.cb = item.cb.indexOf('Prosa') > -1 ? 'Lara' : item.cb
      item.segment = this.segment
      item.translation = this.translation
      item.target = this.target
      item.source = this.source
      if (
        'sentence_confidence' in this &&
        this.sentence_confidence !== '' &&
        this.sentence_confidence !== 0 &&
        this.sentence_confidence !== '0' &&
        this.sentence_confidence !== null &&
        this.sentence_confidence !== false &&
        typeof this.sentence_confidence !== 'undefined'
      ) {
        item.suggestion_info =
          'Quality: <b>' + this.sentence_confidence + '</b>'
      } else if (this.match != 'MT' && this.match !== 'ICE_MT') {
        item.suggestion_info = this.last_update_date
      } else {
        item.suggestion_info = ''
      }

      item.percentText = TranslationMatches.getPercentTextForMatch(this)
      item.percentClass = TranslationMatches.getPercentageClass(this)
      item.penalty = this.penalty

      // Attention Bug: We are mixing the view mode and the raw data mode.
      // before doing a enhanced  view you will need to add a data-original tag
      //
      item.suggestionDecodedHtml = DraftMatecatUtils.transformTagsToHtml(
        this.segment,
        config.isSourceRTL,
      )

      item.translationDecodedHtml = DraftMatecatUtils.transformTagsToHtml(
        this.translation,
        config.isTargetRTL,
      )
      item.translation = this.translation

      item.sourceDiff = item.suggestionDecodedHtml
      item.memoryKey = this.memory_key
      if (
        this.match !== 'MT' &&
        parseInt(this.match) > 70 &&
        parseInt(this.match) < 100
      ) {
        item.sourceDiff = TextUtils.getDiffHtml(
          this.segment,
          segmentRef.current.segment,
        )

        item.sourceDiff = DraftMatecatUtils.transformTagsToHtml(
          item.sourceDiff,
          config.isSourceRTL,
        )
      }

      if (!isUndefined(this.tm_properties)) {
        item.tm_properties = this.tm_properties
      }
      matchesProcessed.push(item)
    })
    return matchesProcessed
  }

  const isOwnerKey = (key) =>
    Boolean(
      tmKeys?.length > 0 &&
      tmKeys.find((currentKey) => currentKey.key === key && currentKey.w === 1),
    )

  const suggestionDblClick = (index) => {
    setTimeout(() => {
      const currentSegment = segmentRef.current
      SegmentActions.setFocusOnEditArea()
      SegmentActions.disableTPOnSegment(currentSegment)
      SegmentActions.setChoosenSuggestion(currentSegment.original_sid, index)
      TranslationMatches.copySuggestionInEditarea(currentSegment, index)
    }, 200)
  }

  const deleteSuggestion = (match) => {
    SegmentActions.deleteContribution(
      match.segment,
      match.translation,
      match.id,
      segment.original_sid,
    )
  }

  const getMatchInfo = (match) => {
    const penaltyPercRef = createRef()
    return (
      <ul className="graysmall-details">
        <li className="graydesc graydesc-sourcekey">
          Origin:
          <span className="bold" title={match.cb}>
            {' '}
            {match.cb}
          </span>
        </li>
        <li>{match.suggestion_info}</li>
        {(match.target !== config.target_code ||
          match.source !== config.source_code) && (
          <Tooltip
            content={
              <div
                style={{
                  display: 'flex',
                  flexDirection: 'column',
                }}
              >
                <span>
                  Different language pair than the job (1% penalty applied)
                </span>
              </div>
            }
          >
            <li ref={createRef()} className={`percent per-yellow-variant`}>
              {match.source} {'>'} {match.target} (-1%)
            </li>
          </Tooltip>
        )}
        <li className={'percent ' + match.percentClass}>{match.percentText}</li>

        {match.penalty > 0 && (
          <Tooltip
            content={
              <div
                style={{
                  display: 'flex',
                  flexDirection: 'column',
                }}
              >
                <span>Applied penalty:</span>
                <span style={{whiteSpace: 'nowrap'}}>
                  matching percentage reduced by{' '}
                  <b>{Math.round(match.penalty * 100)}%</b>
                </span>
              </div>
            }
          >
            <li
              ref={penaltyPercRef}
              className={`percent ${match.percentClass} per-red-outline`}
            >
              -{Math.round(match.penalty * 100)}%
            </li>
          </Tooltip>
        )}

        {matchInfo.getMatchInfoMetadata({match, segment})}
      </ul>
    )
  }

  const copyText = useCallback(async (e) => {
    const internalClipboard = document.getSelection()
    if (internalClipboard) {
      e.preventDefault()
      // Get plain text form internalClipboard fragment
      const plainText = internalClipboard
        .toString()
        .replace(new RegExp(String.fromCharCode(parseInt('200B', 16)), 'g'), '')
        .replace(/·/g, ' ')
      return await navigator.clipboard.writeText(plainText)
    }
  }, [])

  const toggleExtendend = () =>
    setNumContributionsToShow((previous) =>
      previous < NUM_CONTRIBUTION_RESULTS
        ? NUM_CONTRIBUTION_RESULTS
        : MAX_ITEMS_TO_DISPLAY_NOT_EXTENDED,
    )

  const matchesHtml = []
  if (segment.contributions?.matches?.length > 0) {
    const tpmMatches = processContributions(
      segment.contributions.matches.filter(
        (contribution, index) => index < numContributionsToShow,
      ),
    )

    tpmMatches.forEach((match, index) => {
      const {memoryKey} = match
      const isOwnedKey = memoryKey ? isOwnerKey(memoryKey) : false
      const isPublicTm = match.cb !== 'MT' && !memoryKey
      const trashIcon =
        match.disabled || (!isOwnedKey && !isPublicTm) ? (
          ''
        ) : (
          <span
            id={segment.sid + '-tm-' + match.id + '-delete'}
            className="trash"
            title="delete this row"
            onClick={() => deleteSuggestion(match)}
          />
        )
      matchesHtml.push(
        <ul
          key={match.id}
          className="suggestion-item graysmall"
          data-item={index + 1}
          data-id={match.id}
          data-original={match.segment}
          onDoubleClick={() => suggestionDblClick(index + 1)}
        >
          <li className="sugg-source">
            <span
              id={segment.sid + '-tm-' + match.id + '-source'}
              className="suggestion_source"
              dangerouslySetInnerHTML={allowHTML(match.sourceDiff)}
            ></span>
          </li>
          <li className="b sugg-target">
            <span className="graysmall-message">
              {' '}
              {SUGGESTION_SHORTCUT_LABEL + (index + 1)}
            </span>
            <span
              id={segment.sid + '-tm-' + match.id + '-translation'}
              className="translation"
              dangerouslySetInnerHTML={allowHTML(match.translationDecodedHtml)}
            ></span>
            {trashIcon}
          </li>
          {getMatchInfo(match)}
        </ul>,
      )
    })
  } else if (segment.contributions?.matches?.length === 0) {
    matchesHtml.push(
      config.mt_enabled ? (
        <ul key={0} className="graysmall message">
          <li>
            No matches could be found for this segment. Please, contact{' '}
            <a href="mailto:support@matecat.com">support@matecat.com</a> if you
            think this is an error.
          </li>
        </ul>
      ) : (
        <ul key={0} className="graysmall message">
          <li>No match found for this segment</li>
        </ul>
      ),
    )
  }

  const errors = []
  if (segment.contributions?.error && segment.contributions.errors.length > 0) {
    segment.contributions.errors.forEach((error) => {
      let toAdd = false,
        messageClass,
        imgClass,
        messageTypeText

      switch (error.code) {
        case '-2001':
          toAdd = true
          messageClass = 'error'
          imgClass = 'error-img'
          messageTypeText = 'Error: '
          break
        case '-2002':
          toAdd = true
          messageClass = 'warning'
          imgClass = 'warning-img'
          messageTypeText = 'Warning: '
          break
      }
      if (toAdd) {
        errors.push(
          <ul className="engine-error-item graysmall" key={error.code}>
            <li className="engine-error">
              <div className={imgClass} />
              <span className={'engine-error-message ' + messageClass}>
                {messageTypeText + ' ' + error.message}
              </span>
            </li>
          </ul>,
        )
      }
    })
  }

  const isExtended = numContributionsToShow === NUM_CONTRIBUTION_RESULTS

  const moreButton = (
    <Button
      className={`segment-footer-tab-more-button ${isExtended ? 'segment-footer-tab-more-button-extended-mode' : ''}`}
      type={BUTTON_TYPE.DEFAULT}
      size={BUTTON_SIZE.SMALL}
      onClick={toggleExtendend}
    >
      <IconDown size={18} />
      {isExtended ? 'Fewer' : 'More'}
    </Button>
  )

  return (
    <div
      key={'container_' + code}
      className={'tab sub-editor ' + active_class + ' ' + tab_class}
      id={'segment-' + segment.sid + '-' + tab_class}
      onCopy={copyText}
      onCut={copyText}
    >
      {clientConnected ? (
        <>
          <div className="overflow">
            {matchesHtml.length > 0 ? (
              matchesHtml
            ) : (
              <span className="loader loader_on" />
            )}
          </div>
          {segment.contributions?.matches.length >
            MAX_ITEMS_TO_DISPLAY_NOT_EXTENDED && moreButton}
          {errors.length > 0 && <div className="engine-errors">{errors}</div>}
        </>
      ) : (
        clientConnected === false && <SegmentFooterTabError />
      )}
    </div>
  )
}

SegmentFooterTabMatches.propTypes = {
  code: PropTypes.string,
  active_class: PropTypes.string,
  tab_class: PropTypes.string,
  segment: PropTypes.object.isRequired,
}

// Carried over from shouldComponentUpdate. The two state comparisons it also
// made cannot come across — state changes always re-render now, which is the
// safe direction.
const propsAreEqual = (prev, next) =>
  !(
    ((!isUndefined(next.segment.contributions) ||
      !isUndefined(prev.segment.contributions)) &&
      ((!isUndefined(next.segment.contributions) &&
        isUndefined(prev.segment.contributions)) ||
        !fromJS(prev.segment.contributions).equals(
          fromJS(next.segment.contributions),
        ))) ||
    prev.active_class !== next.active_class ||
    prev.tab_class !== next.tab_class ||
    prev.segment.unlocked !== next.segment.unlocked
  )

export default memo(SegmentFooterTabMatches, propsAreEqual)
