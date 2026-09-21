import React, {createRef, useContext, useEffect, useRef, useState} from 'react'
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
import matchInfo from './matchInfo'
import {SegmentContext} from './SegmentContext'
import {SegmentFooterTabError} from './SegmentFooterTabError'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../common/Button/Button'
import Trash from '../../../img/icons/Trash'
import {NUM_CONTRIBUTION_RESULTS} from '../../constants/Constants'
import Tooltip from '../common/Tooltip'
import IconDown from '../../../img/icons/IconDown'

const MAX_ITEMS_TO_DISPLAY_NOT_EXTENDED = 3

const SUGGESTION_SHORTCUT_LABEL = 'CTRL+'

const SegmentFooterTabMatches = ({segment, code, active_class, tab_class}) => {
  const {multiMatchLangs, clientConnected} = useContext(SegmentContext)

  const [tmKeys, setTmKeys] = useState(() => CatToolStore.getJobTmKeys())
  const [numContributionsToShow, setNumContributionsToShow] = useState(
    MAX_ITEMS_TO_DISPLAY_NOT_EXTENDED,
  )

  const processContributions = (matches) => {
    var matchesProcessed = []
    // SegmentActions.createFooter(segment.sid);
    $.each(matches, function () {
      var item = {}
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
        item.sourceDiff = TextUtils.getDiffHtml(this.segment, segment.segment)

        item.sourceDiff = DraftMatecatUtils.transformTagsToHtml(
          item.sourceDiff,
          config.isSourceRTL,
        )
      }

      if (!isUndefined(this.tm_properties)) {
        item.tm_properties = this.tm_properties
      }
      let matchToInsert = matchInfo.processMatchCallback(item)
      if (matchToInsert) {
        matchesProcessed.push(item)
      }
    })
    return matchesProcessed
  }

  const chooseSuggestion = (sid, index) => {
    if (segment.sid === sid) {
      suggestionDblClick(segment.contributions, index)
    }
  }
  const isOwnerKey = (key) => {
    if (tmKeys && tmKeys.length > 0) {
      const ownedKey = tmKeys.find(
        (currentKey) => currentKey.key === key && currentKey.w === 1,
      )
      return !!ownedKey
    }
    return false
  }

  const suggestionDblClick = (match, index) => {
    setTimeout(() => {
      SegmentActions.setFocusOnEditArea()
      SegmentActions.disableTPOnSegment(segment)
      SegmentActions.setChoosenSuggestion(segment.original_sid, index)
      TranslationMatches.copySuggestionInEditarea(segment, index)
    }, 200)
  }

  const deleteSuggestion = (match) => {
    var source = match.segment
    var target = match.translation

    SegmentActions.deleteContribution(
      source,
      target,
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
            <li className={`percent per-yellow-variant`}>
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

        {/*<li className={'graydesc'}>
          <span className={'bold'} style={{fontSize: '14px'}}>
            {ApplicationStore.getLanguageNameFromLocale(match.target)}
          </span>
        </li>*/}

        {matchInfo.getMatchInfoMetadata({match, segment: segment})}
      </ul>
    )
  }

  // Registered once at mount, so the listener must resolve the segment when it
  // fires rather than closing over the one from the first render.
  const latestRef = useRef()
  latestRef.current = {segment, multiMatchLangs}

  // chooseSuggestion is a plain per-render closure; the mount-registered
  // listener calls whichever one the latest render produced.
  const chooseSuggestionRef = useRef()
  chooseSuggestionRef.current = chooseSuggestion

  useEffect(() => {
    const onChooseContribution = (sid, index) =>
      chooseSuggestionRef.current(sid, index)

    const {segment: current, multiMatchLangs: langs} = latestRef.current
    SegmentActions.getContributions(current.sid, langs)
    SegmentStore.addListener(
      SegmentConstants.CHOOSE_CONTRIBUTION,
      onChooseContribution,
    )
    CatToolStore.addListener(CatToolConstants.UPDATE_TM_KEYS, setTmKeys)

    return () => {
      SegmentStore.removeListener(
        SegmentConstants.CHOOSE_CONTRIBUTION,
        onChooseContribution,
      )
      CatToolStore.removeListener(CatToolConstants.UPDATE_TM_KEYS, setTmKeys)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // Was componentDidUpdate, which does not run on mount; seeding the ref with
  // the current value keeps it from firing on the first render.
  const prevUnlockedRef = useRef(segment.unlocked)
  useEffect(() => {
    if (!prevUnlockedRef.current && segment.unlocked) {
      SegmentActions.getContribution(segment.sid, multiMatchLangs)
    }
    prevUnlockedRef.current = segment.unlocked
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [segment.unlocked])

  const copyText = async (e) => {
    const internalClipboard = document.getSelection()
    if (internalClipboard) {
      e.preventDefault()
      // Get plain text form internalClipboard fragment
      const plainText = internalClipboard
        .toString()
        .replace(new RegExp(String.fromCharCode(parseInt('200B', 16)), 'g'), '')
        .replace(/·/g, ' ')
      try {
        await navigator.clipboard.writeText(plainText)
      } catch {
        // The browser or OS denied clipboard permission — nothing more we can do here.
      }
    }
  }

  const allowHTML = (string) => {
    return {__html: string}
  }

  const toggleExtendend = () =>
    setNumContributionsToShow(
      numContributionsToShow < NUM_CONTRIBUTION_RESULTS
        ? NUM_CONTRIBUTION_RESULTS
        : MAX_ITEMS_TO_DISPLAY_NOT_EXTENDED,
    )

  let matchesHtml = []
  if (
    segment.contributions &&
    segment.contributions.matches &&
    segment.contributions.matches.length > 0
  ) {
    let tpmMatches = processContributions(
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
          <Button
            id={segment.sid + '-tm-' + match.id + '-delete'}
            className="trash"
            title="delete this row"
            type={BUTTON_TYPE.ICON}
            size={BUTTON_SIZE.ICON_XSMALL}
            onClick={() => deleteSuggestion(match, index)}
          >
            <Trash size={16} />
          </Button>
        )
      var item = (
        <ul
          key={match.id}
          className="suggestion-item graysmall"
          data-item={index + 1}
          data-id={match.id}
          data-original={match.segment}
          onDoubleClick={() => suggestionDblClick(match, index + 1)}
        >
          <li className="sugg-source">
            <span
              id={segment.sid + '-tm-' + match.id + '-source'}
              className="suggestion_source"
              dangerouslySetInnerHTML={allowHTML(match.sourceDiff)}
            ></span>
          </li>
          <li className="b sugg-target">
            {/* Only the first three matches have a shortcut bound to them:
                copyContribution1..3 in utils/shortcuts.js. */}
            {index < MAX_ITEMS_TO_DISPLAY_NOT_EXTENDED && (
              <span className="graysmall-message">
                {' '}
                {SUGGESTION_SHORTCUT_LABEL + (index + 1)}
              </span>
            )}
            <span
              id={segment.sid + '-tm-' + match.id + '-translation'}
              className="translation"
              dangerouslySetInnerHTML={allowHTML(match.translationDecodedHtml)}
            ></span>
            {trashIcon}
          </li>
          {getMatchInfo(match)}
        </ul>
      )
      matchesHtml.push(item)
    })
  } else if (
    segment.contributions &&
    segment.contributions.matches &&
    segment.contributions.matches.length === 0
  ) {
    if (config.mt_enabled) {
      matchesHtml.push(
        <ul key={0} className="graysmall message">
          <li>
            No matches could be found for this segment. Please, contact{' '}
            <a href="mailto:support@matecat.com">support@matecat.com</a> if you
            think this is an error.
          </li>
        </ul>,
      )
    } else {
      matchesHtml.push(
        <ul key={0} className="graysmall message">
          <li>No match found for this segment</li>
        </ul>,
      )
    }
  }

  let errors = []
  if (
    segment.contributions &&
    segment.contributions.error &&
    segment.contributions.errors.length > 0
  ) {
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
        let item = (
          <ul className="engine-error-item graysmall">
            <li className="engine-error">
              <div className={imgClass} />
              <span className={'engine-error-message ' + messageClass}>
                {messageTypeText + ' ' + error.message}
              </span>
            </li>
          </ul>
        )

        errors.push(item)
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
            {!isUndefined(matchesHtml) && matchesHtml.length > 0 ? (
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

// The class's shouldComponentUpdate, negated. Its tmKeys and numContributionsToShow
// clauses are dropped: those are state, which now re-renders on its own.
export default React.memo(
  SegmentFooterTabMatches,
  (prev, next) =>
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
    ),
)
