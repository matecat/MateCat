import React, {useState, useMemo, useRef, useEffect, useCallback} from 'react'
import classnames from 'classnames'
import {isNull} from 'lodash/lang'

import TextUtils from '../../utils/textUtils'
import SegmentQRLine from './SegmentQRLine'
import SegmentQRIssue from './SegmentQRIssue'
import {
  ANALYSIS_BUCKETS_LABELS,
  SEGMENTS_STATUS,
} from '../../constants/Constants'
import DraftMatecatUtils from '../segments/utils/DraftMatecatUtils'
import SegmentQA from '../../../img/icons/SegmentQA'
import AlertIcon from '../../../img/icons/AlertIcon'
import InfoIcon from '../../../img/icons/InfoIcon'
import {Badge, BADGE_TYPE} from '../common/Badge'

const errorObj = {
  types: {
    TAGS: {
      label: 'Tag mismatch',
    },
    MISMATCH: {
      label: 'Character mismatch',
    },
    GLOSSARY: {
      label: 'Glossary',
    },
  },
  icons: {
    ERROR: <SegmentQA size={16} />,
    WARNING: <AlertIcon size={16} />,
    INFO: <InfoIcon size={16} />,
  },
}

function SegmentQR(props) {
  // Derived values from props
  const source = useMemo(() => props.segment.get('segment'), [props.segment])
  const suggestion = useMemo(
    () => props.segment.get('suggestion'),
    [props.segment],
  )
  const target = useMemo(() => {
    const t = props.segment.get('last_translation')
    return !isNull(t) && t
  }, [props.segment])
  const revise = useMemo(() => {
    const revs = props.segment.get('last_revisions')
    if (!isNull(revs)) {
      const found = revs.find((value) => value.get('revision_number') === 1)
      return found && found.size > 0 ? found.get('translation') : false
    }
    return false
  }, [props.segment])
  const revise2 = useMemo(() => {
    const revs = props.segment.get('last_revisions')
    if (!isNull(revs)) {
      const found = revs.find((value) => value.get('revision_number') === 2)
      return found && found.size > 0 ? found.get('translation') : false
    }
    return false
  }, [props.segment])
  // If second pass separate the issues
  const issuesR1 = useMemo(() => {
    if (props.secondPassReviewEnabled) {
      return props.segment
        .get('issues')
        .filter((value) => value.get('revision_number') === 1)
    }
    return null
  }, [props.segment, props.secondPassReviewEnabled])
  const issuesR2 = useMemo(() => {
    if (props.secondPassReviewEnabled) {
      return props.segment
        .get('issues')
        .filter((value) => value.get('revision_number') === 2)
    }
    return null
  }, [props.segment, props.secondPassReviewEnabled])

  // State
  const [translateDiffOn, setTranslateDiffOn] = useState(
    props.segment.get('last_translation') &&
      !isNull(props.segment.get('last_translation')) &&
      isNull(props.segment.get('last_revisions')),
  )
  const [reviseDiffOn, setReviseDiffOn] = useState(
    !isNull(props.segment.get('last_revisions')) &&
      revise &&
      !revise2 &&
      props.segment.get('last_translation') &&
      !isNull(props.segment.get('last_translation')),
  )
  const [revise2DiffOn, setRevise2DiffOn] = useState(
    !isNull(props.segment.get('last_revisions')) &&
      revise2 &&
      (revise || !isNull(props.segment.get('last_translation'))),
  )
  const [htmlDiff, setHtmlDiff] = useState('')
  const [automatedQaOpen, setAutomatedQaOpen] = useState(
    props.segment.get('issues').size === 0 &&
      props.segment.get('warnings').get('total') > 0,
  )
  const [humanQaOpen, setHumanQaOpen] = useState(
    !props.secondPassReviewEnabled && props.segment.get('issues').size > 0,
  )
  const [r1QaOpen, setR1QaOpen] = useState(props.revisionToShow === '1')
  const [r2QaOpen, setR2QaOpen] = useState(props.revisionToShow === '2')

  // Ref for issues container
  const issuesContainer = useRef(null)

  // Initialize diff on mount
  useEffect(() => {
    function initializeDiff() {
      if (translateDiffOn) {
        return getDiffPatch(suggestion, target)
      } else if (reviseDiffOn) {
        return getDiffPatch(target, revise)
      } else if (revise2DiffOn) {
        const src = revise ? revise : target
        return getDiffPatch(src, revise2)
      }
      return ''
    }
    setHtmlDiff(initializeDiff())
    // eslint-disable-next-line
  }, [])

  // Update QA open state on revisionToShow change
  useEffect(() => {
    setR1QaOpen(props.revisionToShow === '1')
    setR2QaOpen(props.revisionToShow === '2')
  }, [props.revisionToShow])

  // Helper functions
  const getDiffPatch = useCallback((source, text) => {
    return TextUtils.getDiffHtml(source, text)
  }, [])

  const openAutomatedQa = useCallback(() => {
    setAutomatedQaOpen(true)
    setHumanQaOpen(false)
    setR1QaOpen(false)
    setR2QaOpen(false)
  }, [])
  const openHumandQa = useCallback(() => {
    setAutomatedQaOpen(false)
    setHumanQaOpen(true)
  }, [])
  const openR1Qa = useCallback(() => {
    setAutomatedQaOpen(false)
    setR1QaOpen(true)
    setR2QaOpen(false)
  }, [])
  const openR2Qa = useCallback(() => {
    setAutomatedQaOpen(false)
    setR1QaOpen(false)
    setR2QaOpen(true)
  }, [])

  const getAutomatedQaHtml = useCallback(() => {
    let html = []
    let fnMap = (key, obj, type) => {
      let item = (
        <div className="qr-issue automated" key={key + type}>
          <div className={`box-icon ${type.toLowerCase()}`}>
            {errorObj.icons[type]}
          </div>
          <div className="qr-error">
            {errorObj.types[key].label} <b>({obj.size})</b>
          </div>
        </div>
      )
      html.push(item)
    }
    let details = props.segment
      .get('warnings')
      .get('details')
      .get('issues_info')
    if (details.get('ERROR').get('Categories').size > 0) {
      details
        .get('ERROR')
        .get('Categories')
        .entrySeq()
        .forEach((item) => {
          let key = item[0]
          let value = item[1]
          fnMap(key, value, 'ERROR')
        })
    }
    if (details.get('WARNING').get('Categories').size > 0) {
      details
        .get('WARNING')
        .get('Categories')
        .entrySeq()
        .forEach((item) => {
          let key = item[0]
          let value = item[1]
          fnMap(key, value, 'WARNING')
        })
    }
    if (details.get('INFO').get('Categories').size > 0) {
      details
        .get('INFO')
        .get('Categories')
        .entrySeq()
        .forEach((item) => {
          let key = item[0]
          let value = item[1]
          fnMap(key, value, 'INFO')
        })
    }
    return html
  }, [props.segment])

  const getHumanQaHtml = useCallback((issues) => {
    let html = []
    issues.map((issue, index) => {
      let item = <SegmentQRIssue key={index} index={index} issue={issue} />
      html.push(item)
    })
    return html
  }, [])

  const showTranslateDiff = useCallback(() => {
    if (translateDiffOn) {
      setTranslateDiffOn(false)
    } else {
      let diffHtml = getDiffPatch(suggestion, target)
      setTranslateDiffOn(true)
      setReviseDiffOn(false)
      setRevise2DiffOn(false)
      setHtmlDiff(diffHtml)
    }
  }, [translateDiffOn, suggestion, target, getDiffPatch])

  const showReviseDiff = useCallback(() => {
    if (reviseDiffOn) {
      setReviseDiffOn(false)
    } else {
      let textToDiff = target ? target : suggestion
      let diffHtml = getDiffPatch(textToDiff, revise)
      setTranslateDiffOn(false)
      setReviseDiffOn(true)
      setRevise2DiffOn(false)
      setHtmlDiff(diffHtml)
    }
  }, [reviseDiffOn, target, suggestion, revise, getDiffPatch])

  const showRevise2Diff = useCallback(() => {
    if (revise2DiffOn) {
      setRevise2DiffOn(false)
    } else {
      let textToDiff = revise ? revise : target ? target : suggestion
      let diffHtml = getDiffPatch(textToDiff, revise2)
      setTranslateDiffOn(false)
      setReviseDiffOn(false)
      setRevise2DiffOn(true)
      setHtmlDiff(diffHtml)
    }
  }, [revise2DiffOn, revise, target, suggestion, revise2, getDiffPatch])

  const getWordsSpeed = useCallback(() => {
    let str_pad_left = function (string, pad, length) {
      return (new Array(length + 1).join(pad) + string).slice(-length)
    }
    let time = parseInt(props.segment.get('secs_per_word'))
    let minutes = Math.floor(time / 60)
    let seconds = time - minutes * 60
    if (minutes > 0) {
      return (
        str_pad_left(minutes, '0', 2) +
        "'" +
        str_pad_left(seconds, '0', 2) +
        "''"
      )
    } else {
      return str_pad_left(seconds, '0', 2) + "''"
    }
  }, [props.segment])

  const openTranslateLink = useCallback(() => {
    window.open(props.urls.get('translate_url') + '#' + props.segment.get('id'))
  }, [props.urls, props.segment])

  const openReviseLink = useCallback(
    (reviseNum) => {
      if (
        typeof props.urls.get('revise_url') === 'string' ||
        props.urls.get('revise_url') instanceof String
      ) {
        window.open(
          props.urls.get('revise_url') + '#' + props.segment.get('id'),
        )
      } else {
        let url = props.urls
          .get('revise_urls')
          .find((value) => {
            return value.get('revision_number') === reviseNum
          })
          .get('url')
        window.open(url + '#' + props.segment.get('id'))
      }
    },
    [props.urls, props.segment],
  )

  const decodeTextAndTransformTags = useCallback((text, isRtl) => {
    if (text) {
      // Fix for more than 2 followed spaces
      text = text.replace(/ {2}/gi, '&nbsp; ')
      return DraftMatecatUtils.transformTagsToHtml(text, isRtl)
    }
    return text
  }, [])

  const allowHTML = useCallback((string) => {
    return {__html: string}
  }, [])

  // Render logic
  let renderedSource = decodeTextAndTransformTags(source, config.isSourceRTL)
  let renderedSuggestion = decodeTextAndTransformTags(
    suggestion,
    config.isTargetRTL,
  )
  let renderedTarget =
    target && decodeTextAndTransformTags(target, config.isTargetRTL)
  let renderedRevise =
    revise && decodeTextAndTransformTags(revise, config.isTargetRTL)
  let renderedRevise2 =
    revise2 && decodeTextAndTransformTags(revise2, config.isTargetRTL)

  if (translateDiffOn) {
    renderedTarget = decodeTextAndTransformTags(htmlDiff, config.isTargetRTL)
  }
  if (reviseDiffOn) {
    renderedRevise = decodeTextAndTransformTags(htmlDiff, config.isTargetRTL)
  }
  if (revise2DiffOn) {
    renderedRevise2 = decodeTextAndTransformTags(htmlDiff, config.isTargetRTL)
  }

  let sourceClass = classnames({
    'segment-container': true,
    'qr-source': true,
    'rtl-lang': config.isSourceRTL,
  })
  let segmentBodyClass = classnames({
    'qr-segment-body': true,
    'qr-diff-on': translateDiffOn || reviseDiffOn || revise2DiffOn,
  })
  let suggestionClasses = classnames({
    'segment-container': true,
    'qr-suggestion': true,
    'shadow-1':
      translateDiffOn ||
      (reviseDiffOn && !target) ||
      (revise2DiffOn && !revise && !target),
    'rtl-lang': config.isTargetRTL,
  })
  let translateClasses = classnames({
    'segment-container': true,
    'qr-translated': true,
    'shadow-1': translateDiffOn || reviseDiffOn || (revise2DiffOn && !revise),
    'rtl-lang': config.isTargetRTL,
  })
  let revisedClasses = classnames({
    'segment-container': true,
    'qr-revised': true,
    'shadow-1': reviseDiffOn || revise2DiffOn,
    'rtl-lang': config.isTargetRTL,
  })
  let revised2Classes = classnames({
    'segment-container': true,
    'qr-revised': true,
    'qr-revised-2ndpass': true,
    'shadow-1': revise2DiffOn,
    'rtl-lang': config.isTargetRTL,
  })

  return (
    <div className="qr-single-segment">
      <div className="qr-segment-head">
        <div className="segment-id">{props.segment.get('id')}</div>
        <div className="segment-production-container">
          <div className="segment-production">
            <div className="production match-type">
              Analysis:{' '}
              <b>{ANALYSIS_BUCKETS_LABELS[props.segment.get('match_type')]}</b>
            </div>
            <div className="production word-speed">
              Secs/Word: <b>{getWordsSpeed()}</b>
            </div>
            {/*<div className="production time-edit">Time to edit: <b>{this.getTimeToEdit()}</b></div>*/}
            <div className="production pee">
              PEE: <b>{props.segment.get('pee')}%</b>
            </div>
          </div>
        </div>
        <div className="segment-status-container">
          <div className="qr-label">Segment status</div>
          <div className="qr-info">
            <Badge
              type={
                props.segment.get('status').toUpperCase() ===
                  SEGMENTS_STATUS.NEW ||
                props.segment.get('status').toUpperCase() ===
                  SEGMENTS_STATUS.DRAFT
                  ? BADGE_TYPE.GREY
                  : props.segment.get('status').toUpperCase() ===
                      SEGMENTS_STATUS.APPROVED
                    ? BADGE_TYPE.GREEN
                    : props.segment.get('status').toUpperCase() ===
                        SEGMENTS_STATUS.APPROVED2
                      ? BADGE_TYPE.PURPLE
                      : BADGE_TYPE.PRIMARY
              }
            >
              {props.segment.get('status') === SEGMENTS_STATUS.APPROVED2
                ? SEGMENTS_STATUS.APPROVED.toLowerCase()
                : props.segment.get('status').toLowerCase()}
            </Badge>
          </div>
        </div>
      </div>
      <div className={segmentBodyClass}>
        <SegmentQRLine
          segment={props.segment}
          classes={sourceClass}
          label={'Source'}
          text={renderedSource}
          showSegmentWords={true}
        />
        <SegmentQRLine
          segment={props.segment}
          classes={suggestionClasses}
          label={'Suggestion'}
          showSuggestionSource={true}
          text={renderedSuggestion}
        />
        {props.segment.get('last_translation') ? (
          <SegmentQRLine
            segment={props.segment}
            classes={translateClasses}
            label={'Translation'}
            onClickLabel={openTranslateLink}
            text={renderedTarget}
            showDiffButton={true}
            onClickDiff={showTranslateDiff}
            diffActive={translateDiffOn}
            showIceMatchInfo={true}
            tte={props.segment.get('time_to_edit_translation')}
            showIsPretranslated={
              props.segment.get('is_pre_translated') &&
              !props.segment.get('ice_locked')
            }
            rev={0}
          />
        ) : null}
        {!isNull(props.segment.get('last_revisions')) && revise ? (
          <SegmentQRLine
            segment={props.segment}
            classes={revisedClasses}
            label={'Revision'}
            onClickLabel={() => openReviseLink(1)}
            text={renderedRevise}
            showDiffButton={true}
            onClickDiff={showReviseDiff}
            diffActive={reviseDiffOn}
            showIceMatchInfo={isNull(target)}
            tte={props.segment.get('time_to_edit_revise')}
            showIsPretranslated={
              props.segment.get('is_pre_translated') &&
              !props.segment.get('ice_locked')
            }
            rev={1}
          />
        ) : null}
        {!isNull(props.segment.get('last_revisions')) && revise2 ? (
          <SegmentQRLine
            segment={props.segment}
            classes={revised2Classes}
            label={'2nd Revision'}
            onClickLabel={() => openReviseLink(2)}
            text={renderedRevise2}
            showDiffButton={true}
            onClickDiff={showRevise2Diff}
            diffActive={revise2DiffOn}
            showIceMatchInfo={isNull(target) && isNull(revise)}
            tte={props.segment.get('time_to_edit_revise_2')}
            showIsPretranslated={
              props.segment.get('is_pre_translated') &&
              !props.segment.get('ice_locked')
            }
            rev={2}
          />
        ) : null}
        {automatedQaOpen || humanQaOpen || r1QaOpen || r2QaOpen ? (
          <div className="segment-container qr-issues">
            <div className="segment-content qr-segment-title">
              <b>QA</b>
              <div className="ui basic mini buttons segment-production">
                {props.segment.get('issues').size > 0 &&
                !props.secondPassReviewEnabled ? (
                  <div
                    className={
                      'ui button human-qa ' +
                      (humanQaOpen ? 'active' : '') +
                      ' ' +
                      (props.segment.get('warnings').get('total') > 0
                        ? ''
                        : 'no-hover')
                    }
                    onClick={openHumandQa}
                  >
                    Human<b> ({props.segment.get('issues').size})</b>
                  </div>
                ) : null}
                {issuesR1 &&
                issuesR1.size > 0 &&
                props.secondPassReviewEnabled ? (
                  <div
                    className={
                      'ui button human-qa ' + (r1QaOpen ? 'active' : '')
                    }
                    style={{padding: '8px'}}
                    onClick={openR1Qa}
                  >
                    R1<b> ({issuesR1.size})</b>
                  </div>
                ) : null}
                {issuesR2 &&
                issuesR2.size > 0 &&
                props.secondPassReviewEnabled ? (
                  <div
                    className={
                      'ui button human-qa ' + (r2QaOpen ? 'active' : '')
                    }
                    style={{padding: '8px'}}
                    onClick={openR2Qa}
                  >
                    R2<b> ({issuesR2.size})</b>
                  </div>
                ) : null}
                {props.segment.get('warnings').get('total') > 0 ? (
                  <div
                    className={
                      'ui button automated-qa ' +
                      (automatedQaOpen ? 'active' : '') +
                      ' ' +
                      (props.segment.get('issues').size > 0 ? '' : 'no-hover')
                    }
                    onClick={openAutomatedQa}
                  >
                    Automated
                    <b> ({props.segment.get('warnings').get('total')})</b>
                  </div>
                ) : null}
              </div>
            </div>
            <div className="segment-content qr-text" ref={issuesContainer}>
              {automatedQaOpen ? (
                <div className="qr-issues-list" key={'automated-qa'}>
                  {getAutomatedQaHtml()}
                </div>
              ) : null}
              {humanQaOpen ? (
                <div className="qr-issues-list" key={'human-qa'}>
                  {getHumanQaHtml(props.segment.get('issues'))}
                </div>
              ) : null}
              {r1QaOpen && issuesR1 ? (
                <div className="qr-issues-list" key={'issues-r1-qa'}>
                  {getHumanQaHtml(issuesR1)}
                </div>
              ) : null}
              {r2QaOpen && issuesR2 ? (
                <div className="qr-issues-list" key={'issues-r2-qa'}>
                  {getHumanQaHtml(issuesR2)}
                </div>
              ) : null}
            </div>
          </div>
        ) : null}
      </div>
    </div>
  )
}

export default SegmentQR
