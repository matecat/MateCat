import React, {useState} from 'react'

import CatToolStore from '../../stores/CatToolStore'
import CatToolConstants from '../../constants/CatToolConstants'
import TooltipInfo from '../segments/TooltipInfo/TooltipInfo.component'
import SegmentActions from '../../actions/SegmentActions'
import {CookieConsent} from '../common/CookieConsent'
import {REVISE_STEP_NUMBER} from '../../constants/Constants'
import JobProgressBar from '../common/JobProgressBar'
import {isUndefined} from 'lodash'

export const CattolFooter = ({
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

  let sourceLang = languagesArray.find((item) => item.code == source)?.name
  let targetLang = languagesArray.find((item) => item.code == target)?.name

  if (!sourceLang) {
    sourceLang = source
  }

  if (!targetLang) {
    targetLang = target
  }

  React.useEffect(() => {
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

  return (
    <footer className="stats-foo">
      <div className="footer-body">
        <div className="item">
          <p id="job_id">
            Job ID: <span>{idJob}</span>
          </p>
        </div>

        <div className="item language" data-testid="language-pair">
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

        <div className="item">
          <div className="statistics-core">
            <div id="stat-eqwords">
              {config.allow_link_to_analysis ? (
                <a
                  target="_blank"
                  rel="noreferrer"
                  href={
                    '/jobanalysis/' + idProject + '-' + idJob + '-' + password
                  }
                >
                  {!isCJK ? (
                    <span>Weighted words</span>
                  ) : (
                    <span>Characters</span>
                  )}
                </a>
              ) : (
                <a target="_blank">
                  {!isCJK ? (
                    <span>Weighted words</span>
                  ) : (
                    <span>Characters</span>
                  )}
                </a>
              )}
              :
              <strong id="total-payable">
                {' '}
                {stats ? Math.round(stats.equivalent.total) : '-'}
              </strong>
            </div>
          </div>
        </div>

        <div
          className="item"
          onClick={(e) => onClickTodo(e, 'todo')}
          onMouseLeave={removeTooltip}
        >
          {config.isReview &&
          config.revisionNumber === REVISE_STEP_NUMBER.REVISE1 ? (
            <div id="stat-todo">
              <span>To-do</span> :{' '}
              <strong>
                {stats && !isUndefined(stats.revise_todo)
                  ? stats.revise_todo
                  : '-'}
              </strong>
            </div>
          ) : config.isReview &&
            config.revisionNumber === REVISE_STEP_NUMBER.REVISE2 ? (
            <div id="stat-todo">
              <span>To-do</span> :{' '}
              <strong>
                {stats && !isUndefined(stats.revise2_todo)
                  ? stats.revise2_todo
                  : '-'}
              </strong>
            </div>
          ) : (
            <div id="stat-todo">
              <span>To-do</span> :{' '}
              <strong>
                {stats && !isUndefined(stats.translate_todo)
                  ? stats.translate_todo
                  : '-'}
              </strong>
            </div>
          )}
          {getTooltip('todo')}
        </div>

        {stats && stats.analysis_complete && (
          <div className="statistics-details">
            {stats?.words_per_hour && (
              <div id="stat-wph" title="Based on last 10 segments performance">
                Speed:
                <strong>{stats.words_per_hour}</strong> Words/h
              </div>
            )}

            {stats?.estimated_completion && (
              <div id="stat-completion">
                Completed in:
                <strong>{stats.estimated_completion}</strong>
              </div>
            )}
          </div>
        )}

        {!stats?.analysis_complete && (
          <div id="analyzing">
            <p className="progress">Calculating word count...</p>
          </div>
        )}
      </div>
      <CookieConsent />
    </footer>
  )
}
