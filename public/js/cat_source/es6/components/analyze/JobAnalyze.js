import React from 'react'
import {map} from 'lodash/collection'

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
    let self = this
    if (this.props.chunks) {
      return map(this.props.jobInfo.chunks, function (item, index) {
        let chunk = self.props.chunks.find(
          (c) => c.get('password') === item.password,
        )
        index++
        let job = self.props.project.get('jobs').find(function (jobElem) {
          return jobElem.get('password') === item.password
        })

        return (
          <ChunkAnalyze
            key={item.password}
            files={chunk.get('files').toJS()}
            job={job}
            project={self.props.project}
            total={item.summary}
            index={index}
            chunkInfo={item}
            chunksSize={self.props.jobInfo.chunks.length}
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
                <JobTableHeader rates={this.props.jobInfo.payable_rates} />
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
