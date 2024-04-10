import React, {useState} from 'react'
import {TransitionGroup, CSSTransition} from 'react-transition-group'

import ChunkAnalyzeHeader from './ChunkAnalyzeHeader'
import ChunkAnalyzeFile from './ChunkAnalyzeFile'

const ChunkAnalyze = ({files, chunkInfo, index, total, chunksSize}) => {
  const [showFilesInfo, setShowFilesInfo] = useState(false)

  const getFiles = () => {
    return files.map((file, i) => {
      return <ChunkAnalyzeFile key={i} file={file} />
    })
  }

  const showFiles = (e) => {
    e.preventDefault()
    setShowFilesInfo((prevState) => !prevState)
  }

  return (
    <div className="chunk-analyze-container">
      <ChunkAnalyzeHeader
        index={index}
        total={total}
        jobInfo={chunkInfo}
        showFiles={showFiles}
        chunksSize={chunksSize}
      />
      <TransitionGroup style={{width: '100%', padding: 0}}>
        {showFilesInfo ? (
          <CSSTransition
            key={0}
            classNames="transition"
            timeout={{enter: 500, exit: 300}}
          >
            <div>{getFiles()}</div>
          </CSSTransition>
        ) : null}
      </TransitionGroup>
    </div>
  )
}

export default ChunkAnalyze
