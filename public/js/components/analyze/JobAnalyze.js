import React from 'react'
import {map} from 'lodash/collection'
import $ from 'jquery'
import JobAnalyzeHeader from './JobAnalyzeHeader'
import JobTableHeader from './JobTableHeader'
import ChunkAnalyze from './ChunkAnalyze'

class JobAnalyze extends React.Component {
  constructor(props) {
    super(props)
    this.showDetails = this.showDetails.bind(this)
    setTimeout(() => this.showDetails())
  }

  getChunks() {
    if (this.props.chunks) {
      return map(this.props.jobInfo.chunks, (item, index) => {
        let chunk = this.props.chunks.find(
          (c) => c.get('password') === item.password,
        )
        index++
        let job = this.props.project.get('jobs').find(function (jobElem) {
          return jobElem.get('password') === item.password
        })

        return (
          <ChunkAnalyze
            key={item.password}
            files={chunk.get('files').toJS()}
            job={job}
            project={this.props.project}
            total={item.summary}
            index={index}
            chunkInfo={item}
            chunksSize={this.props.jobInfo.chunks.length}
            rates={this.props.jobInfo.payable_rates}
            workflowType={this.props.project
              .get('analysis')
              .get('workflow_type')}
          />
        )
      })
    }
    return ''
  }

  showDetails() {
    if (this.props.jobToScroll == this.props.idJob && this.props.showAnalysis) {
      this.scrollElement()
    }
  }

  scrollElement() {
    let itemComponent = this.container
    let self = this
    if (itemComponent) {
      this.container.classList.add('show-details')
      $('#analyze-container').animate(
        {
          scrollTop: $(itemComponent).offset().top - 200,
        },
        500,
      )

      // ReactDOM.findDOMNode(itemComponent).scrollIntoView({block: 'end'});
      setTimeout(function () {
        self.container && self.container.classList.remove('show-details')
      }, 1000)
    } else {
      setTimeout(function () {
        self.scrollElement()
      }, 500)
    }
  }

  shouldComponentUpdate() {
    return true
  }

  componentDidUpdate(prevProps) {
    if (prevProps.jobToScroll !== this.props.jobToScroll) {
      this.showDetails()
    }
  }
  render() {
    const iceMTRawWords = this.props.jobInfo.chunks.reduce((total, item) => {
      const iceMT = item.summary.find((t) => t.type === 'ice_mt')
      if (iceMT) return total + iceMT.raw
      else total
    }, 0)
    return (
      <div className="job ui grid">
        <div className="job-body sixteen wide column">
          <div className="ui grid chunks">
            <div className="chunk-container sixteen wide column">
              <div
                className="ui grid analysis"
                ref={(container) => (this.container = container)}
              >
                <JobAnalyzeHeader
                  project={this.props.project}
                  jobInfo={this.props.jobInfo}
                  status={this.props.status}
                />
                <JobTableHeader
                  rates={this.props.jobInfo.payable_rates}
                  iceMTRawWords={iceMTRawWords}
                  workflowType={this.props.project
                    .get('analysis')
                    .get('workflow_type')}
                />
                <div className="chunks-analyze">{this.getChunks()}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    )
  }
}

export default JobAnalyze
