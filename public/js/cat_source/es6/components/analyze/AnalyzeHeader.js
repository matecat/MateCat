import React from 'react'
import {TransitionGroup, CSSTransition} from 'react-transition-group'
import {ANALYSIS_STATUS, SEGMENTS_STATUS} from '../../constants/Constants'

class AnalyzeHeader extends React.Component {
  constructor(props) {
    super(props)
    this.previousQueueSize = 0
    this.lastProgressSegments = 0
    this.noProgressTail = 0
    this.state = {}
  }

  getAnalysisStateHtml() {
    this.showProgressBar = false

    let html = (
      <div className="analysis-create">
        <div className="search-tm-matches">
          <div className="ui active inline loader" />
          <div className="complete">Fast word counting...</div>
        </div>
      </div>
    )
    let status = this.props.data.get('status')
    let in_queue_before = parseInt(this.props.data.get('in_queue_before'))
    if (status === 'DONE') {
      html = (
        <div className="analysis-create">
          <div
            className="search-tm-matches hide"
            ref={(container) => (this.containerAnalysisComplete = container)}
          >
            <h5 className="complete">
              Analysis:
              <span>
                complete <i className="icon-checkmark icon" />
              </span>
            </h5>
            <a
              className="downloadAnalysisReport"
              onClick={this.downloadAnalysisReport.bind(this)}
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
        html = this.errorAnalysisHtml()
      } else if (in_queue_before > 0) {
        if (this.previousQueueSize <= in_queue_before) {
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
          //decreasing ( TM analysis on another project )
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
                      {this.props.data.get('in_queue_before')}
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
      this.previousQueueSize = in_queue_before
    } else if (status === 'FAST_OK' && in_queue_before === 0) {
      if (
        this.lastProgressSegments !== this.props.data.get('segments_analyzed')
      ) {
        this.lastProgressSegments = this.props.data.get('segments_analyzed')
        this.noProgressTail = 0
        this.showProgressBar = true
        html = this.getProgressBarText()
      } else {
        this.noProgressTail++
        if (this.noProgressTail > 9) {
          html = this.errorAnalysisHtml()
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
              {this.props.data.get('NAME')}.
            </span>
            <br />
            <span className="analysisNotPerformed">Contact {error}</span>
          </div>
        </div>
      )
    } else {
      // Unknown error :)
      html = this.errorAnalysisHtml()
    }
    return html
  }

  errorAnalysisHtml() {
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
  }

  getProgressBarText() {
    return (
      <div className="analysis-create">
        <div className="search-tm-matches">
          <h5>Searching for TM Matches </h5>
          <span className="initial-segments">
            {' '}
            ({this.props.data.get('segments_analyzed')} of{' '}
          </span>
          <span className="total-segments">
            {' '}
            {' ' + this.props.data.get('total_segments')})
          </span>
        </div>
      </div>
    )
  }

  getProgressBar() {
    if (this.showProgressBar) {
      let width =
        (this.props.data.get('segments_analyzed') /
          this.props.data.get('total_segments')) *
          100 +
        '%'
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
                    <a
                      className="approved-bar translate-tooltip"
                      data-html={'Approved ' + width}
                      style={{width: width}}
                    />
                  </div>
                </div>
              </div>
            </CSSTransition>
          </TransitionGroup>
        </div>
      )
    }
    return null
  }
  getSavingWorkCount() {
    const data = this.props.data.toJS()
    const {total_equivalent} = data
    let wcTime = total_equivalent / 3000
    let wcUnit = 'day'
    if (wcTime > 0 && wcTime < 1) {
      wcTime = wcTime * 8 //convert to hours (1 work day = 8 hours)
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
  }
  getWordscount() {
    let tooltipText =
      'Matecat suggests MT only when it helps thanks to a dynamic penalty system. We learn when to ' +
      'offer machine translation suggestions or translation memory matches thanks to the millions ' +
      'of words corrected by the Matecat community.<br> This data is also used to define a fair pricing ' +
      'scheme that splits the benefits of the technology between the customer and the translator.'

    let status = this.props.data.get('status')
    let raw_words = this.props.data.get('total_raw'),
      weightedWords = ''
    if (
      (status === ANALYSIS_STATUS.NEW ||
        status === '' ||
        this.props.data.get('in_queue_before') > 0) &&
      config.daemon_warning
    ) {
      weightedWords = this.props.data.get('total_raw')
    } else {
      if (
        status === ANALYSIS_STATUS.DONE ||
        this.props.data.get('total_equivalent') > 0
      ) {
        weightedWords = this.props.data.get('total_equivalent')
      }
      if (status === ANALYSIS_STATUS.NOT_TO_ANALYZE) {
        weightedWords = this.props.data.get('total_raw')
      }
    }
    let saving_perc =
      raw_words > 0
        ? parseInt(((raw_words - weightedWords) / raw_words) * 100) + '%'
        : '0%'
    if (saving_perc !== this.saving_perc_value) {
      this.updatedSavingWords = true
    }
    this.saving_perc_value = saving_perc

    return (
      <div className="word-count ui grid">
        <div className="sixteen wide column">
          <div
            className="word-percent "
            ref={(container) => (this.containerSavingWords = container)}
          >
            <h2 className="ui header">
              <div className="percent">{saving_perc}</div>
              <div className="content">
                Saving on word count
                <div className="sub header">
                  {this.getSavingWorkCount()} at 3.000 w/day
                </div>
              </div>
            </h2>
            <p>
              Matecat gives you more matches than any other tool thanks to a
              better integration of machine translation and translation
              memories.
              <span
                style={{marginLeft: '2px'}}
                data-html={tooltipText}
                ref={(tooltip) => (this.tooltip = tooltip)}
              >
                <span
                  className="icon-info icon"
                  style={{position: 'relative', top: '2px', color: '#a7a7a7'}}
                />
              </span>
            </p>
          </div>
        </div>
      </div>
    )
  }

  downloadAnalysisReport() {
    var pid = config.id_project
    var ppassword = config.password

    var form =
      '			<form id="downloadAnalysisReportForm" action="/" method="post">' +
      '				<input type=hidden name="action" value="downloadAnalysisReport">' +
      '				<input type=hidden name="id_project" value="' +
      pid +
      '">' +
      '				<input type=hidden name="password" value="' +
      ppassword +
      '">' +
      '				<input type=hidden name="download_type" value="XTRF">' +
      '			</form>'
    $('body').append(form)
    $('#downloadAnalysisReportForm').submit()
  }

  /**
   * To add informations from the plugins
   * @returns {string}
   */
  moreProjectInfo() {
    return ''
  }

  componentDidUpdate() {
    let self = this
    if (this.updatedSavingWords) {
      this.containerSavingWords.classList.add('updated-count')
      this.updatedSavingWords = false
      setTimeout(function () {
        self.containerSavingWords.classList.remove('updated-count')
      }, 400)
    }
    let status = this.props.data.get('status')
    if (status === ANALYSIS_STATUS.DONE) {
      setTimeout(function () {
        self.containerAnalysisComplete?.classList.remove('hide')
      }, 600)
    }
  }

  componentDidMount() {
    $(this.tooltip).popup({
      position: 'bottom center',
    })
    let self = this
    let status = this.props.data.get('status')
    if (status === ANALYSIS_STATUS.DONE) {
      this.containerSavingWords.classList.add('updated-count')
      setTimeout(function () {
        self.containerSavingWords.classList.remove('updated-count')
        self.containerAnalysisComplete?.classList.remove('hide')
      }, 400)
    }
  }

  shouldComponentUpdate(nextProps) {
    return !nextProps.data.equals(this.props.data)
  }

  render() {
    let analysisStateHtml = this.getAnalysisStateHtml()
    let wordsCountHtml = this.getWordscount()
    let projectName = this.props.project.get('name')
      ? this.props.project.get('name')
      : ''
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
          {this.moreProjectInfo()}
          {analysisStateHtml}
        </div>

        <div className="seven wide right floated column">{wordsCountHtml}</div>

        {this.getProgressBar()}
      </div>
    )
  }
}

export default AnalyzeHeader
