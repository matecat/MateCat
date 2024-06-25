import React, {useState} from 'react'
import {TransitionGroup, CSSTransition} from 'react-transition-group'

import ChunkAnalyzeHeader from './ChunkAnalyzeHeader'
import ChunkAnalyzeFile from './ChunkAnalyzeFile'

const ChunkAnalyze = ({files, chunkInfo, index, total, chunksSize}) => {
  const [showFilesInfo, setShowFilesInfo] = useState(false)

  const getFiles = () => {
    return files.map((file, i) => {
      return (
        <ChunkAnalyzeFile
          key={i}
          file={file}
          index={i + 1}
          size={files.length}
        />
      )
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
        showFilesFn={showFiles}
        showFiles={showFilesInfo}
        chunksSize={chunksSize}
      />
      {showFilesInfo ? <div>{getFiles()}</div> : null}
    </div>
  )
}

export default ChunkAnalyze
