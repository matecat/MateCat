import React, {useState, useMemo, useRef, useEffect, useCallback} from 'react'
import classnames from 'classnames'

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

const QA_TYPES = ['ERROR', 'WARNING', 'INFO']

const QA_CATEGORY_LABELS = {
  TAGS: 'Tag mismatch',
  MISMATCH: 'Character mismatch',
  GLOSSARY: 'Glossary',
}

const QA_ICONS = {
  ERROR: <SegmentQA size={16} />,
  WARNING: <AlertIcon size={16} />,
  INFO: <InfoIcon size={16} />,
}

const strPadLeft = (value, pad, length) =>
  (new Array(length + 1).join(pad) + value).slice(-length)

const decodeTextAndTransformTags = (text, isRtl) => {
  if (text) {
    // Fix for more than 2 followed spaces
    const normalized = text.replace(/ {2}/gi, '&nbsp; ')
    return DraftMatecatUtils.transformTagsToHtml(normalized, isRtl)
  }
  return text
}

const getRevisionTranslation = (revisions, revisionNumber) => {
  if (revisions === null) return false
  const found = revisions.find(
    (value) => value.get('revision_number') === revisionNumber,
  )
  return found && found.size > 0 ? found.get('translation') : false
}

const getStatusBadgeType = (status) => {
  const upper = status.toUpperCase()
  if (upper === SEGMENTS_STATUS.NEW || upper === SEGMENTS_STATUS.DRAFT) {
    return BADGE_TYPE.GREY
  }
  if (upper === SEGMENTS_STATUS.APPROVED) return BADGE_TYPE.GREEN
  if (upper === SEGMENTS_STATUS.APPROVED2) return BADGE_TYPE.PURPLE
  return BADGE_TYPE.PRIMARY
}

function SegmentQR({segment, urls, secondPassReviewEnabled, revisionToShow}) {
  // Derived values from props
  const source = useMemo(() => segment.get('segment'), [segment])
  const suggestion = useMemo(() => segment.get('suggestion'), [segment])
  const target = useMemo(() => {
    const t = segment.get('last_translation')
    return t !== null && t
  }, [segment])
  const revise = useMemo(
    () => getRevisionTranslation(segment.get('last_revisions'), 1),
    [segment],
  )
  const revise2 = useMemo(
    () => getRevisionTranslation(segment.get('last_revisions'), 2),
    [segment],
  )

  // If second pass, separate the issues by revision number
  const issuesR1 = useMemo(() => {
    if (!secondPassReviewEnabled) return null
    return segment
      .get('issues')
      .filter((value) => value.get('revision_number') === 1)
  }, [segment, secondPassReviewEnabled])

  const issuesR2 = useMemo(() => {
    if (!secondPassReviewEnabled) return null
    return segment
      .get('issues')
      .filter((value) => value.get('revision_number') === 2)
  }, [segment, secondPassReviewEnabled])

  // State
  const lastTranslation = segment.get('last_translation')
  const lastRevisions = segment.get('last_revisions')

  const [translateDiffOn, setTranslateDiffOn] = useState(
    lastTranslation !== null && lastTranslation && lastRevisions === null,
  )
  const [reviseDiffOn, setReviseDiffOn] = useState(
    lastRevisions !== null &&
      revise &&
      !revise2 &&
      lastTranslation !== null &&
      !!lastTranslation,
  )
  const [revise2DiffOn, setRevise2DiffOn] = useState(
    lastRevisions !== null && revise2 && (revise || lastTranslation !== null),
  )
  const [htmlDiff, setHtmlDiff] = useState('')
  const [automatedQaOpen, setAutomatedQaOpen] = useState(
    segment.get('issues').size === 0 &&
      segment.get('warnings').get('total') > 0,
  )
  const [humanQaOpen, setHumanQaOpen] = useState(
    !secondPassReviewEnabled && segment.get('issues').size > 0,
  )
  const [r1QaOpen, setR1QaOpen] = useState(revisionToShow === '1')
  const [r2QaOpen, setR2QaOpen] = useState(revisionToShow === '2')

  const issuesContainer = useRef(null)

  // Initialize diff on mount
  useEffect(() => {
    const getDiff = (src, tgt) => TextUtils.getDiffHtml(src, tgt)
    if (translateDiffOn) {
      setHtmlDiff(getDiff(suggestion, target))
    } else if (reviseDiffOn) {
      setHtmlDiff(getDiff(target, revise))
    } else if (revise2DiffOn) {
      setHtmlDiff(getDiff(revise || target, revise2))
    }
    // eslint-disable-next-line
  }, [])

  useEffect(() => {
    setR1QaOpen(revisionToShow === '1')
    setR2QaOpen(revisionToShow === '2')
  }, [revisionToShow])

  // QA tab handlers
  const openAutomatedQa = useCallback(() => {
    setAutomatedQaOpen(true)
    setHumanQaOpen(false)
    setR1QaOpen(false)
    setR2QaOpen(false)
  }, [])

  const openHumanQa = useCallback(() => {
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

  // Diff toggle handlers
  const showTranslateDiff = useCallback(() => {
    if (translateDiffOn) {
      setTranslateDiffOn(false)
    } else {
      setTranslateDiffOn(true)
      setReviseDiffOn(false)
      setRevise2DiffOn(false)
      setHtmlDiff(TextUtils.getDiffHtml(suggestion, target))
    }
  }, [translateDiffOn, suggestion, target])

  const showReviseDiff = useCallback(() => {
    if (reviseDiffOn) {
      setReviseDiffOn(false)
    } else {
      setTranslateDiffOn(false)
      setReviseDiffOn(true)
      setRevise2DiffOn(false)
      setHtmlDiff(TextUtils.getDiffHtml(target || suggestion, revise))
    }
  }, [reviseDiffOn, target, suggestion, revise])

  const showRevise2Diff = useCallback(() => {
    if (revise2DiffOn) {
      setRevise2DiffOn(false)
    } else {
      setTranslateDiffOn(false)
      setReviseDiffOn(false)
      setRevise2DiffOn(true)
      setHtmlDiff(
        TextUtils.getDiffHtml(revise || target || suggestion, revise2),
      )
    }
  }, [revise2DiffOn, revise, target, suggestion, revise2])

  const getAutomatedQaHtml = useCallback(() => {
    const details = segment.get('warnings').get('details').get('issues_info')

    return QA_TYPES.flatMap((type) => {
      const categories = details.get(type).get('Categories')
      if (categories.size === 0) return []
      return categories
        .entrySeq()
        .map(([key, value]) => (
          <div className="qr-issue automated" key={key + type}>
            <div className={`box-icon ${type.toLowerCase()}`}>
              {QA_ICONS[type]}
            </div>
            <div className="qr-error">
              {QA_CATEGORY_LABELS[key]} <b>({value.size})</b>
            </div>
          </div>
        ))
        .toArray()
    })
  }, [segment])

  const renderHumanQaIssues = useCallback(
    (issues) =>
      issues
        .map((issue, index) => (
          <SegmentQRIssue key={index} index={index} issue={issue} />
        ))
        .toArray(),
    [],
  )

  const getWordsSpeed = useCallback(() => {
    const time = parseInt(segment.get('secs_per_word'))
    const minutes = Math.floor(time / 60)
    const seconds = time - minutes * 60
    if (minutes > 0) {
      return `${strPadLeft(minutes, '0', 2)}'${strPadLeft(seconds, '0', 2)}''`
    }
    return `${strPadLeft(seconds, '0', 2)}''`
  }, [segment])

  const openTranslateLink = useCallback(() => {
    window.open(`${urls.get('translate_url')}#${segment.get('id')}`)
  }, [urls, segment])

  const openReviseLink = useCallback(
    (reviseNum) => {
      const reviseUrl = urls.get('revise_url')
      if (typeof reviseUrl === 'string' || reviseUrl instanceof String) {
        window.open(`${reviseUrl}#${segment.get('id')}`)
      } else {
        const url = urls
          .get('revise_urls')
          .find((value) => value.get('revision_number') === reviseNum)
          .get('url')
        window.open(`${url}#${segment.get('id')}`)
      }
    },
    [urls, segment],
  )

  // Render logic
  const renderedSource = decodeTextAndTransformTags(source, config.isSourceRTL)
  const renderedSuggestion = decodeTextAndTransformTags(
    suggestion,
    config.isTargetRTL,
  )
  const renderedTarget = translateDiffOn
    ? decodeTextAndTransformTags(htmlDiff, config.isTargetRTL)
    : target && decodeTextAndTransformTags(target, config.isTargetRTL)
  const renderedRevise = reviseDiffOn
    ? decodeTextAndTransformTags(htmlDiff, config.isTargetRTL)
    : revise && decodeTextAndTransformTags(revise, config.isTargetRTL)
  const renderedRevise2 = revise2DiffOn
    ? decodeTextAndTransformTags(htmlDiff, config.isTargetRTL)
    : revise2 && decodeTextAndTransformTags(revise2, config.isTargetRTL)

  const isDiffOn = translateDiffOn || reviseDiffOn || revise2DiffOn
  const sourceClass = classnames('segment-container', 'qr-source', {
    'rtl-lang': config.isSourceRTL,
  })
  const segmentBodyClass = classnames('qr-segment-body', {
    'qr-diff-on': isDiffOn,
  })
  const suggestionClasses = classnames('segment-container', 'qr-suggestion', {
    'shadow-1':
      translateDiffOn ||
      (reviseDiffOn && !target) ||
      (revise2DiffOn && !revise && !target),
    'rtl-lang': config.isTargetRTL,
  })
  const translateClasses = classnames('segment-container', 'qr-translated', {
    'shadow-1': translateDiffOn || reviseDiffOn || (revise2DiffOn && !revise),
    'rtl-lang': config.isTargetRTL,
  })
  const revisedClasses = classnames('segment-container', 'qr-revised', {
    'shadow-1': reviseDiffOn || revise2DiffOn,
    'rtl-lang': config.isTargetRTL,
  })
  const revised2Classes = classnames(
    'segment-container',
    'qr-revised',
    'qr-revised-2ndpass',
    {
      'shadow-1': revise2DiffOn,
      'rtl-lang': config.isTargetRTL,
    },
  )

  const issuesCount = segment.get('issues').size
  const warningsTotal = segment.get('warnings').get('total')
  const isQaVisible = automatedQaOpen || humanQaOpen || r1QaOpen || r2QaOpen

  return (
    <div className="qr-single-segment">
      <div className="qr-segment-head">
        <div className="segment-id">{segment.get('id')}</div>
        <div className="segment-production-container">
          <div className="segment-production">
            <div className="production match-type">
              Analysis:{' '}
              <b>{ANALYSIS_BUCKETS_LABELS[segment.get('match_type')]}</b>
            </div>
            <div className="production word-speed">
              Secs/Word: <b>{getWordsSpeed()}</b>
            </div>
            <div className="production pee">
              PEE: <b>{segment.get('pee')}%</b>
            </div>
          </div>
        </div>
        <div className="segment-status-container">
          <div className="qr-label">Segment status</div>
          <div className="qr-info">
            <Badge type={getStatusBadgeType(segment.get('status'))}>
              {segment.get('status') === SEGMENTS_STATUS.APPROVED2
                ? SEGMENTS_STATUS.APPROVED.toLowerCase()
                : segment.get('status').toLowerCase()}
            </Badge>
          </div>
        </div>
      </div>
      <div className={segmentBodyClass}>
        <SegmentQRLine
          segment={segment}
          classes={sourceClass}
          label="Source"
          text={renderedSource}
          showSegmentWords={true}
        />
        <SegmentQRLine
          segment={segment}
          classes={suggestionClasses}
          label="Suggestion"
          showSuggestionSource={true}
          text={renderedSuggestion}
        />
        {segment.get('last_translation') ? (
          <SegmentQRLine
            segment={segment}
            classes={translateClasses}
            label="Translation"
            onClickLabel={openTranslateLink}
            text={renderedTarget}
            showDiffButton={true}
            onClickDiff={showTranslateDiff}
            diffActive={translateDiffOn}
            showIceMatchInfo={true}
            tte={segment.get('time_to_edit_translation')}
            showIsPretranslated={
              segment.get('is_pre_translated') && !segment.get('ice_locked')
            }
            rev={0}
          />
        ) : null}
        {segment.get('last_revisions') !== null && revise ? (
          <SegmentQRLine
            segment={segment}
            classes={revisedClasses}
            label="Revision"
            onClickLabel={() => openReviseLink(1)}
            text={renderedRevise}
            showDiffButton={true}
            onClickDiff={showReviseDiff}
            diffActive={reviseDiffOn}
            showIceMatchInfo={target === null}
            tte={segment.get('time_to_edit_revise')}
            showIsPretranslated={
              segment.get('is_pre_translated') && !segment.get('ice_locked')
            }
            rev={1}
          />
        ) : null}
        {segment.get('last_revisions') !== null && revise2 ? (
          <SegmentQRLine
            segment={segment}
            classes={revised2Classes}
            label="2nd Revision"
            onClickLabel={() => openReviseLink(2)}
            text={renderedRevise2}
            showDiffButton={true}
            onClickDiff={showRevise2Diff}
            diffActive={revise2DiffOn}
            showIceMatchInfo={target === null && revise === null}
            tte={segment.get('time_to_edit_revise_2')}
            showIsPretranslated={
              segment.get('is_pre_translated') && !segment.get('ice_locked')
            }
            rev={2}
          />
        ) : null}
        {isQaVisible && (
          <div className="segment-container qr-issues">
            <div className="segment-content qr-segment-title">
              <b>QA</b>
              <div className="ui basic mini buttons segment-production">
                {issuesCount > 0 && !secondPassReviewEnabled && (
                  <div
                    className={classnames('ui button human-qa', {
                      active: humanQaOpen,
                      'no-hover': warningsTotal === 0,
                    })}
                    onClick={openHumanQa}
                  >
                    Human<b> ({issuesCount})</b>
                  </div>
                )}
                {issuesR1?.size > 0 && secondPassReviewEnabled && (
                  <div
                    className={classnames('ui button human-qa', {
                      active: r1QaOpen,
                    })}
                    style={{padding: '8px'}}
                    onClick={openR1Qa}
                  >
                    R1<b> ({issuesR1.size})</b>
                  </div>
                )}
                {issuesR2?.size > 0 && secondPassReviewEnabled && (
                  <div
                    className={classnames('ui button human-qa', {
                      active: r2QaOpen,
                    })}
                    style={{padding: '8px'}}
                    onClick={openR2Qa}
                  >
                    R2<b> ({issuesR2.size})</b>
                  </div>
                )}
                {warningsTotal > 0 && (
                  <div
                    className={classnames('ui button automated-qa', {
                      active: automatedQaOpen,
                      'no-hover': issuesCount === 0,
                    })}
                    onClick={openAutomatedQa}
                  >
                    Automated
                    <b> ({warningsTotal})</b>
                  </div>
                )}
              </div>
            </div>
            <div className="segment-content qr-text" ref={issuesContainer}>
              {automatedQaOpen && (
                <div className="qr-issues-list" key="automated-qa">
                  {getAutomatedQaHtml()}
                </div>
              )}
              {humanQaOpen && (
                <div className="qr-issues-list" key="human-qa">
                  {renderHumanQaIssues(segment.get('issues'))}
                </div>
              )}
              {r1QaOpen && issuesR1 && (
                <div className="qr-issues-list" key="issues-r1-qa">
                  {renderHumanQaIssues(issuesR1)}
                </div>
              )}
              {r2QaOpen && issuesR2 && (
                <div className="qr-issues-list" key="issues-r2-qa">
                  {renderHumanQaIssues(issuesR2)}
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  )
}

export default SegmentQR
