import React from 'react'
import {TransitionGroup, CSSTransition} from 'react-transition-group'

import ChunkAnalyzeHeader from './ChunkAnalyzeHeader'
import ChunkAnalyzeFile from './ChunkAnalyzeFile'

class ChunkAnalyze extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      showFiles: false,
    }
  }

  getFiles() {
    let self = this
    var array = []
    this.props.files.forEach(function (file, i) {
      array.push(
        <ChunkAnalyzeFile
          key={i}
          file={file}
          fileInfo={self.props.chunkInfo.files[i]}
        />,
      )
    })
    return array
  }

  showFiles(e) {
    e.preventDefault()
    this.setState({
      showFiles: !this.state.showFiles,
    })
  }

  shouldComponentUpdate() {
    return true
  }

  render() {
    return (
      <div className="ui grid chunk-analyze-container">
        <ChunkAnalyzeHeader
          index={this.props.index}
          total={this.props.total}
          jobInfo={this.props.chunkInfo}
          showFiles={this.showFiles.bind(this)}
          chunksSize={this.props.chunksSize}
        />

        {/*<CSSTransitionGroup component="div" className="ui grid"*/}
        {/*transitionName="transition"*/}
        {/*transitionEnterTimeout={500}*/}
        {/*transitionLeaveTimeout={500}*/}
        {/*>*/}
        <TransitionGroup style={{width: '100%', padding: 0}}>
          {this.state.showFiles ? (
            <CSSTransition
              key={0}
              classNames="transition"
              timeout={{enter: 500, exit: 300}}
            >
              <div>{this.getFiles()}</div>
            </CSSTransition>
          ) : null}
        </TransitionGroup>
      </div>
    )
  }
}

export default ChunkAnalyze
