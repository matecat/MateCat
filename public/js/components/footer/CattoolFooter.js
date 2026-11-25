import React, {useEffect, useRef, useState} from 'react'
import CatToolConstants from '../../constants/CatToolConstants'
import CatToolStore from '../../stores/CatToolStore'
import SegmentActions from '../../actions/SegmentActions'
import TooltipInfo from '../segments/TooltipInfo/TooltipInfo.component'
import JobProgressBar from '../common/JobProgressBar'
import {BUTTON_MODE, BUTTON_SIZE, Button} from '../common/Button/Button'
import {REVISE_STEP_NUMBER} from '../../constants/Constants'
import {CookieConsent} from '../common/CookieConsent'
import IconRedirect from '../../../img/icons/IconRedirect'
import Tooltip from '../common/Tooltip'
import IconArrowRight from '../../../img/icons/IconArrowRight'

export const CattoolFooter = ({
  idProject,
  idJob,
  password,
  languagesArray,
  source,
  target,
  isCJK,
}) => {
  const [stats, setStats] = React.useState(CatToolStore.getProgress())
  const [isShowingTooltip, setIsShowingTooltip] = useState({
    progressBar: false,
  })

  const goToAnalysisRef = useRef()
  const openToDoRef = useRef()
  const etcLabelRef = useRef()
  const etcNARef = useRef()
  const speedNaRef = useRef()
  const wordsCountRef = useRef()
  const toDoCountRef = useRef()

  const sourceLang =
    languagesArray.find((item) => item.code == source)?.name ?? source
  const targetLang =
    languagesArray.find((item) => item.code == target)?.name ?? target

  useEffect(() => {
    const listener = (stats) => {
      setStats(stats)
    }

    CatToolStore.addListener(CatToolConstants.SET_PROGRESS, listener)

    return () => {
      CatToolStore.removeListener(CatToolConstants.SET_PROGRESS, listener)
    }
  }, [])

  const onClickTodo = (e, targetName) => {
    e.preventDefault()
    if (!stats) return
    // show tooltip
    if (
      (!config.isReview && stats.translationCompleted) ||
      (config.isReview &&
        config.revisionNumber === 1 &&
        stats.revisionCompleted) ||
      (config.isReview &&
        config.revisionNumber === 2 &&
        stats.revision2Completed)
    ) {
      setIsShowingTooltip({progressBar: false, [targetName]: true})
      return
    }
    if (config.isReview) {
      SegmentActions.gotoNextTranslatedSegment()
    } else {
      SegmentActions.gotoNextUntranslatedSegment()
    }
  }

  const onClickOpenJobAnalysis = () =>
    window.open(`/jobanalysis/${idProject}-${idJob}-${password}`, '_blank')

  const getTooltip = (targetName) =>
    isShowingTooltip[targetName] && (
      <TooltipInfo
        text={
          !config.isReview
            ? 'Job complete, no untranslated segments left'
            : 'Job complete, no unapproved segments left'
        }
      />
    )
  const removeTooltip = () => setIsShowingTooltip(false)

  // A dash only while the count is unknown; zero words is a real count
  const formatCount = (value) =>
    value === undefined || value === null || value === ''
      ? '-'
      : Math.round(value)

  // Weighted count in black, raw count in grey brackets, the two spelled out on hover
  // (same pattern as the words button in My projects).
  const renderCount = ({label, weighted, raw, countRef}) => (
    <Tooltip
      content={`Weighted: ${formatCount(weighted)} / Total: ${formatCount(raw)}`}
    >
      <span ref={countRef} className="footer-count">
        {label}:{' '}
        <span className="footer-count-weighted">{formatCount(weighted)}</span>{' '}
        <span className="footer-count-raw">({formatCount(raw)})</span>
      </span>
    </Tooltip>
  )

  const renderWordsStats = () => (
    <div className="footer-stat">
      {renderCount({
        label: isCJK ? 'Characters' : 'Words',
        weighted: stats?.equivalent?.total,
        raw: stats?.raw?.total,
        countRef: wordsCountRef,
      })}
      {config.allow_link_to_analysis && (
        <Tooltip content="Go to analysis page">
          <Button
            ref={goToAnalysisRef}
            className="button-icon"
            size={BUTTON_SIZE.ICON_XSMALL}
            mode={BUTTON_MODE.GHOST}
            onClick={onClickOpenJobAnalysis}
            aria-label="Go to analysis page"
          >
            <IconRedirect size={14} />
          </Button>
        </Tooltip>
      )}
    </div>
  )

  const renderToDoStats = () => {
    const valueTotal = config.isReview
      ? config.revisionNumber === REVISE_STEP_NUMBER.REVISE1
        ? stats?.revise_todo_total
        : config.revisionNumber === REVISE_STEP_NUMBER.REVISE2
          ? stats?.revise2_todo_total
          : stats?.translate_todo_total
      : stats?.translate_todo_total

    const valueWeighted = config.isReview
      ? config.revisionNumber === REVISE_STEP_NUMBER.REVISE1
        ? stats?.revise_todo
        : config.revisionNumber === REVISE_STEP_NUMBER.REVISE2
          ? stats?.revise2_todo
          : stats?.translate_todo
      : stats?.translate_todo

    return (
      <div className="footer-stat">
        {renderCount({
          label: 'To do',
          weighted: valueWeighted,
          raw: valueTotal,
          countRef: toDoCountRef,
        })}
        <Tooltip content="Go to next unconfirmed segment">
          <Button
            ref={openToDoRef}
            className="button-icon"
            size={BUTTON_SIZE.ICON_XSMALL}
            mode={BUTTON_MODE.GHOST}
            onClick={onClickTodo}
            aria-label="Go to next unconfirmed segment"
          >
            <IconArrowRight />
          </Button>
        </Tooltip>
      </div>
    )
  }

  const renderSpeedETC = () => (
    <div className="footer-stat footer-speed-etc">
      <span>
        <span className="footer-stat-label">Speed</span>{' '}
        <strong>
          {stats?.words_per_hour ?? (
            <Tooltip
              className="footer-tooltip"
              content="This information becomes available after you confirm at least ten segments since opening the job"
            >
              <span ref={speedNaRef}>N/A</span>
            </Tooltip>
          )}
        </strong>{' '}
        words/h
      </span>
      <span>
        <Tooltip
          className="footer-tooltip footer-tooltip-end"
          content="Estimated time to complete"
        >
          <span ref={etcLabelRef} className="footer-stat-label">
            ETC
          </span>
        </Tooltip>{' '}
        <strong>
          {stats?.estimated_completion ?? (
            <Tooltip
              className="footer-tooltip footer-tooltip-end"
              content="This information becomes available after you confirm at least ten segments since opening the job"
            >
              <span ref={etcNARef}>N/A</span>
            </Tooltip>
          )}
        </strong>
      </span>
    </div>
  )

  return (
    <footer className="stats-foo">
      <div className="footer-body">
        <div className="container-left">
          <p id="job_id">
            <span className="footer-stat-label">Job ID</span>{' '}
            <span className="footer-stat-label">{idJob}</span>
          </p>
        </div>
        <div className="container-center">
          <div className="container-progress-bar">
            <div
              className="language"
              data-testid="language-pair"
              title={`${sourceLang} → ${targetLang}`}
            >
              <span>{source}</span>
              <span className="to-arrow"> &#8594; </span>
              <span id="footer-target-lang">{target}</span>
            </div>
            <div className="footer-progress" onMouseLeave={removeTooltip}>
              <JobProgressBar
                stats={stats}
                showPercent={true}
                analysisComplete={stats?.analysis_complete}
              />
              {getTooltip('progressBar')}
            </div>
          </div>
        </div>
        <div className="container-right">
          {renderWordsStats()}
          <span className="footer-divider" />
          {renderToDoStats()}
          <span className="footer-divider" />
          {renderSpeedETC()}
        </div>
      </div>
      <CookieConsent />
    </footer>
  )
}
