import React, {useEffect, useRef, useState} from 'react'
import CatToolConstants from '../../constants/CatToolConstants'
import CatToolStore from '../../stores/CatToolStore'
import SegmentActions from '../../actions/SegmentActions'
import TooltipInfo from '../segments/TooltipInfo/TooltipInfo.component'
import JobProgressBar from '../common/JobProgressBar'
import {BUTTON_MODE, BUTTON_SIZE, Button} from '../common/Button/Button'
import {REVISE_STEP_NUMBER} from '../../constants/Constants'
import {CookieConsent} from '../common/CookieConsent'
import IconRedirect from '../icons/IconRedirect'
import Tooltip, {TOOLTIP_POSITION} from '../common/Tooltip'
import IconArrowRight from '../icons/IconArrowRight'

export const CattoolFooter = ({
  idProject,
  idJob,
  password,
  languagesArray,
  source,
  target,
  isCJK,
}) => {
  const [stats, setStats] = React.useState()
  const [isShowingTooltip, setIsShowingTooltip] = useState({
    progressBar: false,
  })

  const goToAnalysisRef = useRef()
  const openToDoRef = useRef()
  const etcLabelRef = useRef()
  const etcNARef = useRef()
  const speedNaRef = useRef()

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

  const renderWordsStats = () => {
    const label = isCJK ? 'Characters' : 'Weighted'

    return (
      <>
        <div className="grey-box-row">
          <span>Total words</span>
          {stats ? Math.round(stats.raw.total) : '-'}{' '}
          {config.allow_link_to_analysis && (
            <Tooltip content="Go to analysis page">
              <Button
                ref={goToAnalysisRef}
                className="button-icon"
                size={BUTTON_SIZE.ICON_SMALL}
                mode={BUTTON_MODE.GHOST}
                onClick={onClickOpenJobAnalysis}
              >
                <IconRedirect />
              </Button>
            </Tooltip>
          )}
        </div>
        <div className="grey-box-row">
          <span>{label}</span>
          {stats ? Math.round(stats.equivalent.total) : '-'}{' '}
        </div>
      </>
    )
  }

  const renderToDoStats = () => {
    const label = isCJK ? 'Characters' : 'Weighted'

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
      <>
        <div className="grey-box-row">
          <span>To do</span>
          {valueTotal ? Math.round(valueTotal) : '-'}{' '}
          <Tooltip content="Go to next unconfirmed segment">
            <Button
              ref={openToDoRef}
              className="button-icon"
              size={BUTTON_SIZE.ICON_SMALL}
              mode={BUTTON_MODE.GHOST}
              onClick={onClickTodo}
            >
              <IconArrowRight />
            </Button>
          </Tooltip>
        </div>
        <div className="grey-box-row">
          <span>{label}</span>
          {valueWeighted ? Math.round(valueWeighted) : '-'}{' '}
        </div>
      </>
    )
  }

  const renderSpeedETC = () => (
    <>
      <div className="grey-box-row">
        <span>Speed</span>
        {stats?.words_per_hour ?? (
          <Tooltip
            content="This information becomes available after you confirm at least ten segments since opening the job"
            position={TOOLTIP_POSITION.LEFT}
          >
            <span ref={speedNaRef}>N/A</span>
          </Tooltip>
        )}{' '}
        <span>Words/h</span>
      </div>
      <div className="grey-box-row">
        <Tooltip content="Estimated time to complete">
          <span ref={etcLabelRef}>ETC</span>
        </Tooltip>
        {stats?.estimated_completion ?? (
          <Tooltip
            content="This information becomes available after you confirm at least ten segments since opening the job"
            position={TOOLTIP_POSITION.LEFT}
          >
            <span ref={etcNARef}>N/A</span>
          </Tooltip>
        )}
      </div>
    </>
  )

  return (
    <footer className="stats-foo">
      <div className="footer-body">
        <div className="container-left">
          <p id="job_id">
            Job ID: <span>{idJob}</span>
          </p>
        </div>
        <div className="container-center">
          <div className="container-progress-bar">
            <div className="language" data-testid="language-pair">
              <p>
                <span>{sourceLang}</span>
                <span className="to-arrow"> &#8594; </span>
                <span id="footer-target-lang">{targetLang}</span>
              </p>
            </div>
            <div onMouseLeave={removeTooltip}>
              <JobProgressBar
                stats={stats}
                showPercent={true}
                analysisComplete={stats?.analysis_complete}
              />
              {getTooltip('progressBar')}
            </div>
          </div>

          <div className="grey-box">{renderWordsStats()}</div>
          <div className="grey-box">{renderToDoStats()}</div>
          <div className="grey-box grey-box-speed-etc">{renderSpeedETC()}</div>
        </div>
      </div>
      <CookieConsent />
    </footer>
  )
}
