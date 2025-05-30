import React, {useState} from 'react'

import ChunkAnalyzeHeader from './ChunkAnalyzeHeader'
import ChunkAnalyzeFile from './ChunkAnalyzeFile'

const ChunkAnalyze = ({
  files,
  chunkInfo,
  index,
  total,
  chunksSize,
  rates,
  workflowType,
}) => {
  const [showFilesInfo, setShowFilesInfo] = useState(false)

  const getFiles = () => {
    return files.map((file, i) => {
      return (
        <ChunkAnalyzeFile
          key={i}
          file={file}
          index={i + 1}
          size={files.length}
          rates={rates}
          workflowType={workflowType}
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
        rates={rates}
        workflowType={workflowType}
      />
      {showFilesInfo ? <div>{getFiles()}</div> : null}
    </div>
  )
}

export default ChunkAnalyze
