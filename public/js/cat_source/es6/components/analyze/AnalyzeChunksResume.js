import React, {useRef} from 'react'
import {isUndefined, size} from 'lodash'
import {each, map} from 'lodash/collection'
import {pick} from 'lodash/object'

import OutsourceContainer from '../outsource/OutsourceContainer'
import ModalsActions from '../../actions/ModalsActions'
import TranslatedIcon from '../../../../../img/icons/TranslatedIcon'
import Tooltip from '../common/Tooltip'
import CommonUtils from '../../utils/commonUtils'
import {ANALYSIS_STATUS, UNIT_COUNT} from '../../constants/Constants'
import LabelWithTooltip from '../common/LabelWithTooltip'

class AnalyzeChunksResume extends React.Component {
  constructor(props) {
    super(props)
    this.payableValues = []
    this.payableValuesChenged = []
    this.containers = {}
    this.state = {
      openOutsource: false,
      outsourceJobId: null,
    }

    this.jobLinkRef = {}
  }

  showDetails = (idJob) => (evt) => {
    if ($(evt.target).parents('.outsource-container').length === 0) {
      evt.preventDefault()
      evt.stopPropagation()
      this.props.openAnalysisReport(idJob, true)
    }
  }
  openSplitModal = (id) => (e) => {
    e.stopPropagation()
    e.preventDefault()
    const {project} = this.props
    let job = project.get('jobs').find((item) => {
      return item.get('id') === id
    })
    ModalsActions.openSplitJobModal(job, project, () =>
      window.location.reload(),
    )
  }

  openMergeModal = (id) => (e) => {
    e.stopPropagation()
    e.preventDefault()
    const {project} = this.props
    let job = this.props.project.get('jobs').find((item) => {
      return item.get('id') === id
    })
    ModalsActions.openMergeModal(project.toJS(), job.toJS(), () =>
      window.location.reload(),
    )
  }

  thereIsChunkOutsourced = () => {
    const {idJob} = this.props
    let outsourceChunk = this.props.project.get('jobs').find((item) => {
      return !!item.get('outsource') && item.get('id') === idJob
    })
    return !isUndefined(outsourceChunk)
  }

  getTranslateUrl = (job, index) => {
    let chunk_id = index ? index : job.get('id')
    return (
      '/translate/' +
      this.props.project.get('project_slug') +
      '/' +
      job.get('source') +
      '-' +
      job.get('target') +
      '/' +
      chunk_id +
      '-' +
      job.get('password') +
      (index ? '#' + job.get('job_first_segment') : '')
    )
  }

  openOutsourceModal = (idJob, chunk) => (e) => {
    const {status} = this.props
    e.stopPropagation()
    e.preventDefault()
    if (status !== ANALYSIS_STATUS.DONE) return

    const data = {
      event: 'outsource_request',
    }
    CommonUtils.dispatchAnalyticsEvents(data)
    if (chunk.outsource_available) {
      this.setState({
        openOutsource: true,
        outsourceJobId: idJob,
      })
    } else {
      window.open('https://translated.com/contact-us', '_blank')
    }
  }

  closeOutsourceModal = () => {
    this.setState({
      openOutsource: false,
      outsourceJobId: null,
    })
  }

  checkPayableChanged = (idJob, payable) => {
    if (this.payableValues[idJob] && payable !== this.payableValues[idJob]) {
      this.payableValuesChenged[idJob] = true
    }
    this.payableValues[idJob] = payable
  }

  copyJobLinkToClipboard = (jid) => (e) => {
    e.stopPropagation()
    this.jobLinkRef[jid].select()
    this.jobLinkRef[jid].setSelectionRange(0, 99999)
    setTimeout(() => {
      $('.ui.icon.button.copy').popup('hide')
    }, 3000)
    document.execCommand('copy')
  }

  goToTranslate = (chunk, index, e) => {
    e.preventDefault()
    e.stopPropagation()
    const key = 'first_translate_click' + config.id_project
    if (!sessionStorage.getItem(key)) {
      //Track Translate click
      const event = {
        event: 'open_job',
        userStatus: APP.USER.isUserLogged() ? 'loggedUser' : 'notLoggedUser',
        userId:
          APP.USER.isUserLogged() && APP.USER.STORE.user
            ? APP.USER.STORE.user.uid
            : null,
        idProject: parseInt(config.id_project),
      }
      CommonUtils.dispatchAnalyticsEvents(event)
      sessionStorage.setItem(key, 'true')
    }
    window.open(chunk.urls.t, '_blank')
  }

  getDirectOpenButton = (chunk, index) => {
    const {status} = this.props
    return (
      <div
        className={`open-translate ui primary button open ${
          status !== ANALYSIS_STATUS.DONE ? 'disabled' : ''
        }`}
        onClick={(e) => {
          this.goToTranslate(chunk, index, e)
        }}
      >
        Translate
      </div>
    )
  }

  getOutsourceButton = (chunk, index) => {
    return (
      <OutsourceButton
        chunk={chunk}
        index={index}
        status={this.props.status}
        openOutsourceModal={this.openOutsourceModal}
      />
    )
  }

  getResumeJobs = () => {
    const {copyJobLinkToClipboard, thereIsChunkOutsourced} = this
    const {status, jobsAnalysis} = this.props

    let buttonsClass =
      status !== 'DONE' || thereIsChunkOutsourced() ? 'disabled' : ''
    if (jobsAnalysis) {
      return jobsAnalysis.map((job, indexJob) => {
        if (job.chunks.length > 1) {
          let chunksHtml = map(job.chunks, (chunkAnalysis, index) => {
            chunkAnalysis.id = job.id
            chunkAnalysis.outsource_available = job.outsource_available
            chunkAnalysis.target_name = job.target_name
            chunkAnalysis.source_name = job.source_name
            index++

            let openOutsource =
              this.state.openOutsource &&
              this.state.outsourceJobId === job.id + '-' + index
            let chunkJob = this.props.project.get('jobs').find((item) => {
              return (
                item.get('id') === chunkAnalysis.id &&
                item.get('password') === chunkAnalysis.password
              )
            })
            this.checkPayableChanged(
              job.id + index,
              chunkAnalysis.total_equivalent,
            )

            let openOutsourceClass = openOutsource ? 'openOutsource' : ''
            const jidChunk = `${chunkAnalysis.id}-${index}`

            return (
              <div
                key={index}
                className={'chunk ui grid shadow-1 ' + openOutsourceClass}
                onClick={this.showDetails(chunkAnalysis.id)}
              >
                <div className="title-job splitted">
                  <div className="job-id">{'Chunk ' + index}</div>
                  <div className={'translate-url'}>
                    <input
                      ref={(el) => (this.jobLinkRef[jidChunk] = el)}
                      type="text"
                      readOnly
                      value={chunkAnalysis.urls.t}
                      onClick={(e) => e.stopPropagation()}
                    />
                    <button
                      onClick={copyJobLinkToClipboard(jidChunk)}
                      className={'ui icon button copy'}
                      data-content="Copied to Clipboard!"
                      data-position="top center"
                    >
                      <i className="icon-link icon" />
                    </button>
                  </div>
                </div>
                <div className="titles-compare">
                  <div className="title-total-words ttw">
                    <div>{chunkAnalysis.total_raw}</div>
                  </div>
                  <div className="title-standard-words tsw">
                    <div>{chunkAnalysis.total_industry}</div>
                  </div>
                  <div
                    className="title-matecat-words tmw"
                    ref={(container) =>
                      (this.containers[chunkAnalysis.id + index] = container)
                    }
                  >
                    <div>{chunkAnalysis.total_equivalent}</div>
                  </div>
                </div>
                <div className="activity-icons">
                  <div
                    className={`activity-button ${config.jobAnalysis ? 'disable-outsource' : ''}`}
                  >
                    {/*{self.getOpenButton(job.toJS(), job.id + '-' + index)}*/}
                    {this.getDirectOpenButton(
                      chunkAnalysis,
                      job.id + '-' + index,
                    )}
                  </div>
                  {!config.jobAnalysis &&
                    this.getOutsourceButton(
                      chunkAnalysis,
                      chunkAnalysis.id + '-' + index,
                    )}
                </div>
                <OutsourceContainer
                  project={this.props.project}
                  job={chunkJob}
                  url={chunkAnalysis.urls.t}
                  showTranslatorBox={false}
                  extendedView={true}
                  onClickOutside={this.closeOutsourceModal}
                  openOutsource={openOutsource}
                  idJobLabel={job.id + '-' + index}
                  outsourceJobId={this.state.outsourceJobId}
                  standardWC={chunkAnalysis.total_equivalent}
                />
              </div>
            )
          })

          return (
            <div key={indexJob} className="job ui grid">
              <div className="chunks sixteen wide column">
                <div
                  className="chunk ui grid shadow-1"
                  onClick={this.showDetails(jobsAnalysis[indexJob].id)}
                >
                  <div className="title-job heading splitted">
                    <div className="job-info">
                      <div className="job-id">
                        ID: {jobsAnalysis[indexJob].id}
                      </div>
                      <div className="source-target">
                        <LabelWithTooltip className="source-box">
                          <span>{jobsAnalysis[indexJob].source_name}</span>
                        </LabelWithTooltip>
                        <div className="in-to">
                          <i className="icon-chevron-right icon" />
                        </div>
                        <LabelWithTooltip className="target-box">
                          <span>{jobsAnalysis[indexJob].target_name}</span>
                        </LabelWithTooltip>
                      </div>
                    </div>
                  </div>

                  <div className="activity-icons splitted">
                    <div
                      className="merge ui blue basic button"
                      onClick={this.openMergeModal(jobsAnalysis[indexJob].id)}
                    >
                      <i className="icon-compress icon" />
                      Merge
                    </div>
                  </div>
                </div>
                {chunksHtml}
              </div>
            </div>
          )
        } else {
          let chunkAnalysis = jobsAnalysis[indexJob].chunks[0]
          chunkAnalysis.id = job.id
          chunkAnalysis.outsource_available = job.outsource_available
          chunkAnalysis.outsource = job.outsource
          chunkAnalysis.target_name = job.target_name
          chunkAnalysis.source_name = job.source_name
          let total_raw = chunkAnalysis.total_raw
          let standardWordCount = chunkAnalysis.total_industry
          let chunkJob = this.props.project.get('jobs').find((item) => {
            return (
              item.get('id') === chunkAnalysis.id &&
              item.get('password') === chunkAnalysis.password
            )
          })
          let total_standard = standardWordCount ? standardWordCount : 0

          let openOutsource =
            this.state.openOutsource &&
            this.state.outsourceJobId === jobsAnalysis[indexJob].id
          let openOutsourceClass = openOutsource ? 'openOutsource' : ''

          this.checkPayableChanged(
            jobsAnalysis[indexJob].id,
            chunkAnalysis.total_equivalent,
          )

          return (
            <div key={indexJob} className="job ui grid">
              <div className="chunks sixteen wide column">
                <div
                  className={'chunk ui grid shadow-1 ' + openOutsourceClass}
                  onClick={this.showDetails(jobsAnalysis[indexJob].id)}
                >
                  <div className="title-job">
                    <div className="job-info">
                      <div className="job-id">
                        ID: {jobsAnalysis[indexJob].id}
                      </div>
                      <div className="source-target">
                        <LabelWithTooltip className="source-box no-split">
                          <span>{jobsAnalysis[indexJob].source_name}</span>
                        </LabelWithTooltip>
                        <div className="in-to">
                          <i className="icon-chevron-right icon" />
                        </div>
                        <LabelWithTooltip className={'target-box no-split'}>
                          <span>{jobsAnalysis[indexJob].target_name}</span>
                        </LabelWithTooltip>
                      </div>
                    </div>
                    <div className={'translate-url'}>
                      <input
                        type="text"
                        readOnly
                        value={chunkAnalysis.urls.t}
                        ref={(el) =>
                          (this.jobLinkRef[jobsAnalysis[indexJob].id] = el)
                        }
                        onClick={(e) => e.stopPropagation()}
                      />
                      <button
                        onClick={copyJobLinkToClipboard(
                          jobsAnalysis[indexJob].id,
                        )}
                        className={'ui icon button copy'}
                        data-content="Copied to Clipboard!"
                        data-position="top center"
                      >
                        <i className="icon-link icon" />
                      </button>
                    </div>
                  </div>
                  <div className="titles-compare">
                    <div className="title-total-words ttw">
                      {/*<div className="cell-label">Total words:</div>*/}
                      <div>{total_raw}</div>
                    </div>
                    <div className="title-standard-words tsw">
                      {/*<div className="cell-label">Other CAT tool</div>*/}
                      <div>{total_standard}</div>
                    </div>
                    <div
                      className="title-matecat-words tmw"
                      ref={(container) =>
                        (this.containers[jobsAnalysis[indexJob].id] = container)
                      }
                    >
                      {/*<div className="cell-label" >Weighted words:</div>*/}
                      <div>
                        {/*<i className="icon-chart4 icon"/>*/}
                        {chunkAnalysis.total_equivalent}
                      </div>
                    </div>
                  </div>
                  <div className="activity-icons">
                    <div
                      className={`activity-button  ${config.jobAnalysis ? 'disable-outsource' : ''}`}
                    >
                      {!config.jobAnalysis && config.splitEnabled ? (
                        <div
                          className={
                            'split ui blue basic button ' + buttonsClass + ' '
                          }
                          onClick={this.openSplitModal(
                            jobsAnalysis[indexJob].id,
                          )}
                        >
                          <i className="icon-expand icon" />
                          Split
                        </div>
                      ) : null}
                      {/*{this.getOpenButton(job.toJS(), jobsAnalysis[indexJob].id)}*/}
                      {this.getDirectOpenButton(chunkAnalysis)}
                    </div>
                    {!config.jobAnalysis &&
                      this.getOutsourceButton(chunkAnalysis, chunkAnalysis.id)}
                  </div>
                </div>
                <OutsourceContainer
                  project={this.props.project}
                  job={chunkJob}
                  url={chunkAnalysis.urls.t}
                  standardWC={chunkAnalysis.total_equivalent}
                  showTranslatorBox={false}
                  extendedView={true}
                  onClickOutside={this.closeOutsourceModal}
                  openOutsource={openOutsource}
                  idJobLabel={jobsAnalysis[indexJob].id}
                  outsourceJobId={this.state.outsourceJobId}
                />
              </div>
            </div>
          )
        }
      })
    } else {
      return this.props.project.get('jobs').map((jobInfo, indexJob) => {
        return (
          <div key={jobInfo.get('id') + '-' + indexJob} className="job ui grid">
            <div className="chunks sixteen wide column">
              <div className="chunk ui grid shadow-1">
                <div className="title-job no-split">
                  <div className="source-target">
                    <LabelWithTooltip className="source-box no-split">
                      <span>{jobInfo.get('sourceTxt')}</span>
                    </LabelWithTooltip>
                    <div className="in-to">
                      <i className="icon-chevron-right icon" />
                    </div>
                    <LabelWithTooltip className="target-box no-split">
                      <span>{jobInfo.get('targetTxt')}</span>
                    </LabelWithTooltip>
                  </div>
                </div>
                <div className="titles-compare">
                  <div className="title-total-words ttw">
                    <div>0</div>
                  </div>
                  <div className="title-standard-words tsw">
                    <div>0</div>
                  </div>
                  <div className="title-matecat-words tmw">
                    <div>0</div>
                  </div>
                </div>
                <div className="activity-icons" />
              </div>
            </div>
          </div>
        )
      })
    }
  }

  openAnalysisReport = (e) => {
    e.preventDefault()
    e.stopPropagation()
    this.props.openAnalysisReport()
  }

  componentDidUpdate() {
    let changedData = pick(this.payableValuesChenged, (item) => {
      return item === true
    })
    if (size(changedData) > 0) {
      each(changedData, (item, i) => {
        this.containers[i].classList.add('updated-count')
        setTimeout(() => {
          this.containers[i].classList.remove('updated-count')
        }, 400)
      })
    }
  }

  componentDidMount() {
    if (this.props.status === 'DONE') {
      each(self.containers, (item, i) => {
        this.classList.add('updated-count')
        setTimeout(() => {
          this.containers[i].classList.remove('updated-count')
        }, 400)
      })
    }

    $('.ui.icon.button.copy').popup({
      on: 'click',
      hideOnScroll: true,
    })
  }

  render() {
    let showHideText = this.props.showAnalysis ? 'Hide Details' : 'Show Details'
    let iconClass = this.props.showAnalysis ? 'open' : ''
    let html = this.getResumeJobs()
    return (
      <div className="project-top ui grid">
        <div className="compare-table sixteen wide column">
          <div className="header-compare-table ui grid shadow-1">
            <div className="title-job">
              <h5 />
              <p />
            </div>
            <div className="titles-compare">
              {this.props.jobsAnalysis.length &&
              this.props.jobsAnalysis[0].count_unit === UNIT_COUNT.WORDS ? (
                <div className="title-total-words">
                  <h5>Total word count</h5>
                </div>
              ) : (
                <div className="title-total-words">
                  <h5>Total character count</h5>
                </div>
              )}
              <div className="title-standard-words">
                <h5>
                  Industry weighted
                  <span data-tooltip="As counted by other CAT tools">
                    <i className="icon-info icon" />
                  </span>
                </h5>
              </div>
              <div className="title-matecat-words">
                <h5>Matecat weighted</h5>
              </div>
            </div>
          </div>
        </div>
        <div className="compare-table jobs sixteen wide column">{html}</div>
        {this.props.jobsAnalysis ? (
          <div className="analyze-report" onClick={this.openAnalysisReport}>
            <div>
              <h3>{showHideText}</h3>
              <div className="rounded">
                <i className={'icon-sort-down icon ' + iconClass} />
              </div>
            </div>
          </div>
        ) : null}
      </div>
    )
  }
}

const OutsourceButton = ({chunk, index, openOutsourceModal, status}) => {
  const outsourceButton = useRef()
  return !chunk.outsource_available &&
    chunk.outsource_info?.custom_payable_rate ? (
    <div
      className={'outsource-translation outsource-translation-disabled'}
      id="open-quote-request"
    >
      <Tooltip
        content={
          <div>
            Jobs created with custom billing models cannot be outsourced to
            Translated.
            <br />
            In order to outsource this job to Translated, please recreate it
            using Matecat&apos;s standard billing model
          </div>
        }
      >
        <div ref={outsourceButton}>
          <a>Buy Translation</a>
          <span>
            from <TranslatedIcon />
          </span>
        </div>
      </Tooltip>
    </div>
  ) : (
    <div
      className={`outsource-translation  ${
        status !== ANALYSIS_STATUS.DONE ? 'outsource-translation-disabled' : ''
      }`}
      onClick={openOutsourceModal(index, chunk)}
      id="open-quote-request"
    >
      <a>Buy Translation</a>
      <span>
        from <TranslatedIcon />
      </span>
    </div>
  )
}

export default AnalyzeChunksResume
