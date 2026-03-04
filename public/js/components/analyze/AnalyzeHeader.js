import React, {useRef, useEffect, useCallback} from 'react'
import {TransitionGroup, CSSTransition} from 'react-transition-group'
import {ANALYSIS_STATUS} from '../../constants/Constants'
import {Popup} from 'semantic-ui-react'
import HelpCircle from '../../../img/icons/HelpCircle'
import {downloadAnalysisReport} from '../../api/downloadAnalysisReport'

const AnalyzeHeader = ({data, project}) => {
  const previousQueueSizeRef = useRef(0)
  const lastProgressSegmentsRef = useRef(0)
  const noProgressTailRef = useRef(0)

  const containerAnalysisCompleteRef = useRef(null)

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
        <div className="search-tm-matches">
          <span className="complete">{analyzerNotRunningErrorString}</span>
        </div>
      </div>
    )
  }, [])

  const getProgressBarText = useCallback(() => {
    return (
      <div className="analysis-create">
        <div className="search-tm-matches">
          <h5>Searching for TM Matches </h5>
          <span className="initial-segments">
            {' '}
            ({data.get('segments_analyzed')} of{' '}
          </span>
          <span className="total-segments">
            {' '}
            {' ' + data.get('total_segments')})
          </span>
        </div>
      </div>
    )
  }, [data])

  const handleDownloadAnalysisReport = useCallback(() => {
    downloadAnalysisReport({
      idProject: project.get('id'),
      password: project.get('password'),
    }).catch((error) => {
      console.error('Error downloading analysis report:', error)
    })
  }, [project])

  const getAnalysisStateHtml = useCallback(() => {
    showProgressBarRef.current = false

    let html = (
      <div className="analysis-create">
        <div className="search-tm-matches">
          <div className="ui active inline loader" />
          <div className="complete">Fast word counting...</div>
        </div>
      </div>
    )
    const status = data.get('status')
    const in_queue_before = parseInt(data.get('in_queue_before'))
    if (status === 'DONE') {
      html = (
        <div className="analysis-create">
          <div
            className="search-tm-matches hide"
            ref={containerAnalysisCompleteRef}
          >
            <h5 className="complete">
              Analysis:
              <span>
                complete <i className="icon-checkmark icon" />
              </span>
            </h5>
            <a
              className="downloadAnalysisReport"
              onClick={handleDownloadAnalysisReport}
            >
              Download Analysis Report
            </a>
          </div>
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
              <div className="search-tm-matches">
                <div
                  style={{top: '-12px'}}
                  className="ui active inline loader right-15"
                />
                <span className="complete">
                  Please wait...{' '}
                  <p className="label">There are other projects in queue. </p>
                </span>
              </div>
            </div>
          )
        } else {
          html = (
            <div className="analysis-create">
              <div className="search-tm-matches">
                <div
                  style={{top: '-12px'}}
                  className="ui active inline loader right-15"
                />
                <span className="complete">
                  Please wait...
                  <p className="label">
                    There are still{' '}
                    <span className="number">
                      {data.get('in_queue_before')}
                    </span>{' '}
                    segments in queue.
                  </p>
                </span>
              </div>
            </div>
          )
        }
      } else {
        html = (
          <div className="analysis-create">
            <div className="search-tm-matches">
              <div
                style={{top: '-12px'}}
                className="ui active inline loader right-15"
              />
              <span className="complete">
                Please wait...
                <p className="label">There are other projects in queue. </p>
              </span>
            </div>
          </div>
        )
      }
      previousQueueSizeRef.current = in_queue_before
    } else if (status === 'FAST_OK' && in_queue_before === 0) {
      if (lastProgressSegmentsRef.current !== data.get('segments_analyzed')) {
        lastProgressSegmentsRef.current = data.get('segments_analyzed')
        noProgressTailRef.current = 0
        showProgressBarRef.current = true
        html = getProgressBarText()
      } else {
        noProgressTailRef.current++
        if (noProgressTailRef.current > 9) {
          html = errorAnalysisHtml()
        }
      }
    } else if (status === 'NOT_TO_ANALYZE') {
      html = (
        <div className="analysis-create">
          <div className="search-tm-matches">
            <div className="complete">
              We are having issues with the analysis of this project.
            </div>
            <div className="analysisNotPerformed">
              {' '}
              Please contact us at{' '}
              <a href="mailto: + config.support_mail + ">
                {config.support_mail}{' '}
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
          <div className="search-tm-matches">
            <span className="complete">
              Ops.. we got an error. No text to translate in the file{' '}
              {data.get('NAME')}.
            </span>
            <br />
            <span className="analysisNotPerformed">Contact {error}</span>
          </div>
        </div>
      )
    } else {
      html = errorAnalysisHtml()
    }
    return html
  }, [
    data,
    errorAnalysisHtml,
    getProgressBarText,
    handleDownloadAnalysisReport,
  ])

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
      <div className="word-count ui grid">
        <div className="sixteen wide column">
          <div className="word-percent ">
            <h2 className="ui header">
              <div className="percent">{saving_perc}</div>
              <div className="content">
                Saving on word count
                <div className="sub header">
                  {getSavingWorkCount()} at 3.000 w/day
                </div>
              </div>
            </h2>
            <p>
              Matecat gives you more matches than any other tool thanks to a
              better integration of machine translation and translation
              memories.
              <Popup
                content={tooltipText}
                position="bottom center"
                trigger={
                  <span
                    style={{
                      marginLeft: '2px',
                      color: '#4184c4',
                      cursor: 'pointer',
                      verticalAlign: '-2px',
                    }}
                  >
                    <HelpCircle />
                  </span>
                }
              />
            </p>
          </div>
        </div>
      </div>
    )
  }, [data, getSavingWorkCount])

  /**
   * To add informations from the plugins
   * @returns {string}
   */
  const moreProjectInfo = useCallback(() => {
    return ''
  }, [])

  const getProgressBar = useCallback(() => {
    if (showProgressBarRef.current) {
      const width =
        (data.get('segments_analyzed') / data.get('total_segments')) * 100 + '%'
      return (
        <div className="progress sixteen wide column">
          <TransitionGroup>
            <CSSTransition
              key={0}
              classNames="transition"
              timeout={{enter: 500, exit: 300}}
            >
              <div className="progress-bar">
                <div className="progr">
                  <div className="meter">
                    <a className="approved-bar" style={{width: width}} />
                  </div>
                </div>
              </div>
            </CSSTransition>
          </TransitionGroup>
        </div>
      )
    }
    return null
  }, [data])

  // Replaces componentDidMount + componentDidUpdate
  const prevDataRef = useRef(data)
  const isFirstRender = useRef(true)

  useEffect(() => {
    const status = data.get('status')

    if (isFirstRender.current) {
      // componentDidMount logic
      if (status === ANALYSIS_STATUS.DONE) {
        setTimeout(() => {
          containerAnalysisCompleteRef.current?.classList.remove('hide')
        }, 400)
      }
      isFirstRender.current = false
    } else {
      // componentDidUpdate logic
      if (status === ANALYSIS_STATUS.DONE) {
        setTimeout(() => {
          containerAnalysisCompleteRef.current?.classList.remove('hide')
        }, 600)
      }
    }

    prevDataRef.current = data
  }, [data])

  const analysisStateHtml = getAnalysisStateHtml()
  const wordsCountHtml = getWordscount()
  const projectName = project.get('name') ? project.get('name') : ''

  return (
    <div className="project-header ui grid">
      <div className="left-analysis nine wide column">
        <h1>Volume Analysis</h1>
        <div className="ui ribbon label">
          <div className="project-name" title="Project name">
            {' '}
            {projectName}{' '}
          </div>
        </div>
        {moreProjectInfo()}
        {analysisStateHtml}
      </div>

      <div className="seven wide right floated column">{wordsCountHtml}</div>

      {getProgressBar()}
    </div>
  )
}

export default React.memo(AnalyzeHeader, (prevProps, nextProps) => {
  return nextProps.data.equals(prevProps.data)
})
