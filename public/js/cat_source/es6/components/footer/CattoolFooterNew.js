import React, {useEffect, useState} from 'react'
import CatToolConstants from '../../constants/CatToolConstants'
import CatToolStore from '../../stores/CatToolStore'
import SegmentActions from '../../actions/SegmentActions'
import TooltipInfo from '../segments/TooltipInfo/TooltipInfo.component'
import JobProgressBar from '../common/JobProgressBar'
import {BUTTON_MODE, BUTTON_SIZE, Button} from '../common/Button/Button'
import ArrowDown from '../../../../../img/icons/ArrowDown'
import {REVISE_STEP_NUMBER} from '../../constants/Constants'

export const CattoolFooterNew = ({
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
    todo: false,
  })

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
      setIsShowingTooltip({progressBar: false, todo: false, [targetName]: true})
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
          <span>Total words:</span>
          {stats ? Math.round(stats.raw.total) : '-'}{' '}
          {config.allow_link_to_analysis && (
            <Button
              className="button-icon"
              size={BUTTON_SIZE.ICON_SMALL}
              mode={BUTTON_MODE.GHOST}
              onClick={onClickOpenJobAnalysis}
            >
              <ArrowDown />
            </Button>
          )}
        </div>
        <div className="grey-box-row">
          <span>{label}:</span>
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
          <span>To do:</span>
          {valueTotal ? Math.round(valueTotal) : '-'}{' '}
          <Button
            className="button-icon"
            size={BUTTON_SIZE.ICON_SMALL}
            mode={BUTTON_MODE.GHOST}
            onClick={onClickTodo}
          >
            <ArrowDown />
          </Button>
        </div>
        <div className="grey-box-row">
          <span>{label}</span>
          {valueWeighted ? Math.round(valueWeighted) : '-'}{' '}
        </div>
      </>
    )
  }

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
        </div>
        <div className="container-right">
          <div className="grey-box">
            <div className="grey-box-row">
              <span>Speed:</span>N/A
            </div>
            <div className="grey-box-row">
              <span>ETC:</span>N/A
            </div>
          </div>
        </div>
      </div>
    </footer>
  )
}
