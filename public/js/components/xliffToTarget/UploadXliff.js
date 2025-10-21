import React, {useCallback, useState} from 'react'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../common/Button/Button'
import {DeleteIcon} from '../segments/SegmentFooterTabGlossary'
import FileUploadIconBig from '../../../img/icons/FileUploadIconBig'
import CommonUtils from '../../utils/commonUtils'
import IconAdd from '../icons/IconAdd'
import IconClose from '../icons/IconClose'
import {PROGRESS_BAR_SIZE, ProgressBar} from '../common/ProgressBar'
import {xliffToTargetUpload} from '../../api/xliffToTargetUpload'
import {getPrintableFileSize} from '../createProject/UploadFile'
import {saveAs} from 'file-saver'

const b64toBlob = (b64Data, contentType, sliceSize) => {
  contentType = contentType || ''
  sliceSize = sliceSize || 512
  const byteCharacters = atob(b64Data)
  let byteArrays = []
  for (let offset = 0; offset < byteCharacters.length; offset += sliceSize) {
    const slice = byteCharacters.slice(offset, offset + sliceSize)
    const byteNumbers = new Array(slice.length)
    for (let i = 0; i < slice.length; i++) {
      byteNumbers[i] = slice.charCodeAt(i)
    }
    const byteArray = new Uint8Array(byteNumbers)
    byteArrays.push(byteArray)
  }
  return new Blob(byteArrays, {
    type: contentType,
  })
}
export const UploadXliff = () => {
  const [files, setFiles] = useState([])
  const [isDragging, setIsDragging] = useState(false)
  const dragCounter = React.useRef(0)

  const handleFiles = (selectedFiles) => {
    const fileList = Array.from(selectedFiles).map((file) => {
      let name = file.name
      // Check if file with the same name already exists
      const filesSameName = files.filter((f) => f.originalName === name)
      if (filesSameName.length > 0) {
        name = `${file.name.split('.')[0]}_(${filesSameName.length}).${file.name.split('.')[1]}`
      }
      const ext = file.name.split('.').pop()
      CommonUtils.dispatchCustomEvent('uploaded-file', {extension: ext})
      return {
        file,
        originalName: file.name,
        name: name,
        uploadProgress: 0,
        uploaded: false,
        error: null,
        zipFolder: false,
        size: 0,
        ext: ext,
      }
    })
    //Check if the total number of files exceeds the limit
    const totalFiles = files.length + fileList.length
    if (totalFiles > config.maxNumberFiles) {
      const excessFiles = totalFiles - config.maxNumberFiles
      fileList.slice(-excessFiles).forEach((f) => {
        f.error = 'File limit exceeded'
      })
    }
    setFiles((prevFiles) => prevFiles.concat(fileList))
    fileList.forEach(({file, name}) => {
      if (file.error) return
      const onProgress = (progress) => {
        setFiles((prevFiles) =>
          prevFiles.map((f) =>
            f.name === name ? {...f, uploadProgress: progress} : f,
          ),
        )
      }

      const onSuccess = (files) => {
        const fileResponse = JSON.parse(files)
        if (fileResponse.error) {
          setFiles((prevFiles) =>
            prevFiles.map((f) =>
              f.file === file
                ? {
                    ...f,
                    uploaded: false,
                    error: fileResponse.error,
                  }
                : f,
            ),
          )
          return
        } else {
          setFiles((prevFiles) =>
            prevFiles.map((f) =>
              f.file === file
                ? {
                    ...f,
                    uploaded: true,
                    size: fileResponse.size,
                    type: fileResponse.type,
                  }
                : f,
            ),
          )
        }

        saveAs(b64toBlob(fileResponse.fileContent), fileResponse.fileName)
      }

      const onError = () => {
        setFiles((prevFiles) =>
          prevFiles.map((f) =>
            f.file === file
              ? {
                  ...f,
                  error:
                    'Error: An error occurred. Please, be sure that the xliff file has been downloaded from Matecat',
                }
              : f,
          ),
        )
      }

      try {
        xliffToTargetUpload(file, onProgress, onSuccess, onError)
      } catch (e) {
        onError(file)
      }
    })
  }

  const deleteFile = (fileName) => {
    setFiles((prevFiles) => prevFiles.filter((f) => f.name !== fileName))
  }

  const handleDrop = useCallback(
    (e) => {
      e.preventDefault()
      handleFiles(e.dataTransfer.files)
      setIsDragging(false)
      dragCounter.current = 0
    },
    [handleFiles],
  )

  const handleDragEnter = useCallback((e) => {
    e.preventDefault()
    dragCounter.current += 1
    if (dragCounter.current === 1) {
      setIsDragging(true)
    }
  }, [])

  const handleDragLeave = useCallback((e) => {
    e.preventDefault()
    dragCounter.current -= 1
    if (dragCounter.current === 0) {
      setIsDragging(false)
    }
  }, [])

  const handleChange = (e) => {
    handleFiles(e.target.files)
    e.target.value = ''
  }

  const handleDragOver = (e) => {
    e.preventDefault()
  }

  return (
    <div
      className={`upload-files-container ${isDragging ? 'dragging' : ''} ${files.length > 0 ? 'add-files' : ''}`}
    >
      <input
        type="file"
        multiple
        style={{display: 'none'}}
        id="fileInput"
        accept="application/xliff+xml, .xlf"
        onChange={handleChange}
      />
      <div
        className="upload-files-start"
        onDrop={handleDrop}
        onDragEnter={handleDragEnter}
        onDragLeave={handleDragLeave}
        onDragOver={handleDragOver}
        onClick={() => document.getElementById('fileInput').click()}
      >
        <FileUploadIconBig />
        {!isDragging ? (
          <>
            <p>Drag and drop your XLIFF here</p>
            <span>or click to browse</span>
          </>
        ) : (
          <p>Drop it here</p>
        )}
      </div>
      <>
        <div className="upload-files-list">
          {files.map((f, idx) => (
            <div key={idx} className="file-item">
              <div className="file-item-name">
                <span
                  className={`file-icon ${CommonUtils.getIconClass(f.ext)}`}
                />
                {f.name}
              </div>
              {f.error && (
                <div className="file-item-error">
                  <span dangerouslySetInnerHTML={{__html: f.error}} />
                </div>
              )}
              {f.warning && (
                <div className="file-item-warning">{f.warning}</div>
              )}
              {f.uploaded && !f.error && f.size && (
                <div className="file-item-info">
                  <div>{getPrintableFileSize(f.size)}</div>
                  <span className="file-item-success">
                    File downloaded! Check your download folder{' '}
                  </span>
                </div>
              )}
              {!f.uploaded && !f.error && f.uploadProgress > 0 && (
                <div className={'upload-progress'}>
                  <ProgressBar
                    total={100}
                    progress={f.uploadProgress}
                    size={PROGRESS_BAR_SIZE.BIG}
                    showProgress={true}
                    label={'Uploading'}
                  />
                </div>
              )}
              <Button
                size={BUTTON_SIZE.ICON_SMALL}
                onClick={() => deleteFile(f.name)}
                style={{marginLeft: 'auto'}}
                tooltip={'Remove file'}
              >
                <DeleteIcon />
              </Button>
            </div>
          ))}
        </div>
        {files.length > 0 && (
          <div className="upload-files-buttons">
            <Button
              type={BUTTON_TYPE.WARNING}
              onClick={() => files.forEach((f) => deleteFile(f.name))}
            >
              <IconClose /> Clear all
            </Button>
          </div>
        )}
      </>
    </div>
  )
}
