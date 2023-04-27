import React from 'react'
import _ from 'lodash'

import OutsourceContainer from '../outsource/OutsourceContainer'
import ModalsActions from '../../actions/ModalsActions'

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
      return item.get('id') == id
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
      return item.get('id') == id
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
    return !_.isUndefined(outsourceChunk)
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
    e.stopPropagation()
    e.preventDefault()
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
    window.open(this.getTranslateUrl(chunk, index), '_blank')
  }

  getDirectOpenButton = (chunk, index) => {
    const {status} = this.props
    return (
      <div
        className={`open-translate ui primary button open ${
          status === 'NEW' ? 'disabled' : ''
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
    const {openOutsourceModal} = this
    return (
      <div
        className={'outsource-translation'}
        onClick={openOutsourceModal(index, chunk)}
        id="open-quote-request"
      >
        <a>Buy Translation</a>
        <span>
          By
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="60"
            height="15"
            viewBox="0 0 192 40"
          >
            <g
              fill="#000"
              fillRule="nonzero"
              stroke="none"
              strokeWidth="1"
              transform="translate(-120 -32) translate(120 32)"
            >
              <path d="M60.456 28.064a5.584 5.584 0 01-3.474 1.082c-1.777 0-3.138-.583-3.937-1.856-.5-.748-.694-1.634-.694-3.046v-7.615h-2.453v-2.352h2.444v-3.904h3.094v3.904h4.5v2.352h-4.5v7.643c0 1.466.694 2.158 1.968 2.158a2.889 2.889 0 001.834-.636l1.218 2.27zM71.08 17.497a3.446 3.446 0 00-1.664-.442c-1.862 0-3.502 1.606-3.502 4.372v7.394h-3.057V14.277h3.071v2.352c.973-1.522 2.416-2.63 4.112-2.63a3.697 3.697 0 011.943.5l-.902 2.998zM85.443 29.034c-1.361 0-2.613-.636-2.916-2.161-1.196 1.718-3.139 2.273-4.863 2.273-3 0-5-1.634-5-4.485 0-3.598 3.195-4.153 5.695-4.428 2.112-.221 3.695-.415 3.695-1.827 0-1.413-1.611-1.856-2.972-1.856a8.098 8.098 0 00-4.556 1.466l-1.224-2.295a10.682 10.682 0 016.002-1.775c3.445 0 5.86 1.385 5.86 4.874v6.618c0 .942.307 1.328 1.03 1.328.28.012.56-.055.804-.193l.335 2.105c-.6.242-1.243.363-1.89.356zm-3.36-7.946c-.844 1.05-2.085 1.05-3.64 1.3-1.555.25-2.697.693-2.697 2.215 0 1.522 1.167 2.16 2.67 2.16 1.914 0 3.655-1.025 3.655-3.46l.011-2.215zM89.27 28.812V14.277h3.058v2.13c1.192-1.578 2.812-2.463 4.78-2.463 3.195 0 5.029 2.132 5.029 5.4v9.468h-3.054v-8.775c0-1.553-.5-3.296-2.889-3.296-2.168 0-3.86 1.522-3.86 3.924v8.128l-3.063.02zM111.11 29.146c-2.972 0-5.39-1.138-6.777-3.268l2.166-1.522c1.11 1.55 2.888 2.295 4.78 2.295 1.806 0 3.03-.664 3.03-1.936 0-1.329-1.334-1.606-3.696-1.993-3.062-.527-5.504-1.385-5.504-4.384 0-2.999 2.695-4.403 5.723-4.403 2.945 0 5.085 1.275 6.28 2.548l-1.946 1.771a5.744 5.744 0 00-4.362-1.827c-1.389 0-2.722.499-2.722 1.634 0 1.275 1.555 1.55 3.473 1.883 3.11.56 5.78 1.357 5.78 4.429 0 2.861-2.335 4.773-6.224 4.773zM120.054 9.044h3.057V25.02c0 1.189.5 1.522 1.277 1.522.436.008.868-.098 1.251-.305l.582 2.326a4.794 4.794 0 01-2.36.527c-1.36 0-2.53-.415-3.194-1.329-.472-.692-.613-1.496-.613-2.769V9.044zM140.674 29.034c-1.361 0-2.613-.636-2.917-2.161-1.195 1.718-3.138 2.273-4.862 2.273-3 0-5-1.634-5-4.485 0-3.598 3.195-4.153 5.695-4.428 2.112-.221 3.695-.415 3.695-1.827 0-1.413-1.611-1.856-2.973-1.856a8.098 8.098 0 00-4.556 1.466l-1.223-2.295c1.78-1.181 3.877-1.8 6.016-1.777 3.445 0 5.86 1.384 5.86 4.874v6.617c0 .942.307 1.329 1.03 1.329.28.011.56-.056.804-.194l.335 2.105a4.925 4.925 0 01-1.904.359zm-3.36-7.946c-.845 1.05-2.085 1.05-3.64 1.3-1.555.25-2.694.693-2.694 2.215 0 1.522 1.164 2.16 2.666 2.16 1.915 0 3.656-1.025 3.656-3.46l.011-2.215zM153.369 28.064a5.584 5.584 0 01-3.474 1.082c-1.777 0-3.138-.583-3.937-1.856-.5-.748-.694-1.634-.694-3.046v-7.615h-2.447v-2.352h2.447v-3.904h3.093v3.904h4.5v2.352h-4.528v7.643c0 1.466.695 2.158 1.969 2.158a2.889 2.889 0 001.833-.636l1.238 2.27zM168.842 22.307H157.73c.138 2.657 1.888 4.123 4.472 4.123a5.591 5.591 0 004.218-1.827l2 1.911c-1.527 1.606-3.558 2.632-6.333 2.632-4.919 0-7.641-3.24-7.641-7.531 0-4.291 2.75-7.671 7.334-7.671 4.418 0 7.113 3.13 7.113 7.31.002.351-.015.703-.051 1.053zm-3.029-2.217c-.222-2.436-1.915-3.683-4-3.683-2.305 0-3.86 1.525-4.03 3.683h8.03zM182.231 28.812V26.68a5.87 5.87 0 01-4.918 2.467c-4.137 0-6.694-3.296-6.694-7.587s2.557-7.615 6.694-7.615a5.842 5.842 0 014.918 2.463V9.044h3.094v19.768h-3.094zm-4.25-12.127c-2.778 0-4.11 2.189-4.11 4.874 0 2.685 1.332 4.846 4.11 4.846 2.78 0 4.25-2.161 4.25-4.846s-1.473-4.874-4.25-4.874zM19.807 2.89c9.337 0 16.907 7.543 16.907 16.85 0 9.306-7.57 16.85-16.907 16.85-9.338 0-16.908-7.544-16.908-16.85.01-9.302 7.574-16.84 16.908-16.85zm0-2.77C8.934.12.12 8.904.12 19.74c0 10.835 8.813 19.62 19.686 19.62 10.872 0 19.686-8.785 19.686-19.62C39.493 8.904 30.679.12 19.807.12z">
                {' '}
              </path>
              <path d="M24.562 27.977a5.533 5.533 0 01-3.448 1.074c-1.766 0-3.118-.578-3.92-1.842-.495-.743-.689-1.623-.689-3.027v-7.567h-3.54v-2.332h3.54v-3.88h3.063v13.821c0 1.458.689 2.144 1.968 2.144a2.892 2.892 0 001.82-.63l1.206 2.24zM23.148 15.42c-.004-.473.181-.929.514-1.266.334-.337.788-.53 1.263-.533h.056c.475.004.93.196 1.263.533.333.337.518.793.515 1.267a1.788 1.788 0 01-1.778 1.796h-.056a1.788 1.788 0 01-1.777-1.796z">
                {' '}
              </path>
              <ellipse cx="190.035" cy="26.988" rx="1.805" ry="1.799">
                {' '}
              </ellipse>
            </g>
          </svg>
        </span>
      </div>
    )
  }

  getUrl = (job, index) => {
    let chunk_id = index ? job.get('id') + '-' + index : job.get('id')
    return (
      window.location.protocol +
      '//' +
      window.location.host +
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

  getResumeJobs = () => {
    const {copyJobLinkToClipboard, thereIsChunkOutsourced} = this
    const {status, jobsAnalysis, jobsInfo} = this.props

    let buttonsClass =
      status !== 'DONE' || thereIsChunkOutsourced() ? 'disabled' : ''
    if (!jobsAnalysis.isEmpty()) {
      return _.map(jobsInfo, (item, indexJob) => {
        let tmpJobAnalysis = jobsAnalysis.get(indexJob)

        if (item.splitted !== '' && _.size(item.chunks) > 1) {
          let chunksHtml = _.map(item.chunks, (chunkConfig, index) => {
            let indexChunk = chunkConfig.jpassword
            let chunkAnalysis = tmpJobAnalysis.get('totals').get(indexChunk)
            let chunk = chunkConfig
            let chunkJob = this.props.project.get('jobs').find((job) => {
              return (
                job.get('id') == chunk.jid &&
                job.get('password') === chunk.jpassword
              )
            })
            index++

            let openOutsource =
              this.state.openOutsource &&
              this.state.outsourceJobId === chunk.jid + '-' + index

            this.checkPayableChanged(
              chunk.jid + index,
              chunkAnalysis.get('TOTAL_PAYABLE').get(1),
            )

            let openOutsourceClass = openOutsource ? 'openOutsource' : ''
            const jidChunk = `${chunk.jid}-${index}`

            return (
              <div
                key={indexChunk}
                className={'chunk ui grid shadow-1 ' + openOutsourceClass}
                onClick={this.showDetails(chunk.jid)}
              >
                <div className="title-job splitted">
                  <div className="job-id">{'Chunk ' + index}</div>
                  <div className={'translate-url'}>
                    <input
                      ref={(el) => (this.jobLinkRef[jidChunk] = el)}
                      type="text"
                      readOnly
                      value={this.getUrl(chunkJob, index)}
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
                    <div>{chunk.total_raw_word_count_print}</div>
                  </div>
                  <div className="title-standard-words tsw">
                    <div>{chunkAnalysis.get('standard_word_count').get(1)}</div>
                  </div>
                  <div
                    className="title-matecat-words tmw"
                    ref={(container) =>
                      (this.containers[chunk.jid + index] = container)
                    }
                  >
                    <div>{chunkAnalysis.get('TOTAL_PAYABLE').get(1)}</div>
                  </div>
                </div>
                <div className="activity-icons">
                  <div className={'activity-button splitted'}>
                    {/*{self.getOpenButton(chunkJob.toJS(), chunk.jid + '-' + index)}*/}
                    {this.getDirectOpenButton(
                      chunkJob,
                      chunk.jid + '-' + index,
                    )}
                  </div>
                  {this.getOutsourceButton(
                    chunkJob.toJS(),
                    chunk.jid + '-' + index,
                  )}
                </div>
                <OutsourceContainer
                  project={this.props.project}
                  job={chunkJob}
                  standardWC={chunkAnalysis.get('standard_word_count').get(1)}
                  url={this.getTranslateUrl(chunkJob, index)}
                  showTranslatorBox={false}
                  extendedView={true}
                  onClickOutside={this.closeOutsourceModal}
                  openOutsource={openOutsource}
                  idJobLabel={chunk.jid + '-' + index}
                  outsourceJobId={this.state.outsourceJobId}
                />
              </div>
            )
          })

          return (
            <div key={indexJob} className="job ui grid">
              <div className="chunks sixteen wide column">
                <div
                  className="chunk ui grid shadow-1"
                  onClick={this.showDetails(this.props.jobsInfo[indexJob].jid)}
                >
                  <div className="title-job heading splitted">
                    <div className="job-info">
                      <div className="job-id">
                        ID: {this.props.jobsInfo[indexJob].jid}
                      </div>
                      <div className="source-target">
                        <div className="source-box">
                          {this.props.jobsInfo[indexJob].source}
                        </div>
                        <div className="in-to">
                          <i className="icon-chevron-right icon" />
                        </div>
                        <div className="target-box">
                          {this.props.jobsInfo[indexJob].target}
                        </div>
                      </div>
                    </div>
                  </div>

                  <div className="activity-icons splitted">
                    <div
                      className="merge ui blue basic button"
                      onClick={this.openMergeModal(
                        this.props.jobsInfo[indexJob].jid,
                      )}
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
          let obj = this.props.jobsInfo[indexJob].chunks[0]
          let password = obj.jpassword
          let total_raw = obj.total_raw_word_count_print
          let standardWordCount = tmpJobAnalysis
            .get('totals')
            .get(password)
            .get('standard_word_count')
          let total_standard = standardWordCount ? standardWordCount.get(1) : 0

          let chunkJob = this.props.project.get('jobs').find((job) => {
            return (
              job.get('id') == this.props.jobsInfo[indexJob].jid &&
              job.get('password') === password
            )
          })

          let openOutsource =
            this.state.openOutsource &&
            this.state.outsourceJobId === this.props.jobsInfo[indexJob].jid
          let openOutsourceClass = openOutsource ? 'openOutsource' : ''

          this.checkPayableChanged(
            this.props.jobsInfo[indexJob].jid,
            tmpJobAnalysis
              .get('totals')
              .get(password)
              .get('TOTAL_PAYABLE')
              .get(1),
          )

          return (
            <div key={indexJob} className="job ui grid">
              <div className="chunks sixteen wide column">
                <div
                  className={'chunk ui grid shadow-1 ' + openOutsourceClass}
                  onClick={this.showDetails(this.props.jobsInfo[indexJob].jid)}
                >
                  <div className="title-job">
                    <div className="job-info">
                      <div className="job-id">
                        ID: {this.props.jobsInfo[indexJob].jid}
                      </div>
                      <div className="source-target">
                        <div className="source-box no-split">
                          {this.props.jobsInfo[indexJob].source}
                        </div>
                        <div className="in-to">
                          <i className="icon-chevron-right icon" />
                        </div>
                        <div className="target-box no-split">
                          {this.props.jobsInfo[indexJob].target}
                        </div>
                      </div>
                    </div>
                    <div className={'translate-url'}>
                      <input
                        type="text"
                        readOnly
                        value={this.getUrl(chunkJob)}
                        ref={(el) =>
                          (this.jobLinkRef[this.props.jobsInfo[indexJob].jid] =
                            el)
                        }
                        onClick={(e) => e.stopPropagation()}
                      />
                      <button
                        onClick={copyJobLinkToClipboard(
                          this.props.jobsInfo[indexJob].jid,
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
                        (this.containers[this.props.jobsInfo[indexJob].jid] =
                          container)
                      }
                    >
                      {/*<div className="cell-label" >Weighted words:</div>*/}
                      <div>
                        {/*<i className="icon-chart4 icon"/>*/}
                        {tmpJobAnalysis
                          .get('totals')
                          .get(password)
                          .get('TOTAL_PAYABLE')
                          .get(1)}
                      </div>
                    </div>
                  </div>
                  <div className="activity-icons">
                    <div className="activity-button">
                      {!config.jobAnalysis && config.splitEnabled ? (
                        <div
                          className={
                            'split ui blue basic button ' + buttonsClass + ' '
                          }
                          onClick={this.openSplitModal(
                            this.props.jobsInfo[indexJob].jid,
                          )}
                        >
                          <i className="icon-expand icon" />
                          Split
                        </div>
                      ) : null}
                      {/*{this.getOpenButton(chunkJob.toJS(), this.props.jobsInfo[indexJob].jid)}*/}
                      {this.getDirectOpenButton(chunkJob)}
                    </div>
                    {this.getOutsourceButton(
                      chunkJob.toJS(),
                      this.props.jobsInfo[indexJob].jid,
                    )}
                  </div>
                </div>
                <OutsourceContainer
                  project={this.props.project}
                  job={chunkJob}
                  url={this.getTranslateUrl(chunkJob)}
                  standardWC={total_standard}
                  showTranslatorBox={false}
                  extendedView={true}
                  onClickOutside={this.closeOutsourceModal}
                  openOutsource={openOutsource}
                  idJobLabel={this.props.jobsInfo[indexJob].jid}
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
                    <div className="source-box no-split">
                      {jobInfo.get('sourceTxt')}
                    </div>
                    <div className="in-to">
                      <i className="icon-chevron-right icon" />
                    </div>
                    <div className="target-box no-split">
                      {jobInfo.get('targetTxt')}
                    </div>
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
    let changedData = _.pick(this.payableValuesChenged, (item) => {
      return item === true
    })
    if (_.size(changedData) > 0) {
      _.each(changedData, (item, i) => {
        this.containers[i].classList.add('updated-count')
        setTimeout(() => {
          this.containers[i].classList.remove('updated-count')
        }, 400)
      })
    }
  }

  componentDidMount() {
    if (this.props.status === 'DONE') {
      _.each(self.containers, (item, i) => {
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
              {!config.isCJK ? (
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
        {!this.props.jobsAnalysis.isEmpty() ? (
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

export default AnalyzeChunksResume
