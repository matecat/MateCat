import React, {useRef, useEffect, useCallback} from 'react'
import {ANALYSIS_STATUS} from '../../constants/Constants'
import {Popup} from 'semantic-ui-react'
import HelpCircle from '../../../img/icons/HelpCircle'
import {downloadAnalysisReport} from '../../api/downloadAnalysisReport'
import {PROGRESS_BAR_SIZE, ProgressBar} from '../common/ProgressBar'
import {Badge, BADGE_TYPE} from '../common/Badge'
import Check from '../../../img/icons/Check'
import Download from '../../../img/icons/Download'
import InfoIcon from '../../../img/icons/InfoIcon'

const AnalyzeHeader = ({data, project}) => {
  const previousQueueSizeRef = useRef(0)
  const lastProgressSegmentsRef = useRef(0)
  const noProgressTailRef = useRef(0)

  const showProgressBarRef = useRef(false)

  const errorAnalysisHtml = useCallback(() => {
    let analyzerNotRunningErrorString
    if (config.support_mail.indexOf('@') === -1) {
      analyzerNotRunningErrorString = (
        <p className="label">
          The analysis seems not to be running. Contact {config.support_mail}.
        </p>
      )
    } else {
      analyzerNotRunningErrorString = (
        <p className="label">
          The analysis seems not to be running. Contact{' '}
          <a href={'mailto: ' + config.support_mail}>{config.support_mail}</a>.
        </p>
      )
    }
    return (
      <div className="analysis-create">
        <span className="not-complete">{analyzerNotRunningErrorString}</span>
      </div>
    )
  }, [])

  const handleDownloadAnalysisReport = useCallback(() => {
    downloadAnalysisReport({
      idProject: project.get('id'),
      password: project.get('password'),
    }).catch((error) => {
      console.error('Error downloading analysis report:', error)
    })
  }, [project])

  let getAnalysisStateHtml
  getAnalysisStateHtml = useCallback(() => {
    showProgressBarRef.current = false

    let html = (
      <div className="analysis-create">
        <div className="ui active inline loader" />
        <div className="complete">Fast word counting...</div>
      </div>
    )
    let status = data.get('status')
    const in_queue_before = parseInt(data.get('in_queue_before'))
    if (status === 'DONE') {
      html = (
        <div className="analysis-create">
          <div className="complete">
            Analysis:
            <Badge type={BADGE_TYPE.GREEN}>
              <Check size={20} />
              Complete
            </Badge>
          </div>
          <a className={'downloadAnalysisReport'}>
            Download Analysis Report
            <Download size={16} />
          </a>
        </div>
      )
    } else if (
      status === ANALYSIS_STATUS.NEW ||
      status === ANALYSIS_STATUS.BUSY ||
      status === '' ||
      in_queue_before > 0
    ) {
      if (config.daemon_warning) {
        html = errorAnalysisHtml()
      } else if (in_queue_before > 0) {
        if (previousQueueSizeRef.current <= in_queue_before) {
          html = (
            <div className="analysis-create">
              <div className="ui active inline loader right-15" />
              <span className="not-complete">
                Please wait...{' '}
                <p className="label">There are other projects in queue. </p>
              </span>
            </div>
          )
        } else {
          html = (
            <div className="analysis-create">
              <div className="ui active inline loader right-15" />
              <div className="not-complete">
                Please wait...
                <p className="label">
                  There are still{' '}
                  <span className="number">{data.get('in_queue_before')}</span>{' '}
                  segments in queue.
                </p>
              </div>
            </div>
          )
        }
      } else {
        html = (
          <div className="analysis-create">
            <div className="ui active inline loader right-15" />
            <div className="not-complete">
              Please wait...
              <p className="label">There are other projects in queue. </p>
            </div>
          </div>
        )
      }
      previousQueueSizeRef.current = in_queue_before
    } else if (status === ANALYSIS_STATUS.FAST_OK && in_queue_before === 0) {
      if (lastProgressSegmentsRef.current !== data.get('segments_analyzed')) {
        lastProgressSegmentsRef.current = data.get('segments_analyzed')
        noProgressTailRef.current = 0
        showProgressBarRef.current = true
        html = getProgressBar()
      } else {
        noProgressTailRef.current++
        if (noProgressTailRef.current > 9) {
          html = errorAnalysisHtml()
        }
      }
    } else if (status === ANALYSIS_STATUS.NOT_TO_ANALYZE) {
      html = (
        <div className="analysis-create">
          <div className="not-complete">
            We are having issues with the analysis of this project.
            <div className="analysisNotPerformed">
              Please contact us at{' '}
              <a href="mailto: + config.support_mail + ">
                {config.support_mail}
              </a>{' '}
              for more information.
            </div>
          </div>
        </div>
      )
    } else if (status === ANALYSIS_STATUS.EMPTY) {
      let error = ''
      if (config.support_mail.indexOf('@') === -1) {
        error = config.support_mail
      } else {
        error = (
          <a href="mailto: + config.support_mail + "> {config.support_mail} </a>
        )
      }

      html = (
        <div className="analysis-create">
          <div className="not-complete">
            Ops.. we got an error. No text to translate in the file .
            <div className="analysisNotPerformed">Contact {error}</div>
          </div>
        </div>
      )
    } else {
      html = errorAnalysisHtml()
    }
    return html
  }, [data, errorAnalysisHtml, getProgressBar])

  const getSavingWorkCount = useCallback(() => {
    const dataJS = data.toJS()
    const {total_equivalent} = dataJS
    let wcTime = total_equivalent / 3000
    let wcUnit = 'day'
    if (wcTime > 0 && wcTime < 1) {
      wcTime = wcTime * 8
      wcUnit = 'hour'
    }
    if (wcTime > 0 && wcTime < 1) {
      wcTime = wcTime * 60
      wcUnit = 'minute'
    }
    if (wcTime > 1) {
      wcUnit = wcUnit + 's'
    }
    return Math.round(wcTime) + ' work ' + wcUnit
  }, [data])

  const getWordscount = useCallback(() => {
    const tooltipText = (
      <span>
        Matecat suggests MT only when it helps thanks to a dynamic penalty
        system. We learn when to offer machine translation suggestions or
        translation memory matches thanks to the millions of words corrected by
        the Matecat community.
        <br /> This data is also used to define a fair pricing scheme that
        splits the benefits of the technology between the customer and the
        translator.
      </span>
    )

    const status = data.get('status')
    let raw_words = data.get('total_raw'),
      weightedWords = ''
    if (
      (status === ANALYSIS_STATUS.NEW ||
        status === '' ||
        data.get('in_queue_before') > 0) &&
      config.daemon_warning
    ) {
      weightedWords = data.get('total_raw')
    } else {
      if (status === ANALYSIS_STATUS.DONE || data.get('total_equivalent') > 0) {
        weightedWords = data.get('total_equivalent')
      }
      if (status === ANALYSIS_STATUS.NOT_TO_ANALYZE) {
        weightedWords = data.get('total_raw')
      }
    }
    let saving_perc =
      raw_words > 0
        ? parseInt(((raw_words - weightedWords) / raw_words) * 100) + '%'
        : '0%'

    return (
      <div className="word-count">
        <div className="percent">
          <h2>{saving_perc}</h2>
          <div className="content">
            Saving on word count
            <div className="work-hour">
              {getSavingWorkCount()} at 3.000 w/day
            </div>
          </div>
        </div>
        <Popup
          content={tooltipText}
          position="bottom center"
          trigger={
            <div>
              <InfoIcon />
            </div>
          }
        />
      </div>
    )
  }, [data, getSavingWorkCount])

  const getProgressBar = useCallback(() => {
    if (showProgressBarRef.current) {
      const progress =
        (data.get('segments_analyzed') / data.get('total_segments')) * 100
      return (
        <div className="analysis-create">
          <ProgressBar
            total={100}
            progress={progress}
            size={PROGRESS_BAR_SIZE.SMALL}
            label={
              <div>
                Searching for TM Matches
                <span className="initial-segments">
                  {' '}
                  ({data.get('segments_analyzed')} of{' '}
                </span>
                <span className="total-segments">
                  {' '}
                  {data.get('total_segments')})
                </span>
              </div>
            }
            className={'analysis-progressbar'}
          />
        </div>
      )
    }
    return null
  }, [data])

  const analysisStateHtml = getAnalysisStateHtml()
  const wordsCountHtml = getWordscount()
  const projectName = project.get('name') ? project.get('name') : ''

  return (
    <div className="project-header">
      <div className="left-analysis">
        <div className="project-name" title="Project name">
          <h5>{projectName}</h5>
        </div>
        {analysisStateHtml}
      </div>
      {wordsCountHtml}
    </div>
  )
}

export default React.memo(AnalyzeHeader, (prevProps, nextProps) => {
  return nextProps.data.equals(prevProps.data)
})
