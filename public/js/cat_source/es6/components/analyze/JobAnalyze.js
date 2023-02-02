import React from 'react'
import _ from 'lodash'

import JobAnalyzeHeader from './JobAnalyzeHeader'
import JobTableHeader from './JobTableHeader'
import ChunkAnalyze from './ChunkAnalyze'

class JobAnalyze extends React.Component {
  constructor(props) {
    super(props)
    this.showDetails = this.showDetails.bind(this)
    setTimeout(()=>this.showDetails())
  }

  getChunks() {
    let self = this
    if (this.props.chunks) {
      return _.map(this.props.jobInfo.chunks, function (item, index) {
        let files = self.props.chunks.get(item.jpassword)
        index++
        let job = self.props.project.get('jobs').find(function (jobElem) {
          return jobElem.get('password') === item.jpassword
        })

        return (
          <ChunkAnalyze
            key={item.jpassword}
            files={files}
            job={job}
            project={self.props.project}
            total={self.props.total.get(item.jpassword)}
            index={index}
            chunkInfo={item}
            chunksSize={_.size(self.props.jobInfo.chunks)}
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
      $('html, body').animate(
        {
          scrollTop: $(itemComponent).offset().top - 200,
        },
        500,
      )

      // ReactDOM.findDOMNode(itemComponent).scrollIntoView({block: 'end'});
      setTimeout(function () {
        self.container.classList.remove('show-details')
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
      this.showDetails();
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
                  totals={this.props.total}
                  project={this.props.project}
                  jobInfo={this.props.jobInfo}
                  status={this.props.status}
                />
                <JobTableHeader rates={this.props.jobInfo.rates} />
                {this.getChunks()}
              </div>
            </div>
          </div>
        </div>
      </div>
    )
  }
}

export default JobAnalyze
