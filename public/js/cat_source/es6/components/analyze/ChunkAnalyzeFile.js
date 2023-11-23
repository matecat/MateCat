import React from 'react'

class ChunkAnalyzeFile extends React.Component {
  constructor(props) {
    super(props)
  }

  render() {
    const file = this.props.file
    const matches = file.matches
    return (
      <div className="chunk-detail sixteen wide column pad-right-10">
        <div className="left-box">
          <i className="icon-make-group icon"></i>
          <div className="file-title-details">
            {file.original_name}
            {/*(<span className="f-details-number">2</span>)*/}
          </div>
        </div>
        <div className="single-analysis">
          <div className="single total">{file.total_raw}</div>
          <div className="single payable-words">{file.total_equivalent}</div>
          <div className="single new">
            {matches.find((item) => item.type === 'new').equivalent}
          </div>
          <div className="single repetition">
            {matches.find((item) => item.type === 'repetitions').equivalent}
          </div>
          <div className="single internal-matches">
            {matches.find((item) => item.type === 'internal').equivalent}
          </div>
          <div className="single p-50-74">
            {matches.find((item) => item.type === '50_74').equivalent}
          </div>
          <div className="single p-75-84">
            {matches.find((item) => item.type === '75_84').equivalent}
          </div>
          <div className="single p-84-94">
            {matches.find((item) => item.type === '85_94').equivalent}
          </div>
          <div className="single p-95-99">
            {matches.find((item) => item.type === '95_99').equivalent}
          </div>
          <div className="single tm-100">
            {matches.find((item) => item.type === '100').equivalent}
          </div>
          <div className="single tm-public">
            {matches.find((item) => item.type === '100_public').equivalent}
          </div>
          <div className="single tm-context">
            {matches.find((item) => item.type === 'ice').equivalent}
          </div>
          <div className="single machine-translation">
            {matches.find((item) => item.type === 'MT').equivalent}
          </div>
        </div>
      </div>
    )
  }
}

export default ChunkAnalyzeFile
