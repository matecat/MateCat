import React from 'react'

class OpenJobBox extends React.Component {
  constructor(props) {
    super(props)
  }

  openJob() {
    return this.props.url
  }

  getUrl() {
    return (
      window.location.protocol + '//' + window.location.host + this.props.url
    )
  }

  render() {
    return (
      <div className="open-job-box">
        <div className="title">Open job:</div>
        <div className="title-url">
          <a className="job-url" href={this.openJob()} target="_blank">
            {this.getUrl()}
          </a>
          <a
            className="ui primary button"
            href={this.openJob()}
            target="_blank"
          >
            Open job
          </a>
        </div>
      </div>
    )
  }
}

export default OpenJobBox
