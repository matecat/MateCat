import React, {useEffect, useState, useCallback, useContext} from 'react'
import {fileUpload} from '../../api/fileUpload'
import {convertFileRequest} from '../../api/convertFileRequest'
import CreateProjectActions from '../../actions/CreateProjectActions'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../common/Button/Button'
import {DeleteIcon} from '../segments/SegmentFooterTabGlossary'
import {fileUploadDelete} from '../../api/fileUploadDelete'
import FileUploadIconBig from '../../../../../img/icons/FileUploadIconBig'
import CommonUtils from '../../utils/commonUtils'
import IconAdd from '../icons/IconAdd'
import IconClose from '../icons/IconClose'
import {initFileUpload} from '../../api/initFileUpload'
import {clearNotCompletedUploads} from '../../api/clearNotCompletedUploads'
import {PROGRESS_BAR_SIZE, ProgressBar} from '../common/ProgressBar'
import {getPrintableFileSize} from './UploadFile'
import {CreateProjectContext} from './CreateProjectContext'

const EXTENSIONS = {
  tmx: 'tmx',
  zip: 'zip',
}

const maxFileSize = Math.log(config.maxFileSize) / Math.log(1024)
const maxFileSizePrint =
  parseInt(Math.pow(1024, maxFileSize - Math.floor(maxFileSize)) + 0.5) + ' MB'

const maxTMXFileSize = Math.log(config.maxTMXFileSize) / Math.log(1024)
const maxTMXSizePrint =
  parseInt(Math.pow(1024, maxTMXFileSize - Math.floor(maxTMXFileSize)) + 0.5) +
  ' MB'

function UploadFileLocal() {
  const [files, setFiles] = useState([])
  const [isDragging, setIsDragging] = useState(false)
  const dragCounter = React.useRef(0)
  const {
    sourceLang,
    targetLangs,
    currentProjectTemplate,
    setUploadedFilesNames,
  } = useContext(CreateProjectContext)
  const segmentationRule = currentProjectTemplate?.segmentationRule.id
  const extractionParameterTemplateId =
    currentProjectTemplate?.filters_template_id
  useEffect(() => {
    restartConversions()
  }, [sourceLang, extractionParameterTemplateId, segmentationRule])

  useEffect(() => {
    initFileUpload()
    const onBeforeUnload = () => {
      clearNotCompletedUploads()
      return true
    }

    window.addEventListener('beforeunload', onBeforeUnload)

    return () => {
      window.removeEventListener('beforeunload', onBeforeUnload)
    }
  }, [])

  useEffect(() => {
    const hasIncompleteFiles =
      files.some((f) => !f.uploaded || !f.converted || f.error) ||
      !files.some((f) => f.ext !== EXTENSIONS.tmx)
    CreateProjectActions.enableAnalyzeButton(!hasIncompleteFiles)
    if (files.length >= config.maxNumberFiles) {
      CreateProjectActions.showError(
        'No more files can be loaded (the limit of ' +
          config.maxNumberFiles +
          ' has been exceeded).',
      )
    }
  }, [files])
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
        convertProgress: 0,
        uploaded: false,
        converted: false,
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
    fileList.forEach(({file, name, ext}) => {
      if (file.error) return
      const onProgress = (progress) => {
        setFiles((prevFiles) =>
          prevFiles.map((f) =>
            f.name === name ? {...f, uploadProgress: progress} : f,
          ),
        )
      }

      const onSuccess = (files) => {
        const fileResponse = JSON.parse(files)[0]
        const fileError = getFileErrorMessage(fileResponse)
        if (fileResponse.error || fileError) {
          setFiles((prevFiles) =>
            prevFiles.map((f) =>
              f.file === file
                ? {
                    ...f,
                    uploaded: false,
                    error: fileError ? fileError : fileResponse.error,
                  }
                : f,
            ),
          )
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
          const interval = startConvertFakeProgress(file)
          convertFileRequest({
            file_name: name,
            source_lang: sourceLang.code,
            target_lang: targetLangs.map((lang) => lang.id).join(),
            segmentation_rule: segmentationRule,
            filters_extraction_parameters_template_id:
              extractionParameterTemplateId,
            restarted_conversion: false,
          })
            .then(({data, errors, warnings}) => {
              clearInterval(interval)
              if (errors?.length > 0 && errors[0].code <= -14) {
                setFiles((prevFiles) =>
                  prevFiles.map((f) =>
                    f.file === file
                      ? {
                          ...f,
                          uploaded: false,
                          error: errors[0].message,
                        }
                      : f,
                  ),
                )
                return
              }
              setUploadedFilesNames((prev) => prev.concat([name]))
              if (data.data.zipFiles) {
                const zipFiles = JSON.parse(data.data.zipFiles)
                zipFiles.forEach((zipFile) => {
                  setFiles((prevFiles) =>
                    prevFiles.concat({
                      name: zipFile.name,
                      uploadProgress: 100,
                      convertedProgress: 100,
                      converted: true,
                      uploaded: true,
                      error: null,
                      zipFolder: true,
                      size: zipFile.size,
                    }),
                  )
                  setUploadedFilesNames((prev) => prev.concat([zipFile.name]))
                })
              }
              setFiles((prevFiles) =>
                prevFiles.map((f) =>
                  f.file === file
                    ? {
                        ...f,
                        convertedProgress: 100,
                        converted: true,
                        warning: warnings ? warnings[0].message : null,
                      }
                    : f,
                ),
              )
              if (ext === EXTENSIONS.tmx) {
                CreateProjectActions.createKeyFromTMXFile({
                  filename: file.name,
                })
              }
              CreateProjectActions.enableAnalyzeButton(true)
            })
            .catch((e) => {
              console.log(e)
              setFiles((prevFiles) =>
                prevFiles.map((f) =>
                  f.file === file
                    ? {
                        ...f,
                        uploaded: false,
                        error: 'Server error, try again.',
                      }
                    : f,
                ),
              )
            })
        }
      }

      const onError = (error) => {
        setFiles((prevFiles) =>
          prevFiles.map((f) => (f.file === file ? {...f, error} : f)),
        )
      }

      fileUpload(file, onProgress, onSuccess, onError)
    })
  }

  const getFileErrorMessage = (file) => {
    const {ext, size} = file
    if (ext === EXTENSIONS.tmx && size > config.maxTMXFileSize) {
      return (
        'Error during upload. The uploaded TMX file exceed the file size limit of ' +
        maxTMXSizePrint
      )
    } else if (ext !== EXTENSIONS.tmx && size > config.maxFileSize) {
      return (
        'Error during upload. The uploaded file exceed the file size limit of ' +
        maxFileSizePrint
      )
    }
  }

  const restartConversions = () => {
    CreateProjectActions.enableAnalyzeButton(false)
    files.forEach((f) => {
      if (f.uploaded && !f.error) {
        convertFileRequest({
          file_name: f.name,
          source_lang: sourceLang,
          target_lang: targetLangs.map((lang) => lang.id).join(),
          segmentation_rule: segmentationRule,
          filters_extraction_parameters_template_id:
            extractionParameterTemplateId,
        }).then(() => {
          CreateProjectActions.enableAnalyzeButton(true)
        })
      }
    })
  }

  const deleteFile = (file) => {
    setFiles((prevFiles) => prevFiles.filter((f) => f.name !== file.name))
    setUploadedFilesNames((prev) => prev.filter((f) => f !== file.name))
    fileUploadDelete({
      file: file.name,
      source: sourceLang,
      segmentationRule,
      filtersTemplateId: extractionParameterTemplateId,
    })
    if (file.ext === EXTENSIONS.zip) {
      setFiles((prevFiles) =>
        prevFiles.filter((f) => !(f.zipFolder && f.name.startsWith(file.name))),
      )
      setUploadedFilesNames((prev) =>
        prev.filter((f) => !f.startsWith(file.name)),
      )
    }
    CreateProjectActions.hideErrors()
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

  const startConvertFakeProgress = (file) => {
    let step = 0.5
    let currentProgress = 0
    return setInterval(() => {
      currentProgress += step
      const progress =
        Math.round((Math.atan(currentProgress) / (Math.PI / 2)) * 100 * 1000) /
        1000

      setFiles((prevFiles) =>
        prevFiles.map((f) =>
          f.file === file
            ? {
                ...f,
                convertProgress: progress,
              }
            : f,
        ),
      )
      if (progress >= 70) {
        step = 0.1
      }
    }, 100)
  }

  return (
    <div
      className={`upload-files-container ${isDragging ? 'dragging' : ''} ${files.length > 0 ? 'add-files' : ''}`}
      onDrop={handleDrop}
      onDragEnter={handleDragEnter}
      onDragLeave={handleDragLeave}
      onDragOver={handleDragOver}
      onClick={
        files.length === 0
          ? () => document.getElementById('fileInput').click()
          : null
      }
    >
      <input
        type="file"
        multiple
        style={{display: 'none'}}
        id="fileInput"
        onChange={handleChange}
      />
      {files.length === 0 ? (
        <div className={`upload-files-start`}>
          <FileUploadIconBig />
          {!isDragging ? (
            <>
              <p>Drop your files to translate them with Matecat</p>
              <span>or click to browse</span>
            </>
          ) : (
            <p>Drop it here</p>
          )}
        </div>
      ) : (
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
                {f.error && <div className="file-item-error">{f.error}</div>}
                {f.warning && (
                  <div className="file-item-warning">{f.warning}</div>
                )}
                {f.uploaded &&
                  f.converted &&
                  !f.error &&
                  f.size &&
                  getPrintableFileSize(f.size)}
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
                {f.uploaded &&
                  !f.converted &&
                  !f.error &&
                  f.convertProgress > 0 && (
                    <div className={'upload-progress'}>
                      <ProgressBar
                        total={100}
                        progress={f.convertProgress}
                        size={PROGRESS_BAR_SIZE.BIG}
                        label={'Importing'}
                        className={'importing-progress'}
                      />
                    </div>
                  )}
                <Button
                  size={BUTTON_SIZE.ICON_SMALL}
                  onClick={() => deleteFile(f)}
                  style={{marginLeft: 'auto'}}
                  tooltip={'Remove file'}
                >
                  <DeleteIcon />
                </Button>
              </div>
            ))}
          </div>
          <div className="upload-files-buttons">
            <span>
              <strong>Drag and drop</strong> your file here or
            </span>
            <Button
              type={BUTTON_TYPE.PRIMARY}
              onClick={() => document.getElementById('fileInput').click()}
              disabled={files.length >= config.maxNumberFiles}
            >
              <IconAdd />
              Add files...
            </Button>
            <Button
              type={BUTTON_TYPE.WARNING}
              onClick={() => files.forEach((f) => deleteFile(f))}
            >
              <IconClose /> Clear all
            </Button>
            {files.filter((f) => f.error).length > 0 && (
              <Button
                type={BUTTON_TYPE.WARNING}
                onClick={() => files.forEach((f) => f.error && deleteFile(f))}
              >
                <IconClose /> Clear all failed
              </Button>
            )}
          </div>
        </>
      )}
    </div>
  )
}

export default UploadFileLocal
