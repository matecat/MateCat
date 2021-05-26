import React from 'react'
import _ from 'lodash'

import CatToolStore from '../../stores/CatToolStore'
import CattoolConstants from '../../constants/CatToolConstants'

const transformStats = (stats) => {
  let reviewWordsSecondPass
  let a_perc_2nd_formatted
  let a_perc_2nd

  const t_perc = stats.TRANSLATED_PERC
  let a_perc = stats.APPROVED_PERC
  const d_perc = stats.DRAFT_PERC
  const r_perc = stats.REJECTED_PERC

  const t_perc_formatted = stats.TRANSLATED_PERC_FORMATTED
  let a_perc_formatted = stats.APPROVED_PERC_FORMATTED
  const d_perc_formatted = stats.DRAFT_PERC_FORMATTED
  const r_perc_formatted = stats.REJECTED_PERC_FORMATTED

  let revise_todo_formatted = Math.round(stats.TRANSLATED + stats.DRAFT)

  if (config.secondRevisionsCount && stats.revises) {
    const reviewedWords = stats.revises.find(
      (value) => value.revision_number === 1,
    )

    if (reviewedWords) {
      let approvePerc =
        (parseFloat(reviewedWords.advancement_wc) * 100) / stats.TOTAL
      approvePerc =
        approvePerc > stats.APPROVED_PERC ? stats.APPROVED_PERC : approvePerc
      a_perc_formatted = approvePerc < 0 ? 0 : _.round(approvePerc, 1)
      a_perc = approvePerc
    }

    reviewWordsSecondPass = stats.revises.find(
      (value) => value.revision_number === 2,
    )

    if (reviewWordsSecondPass) {
      let approvePerc2ndPass =
        (parseFloat(reviewWordsSecondPass.advancement_wc) * 100) / stats.TOTAL
      approvePerc2ndPass =
        approvePerc2ndPass > stats.APPROVED_PERC
          ? stats.APPROVED_PERC
          : approvePerc2ndPass
      a_perc_2nd_formatted =
        approvePerc2ndPass < 0 ? 0 : _.round(approvePerc2ndPass, 1)
      a_perc_2nd = approvePerc2ndPass
      revise_todo_formatted =
        config.revisionNumber === 2
          ? revise_todo_formatted +
            _.round(parseFloat(reviewedWords.advancement_wc))
          : revise_todo_formatted
    }
  }

  stats.a_perc_formatted = a_perc_formatted
  stats.a_perc = a_perc
  stats.t_perc_formatted = t_perc_formatted
  stats.t_perc = t_perc
  stats.d_perc_formatted = d_perc_formatted
  stats.d_perc = d_perc
  stats.r_perc_formatted = r_perc_formatted
  stats.r_perc = r_perc
  stats.a_perc_2nd_formatted = a_perc_2nd_formatted
  stats.a_perc_2nd = a_perc_2nd
  stats.revise_todo_formatted =
    revise_todo_formatted >= 0 ? revise_todo_formatted : 0

  return stats
}

export const CattolFooter = ({
  idProject,
  idJob,
  password,
  languagesArray,
  source,
  target,
  isCJK,
  isReview,
}) => {
  const [stats, setStats] = React.useState()
  const sourceLang = languagesArray.find((item) => item.code == source).name
  const targetLang = languagesArray.find((item) => item.code == target).name

  React.useEffect(() => {
    const listener = (stats) => {
      setStats(transformStats(stats))
    }

    CatToolStore.addListener(CattoolConstants.SET_PROGRESS, listener)

    return () => {
      CatToolStore.removeListener(CattoolConstants.SET_PROGRESS, listener)
    }
  }, [])

  return (
    <footer className="footer-body">
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

      <div className="progress-bar" data-testid="progress-bar">
        <div className="meter" style={{width: '100%', position: 'relative'}}>
          {stats == null ? (
            <div className="bg-loader" />
          ) : !stats?.ANALYSIS_COMPLETE ? null : (
            <>
              <a
                href="#"
                className="approved-bar"
                style={{width: stats.a_perc + '%'}}
                title={'Approved ' + stats.a_perc_formatted}
              />
              <a
                href="#"
                className="approved-bar-2nd-pass"
                style={{width: stats.a_perc_2nd + '%'}}
                title={'2nd Approved ' + stats.a_perc_2nd_formatted}
              />
              <a
                href="#"
                className="translated-bar"
                style={{width: stats.t_perc + '%'}}
                title={'Translated ' + stats.t_perc_formatted}
              />
              <a
                href="#"
                className="rejected-bar"
                style={{width: stats.r_perc + '%'}}
                title={'Rejected ' + stats.r_perc_formatted}
              />
              <a
                href="#"
                className="draft-bar"
                style={{width: stats.d_perc + '%'}}
                title={'Draft ' + stats.d_perc_formatted}
              />
            </>
          )}
        </div>

        <div className="percent">
          <span id="stat-progress" data-testid="progress-bar-amount">
            {stats?.PROGRESS_PERC_FORMATTED || '-'}
          </span>
          %
        </div>
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
                {!isCJK ? <span>Weighted words</span> : <span>Characters</span>}
              </a>
            ) : (
              <a target="_blank">
                {!isCJK ? <span>Weighted words</span> : <span>Characters</span>}
              </a>
            )}
            :
            <strong id="total-payable"> {stats?.TOTAL_FORMATTED || '-'}</strong>
          </div>
        </div>
      </div>

      <div className="item">
        {isReview ? (
          <div id="stat-todo">
            <span>To-do</span> :{' '}
            <strong>{stats?.revise_todo_formatted || '-'}</strong>
          </div>
        ) : (
          <div id="stat-todo">
            <span>To-do</span> : <strong>{stats?.TODO_FORMATTED || '-'}</strong>
          </div>
        )}
      </div>

      {!!stats && (
        <div className="statistics-details">
          {!!stats?.WORDS_PER_HOUR && (
            <div id="stat-wph" title="Based on last 10 segments performance">
              Speed:
              <strong>{stats.WORDS_PER_HOUR}</strong> Words/h
            </div>
          )}

          {!!stats?.ESTIMATED_COMPLETION && (
            <div id="stat-completion">
              Completed in:
              <strong>{stats.ESTIMATED_COMPLETION}</strong>
            </div>
          )}
        </div>
      )}

      {!stats?.ANALYSIS_COMPLETE && (
        <div id="analyzing">
          <p className="progress">Calculating word count...</p>
        </div>
      )}
    </footer>
  )
}
