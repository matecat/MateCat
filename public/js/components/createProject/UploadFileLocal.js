import React, {
  useEffect,
  useState,
  useCallback,
  useContext,
  useRef,
  useMemo,
} from 'react'
import {fileUpload} from '../../api/fileUpload'
import {convertFileRequest} from '../../api/convertFileRequest'
import CreateProjectActions from '../../actions/CreateProjectActions'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../common/Button/Button'
import {DeleteIcon} from '../segments/SegmentFooterTabGlossary'
import {fileUploadDelete} from '../../api/fileUploadDelete'
import FileUploadIconBig from '../../../img/icons/FileUploadIconBig'
import CommonUtils from '../../utils/commonUtils'
import IconAdd from '../icons/IconAdd'
import IconClose from '../icons/IconClose'
import {PROGRESS_BAR_SIZE, ProgressBar} from '../common/ProgressBar'
import {getPrintableFileSize} from './UploadFile'
import {CreateProjectContext} from './CreateProjectContext'
import {isEqual} from 'lodash'

const EXTENSIONS = {
  tmx: 'tmx',
  zip: 'zip',
}

const UPLOAD_ERRORS = {
  EMPTY_FILE: 'minFileSize',
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
    tmKeys,
    setTmKeys,
    modifyingCurrentTemplate,
    fileImportFiltersParamsTemplates,
  } = useContext(CreateProjectContext)
  const segmentationRule = currentProjectTemplate?.segmentationRule.id
  const extractionParameterTemplateId =
    currentProjectTemplate?.filters_template_id
  const icuEnabled = currentProjectTemplate?.icuEnabled
  const currentFiltersExtractionParameters = useMemo(() => {
    const unsavedTemplate = fileImportFiltersParamsTemplates.templates
      .filter(
        (template) =>
          template.id === extractionParameterTemplateId && template.isTemporary,
      )
      .map(
        ({
          /* eslint-disable */
          isSelected,
          isTemporary,
          id,
          created_at,
          modified_at,
          /* eslint-enable */
          ...result
        }) => result,
      )[0]

    return unsavedTemplate
  }, [
    extractionParameterTemplateId,
    fileImportFiltersParamsTemplates?.templates,
  ])

  const filesInterval = useRef([])

  const previousFiltersExtrationParameters = useRef()

  useEffect(() => {
    restartConversions()
  }, [sourceLang, extractionParameterTemplateId, segmentationRule])

  useEffect(() => {
    if (
      !isEqual(
        currentFiltersExtractionParameters,
        previousFiltersExtrationParameters.current,
      )
    )
      restartConversions()

    previousFiltersExtrationParameters.current =
      currentFiltersExtractionParameters
  }, [currentFiltersExtractionParameters])

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
    const fileList = selectedFiles.map((file) => {
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
          filesInterval.current.push(interval)

          convertFileRequest({
            file_name: name,
            source_lang: sourceLang.code,
            target_lang: targetLangs.map((lang) => lang.id).join(),
            segmentation_rule: segmentationRule,
            ...(typeof currentFiltersExtractionParameters === 'object'
              ? {
                  filters_extraction_parameters_template: JSON.stringify(
                    currentFiltersExtractionParameters,
                  ),
                }
              : {
                  filters_extraction_parameters_template_id:
                    extractionParameterTemplateId,
                }),
            restarted_conversion: false,
            icu_enabled: icuEnabled,
          })
            .then(({data, warnings}) => {
              clearInterval(interval)
              setUploadedFilesNames((prev) => prev.concat([name]))
              if (data.data.zipFiles) {
                data.data.zipFiles.reverse().forEach((zipFile) => {
                  setFiles((prevFiles) => {
                    const index = prevFiles.findIndex((cf) => cf.name === name)
                    return [
                      ...prevFiles.slice(0, index + 1),
                      {
                        name: zipFile.name,
                        uploadProgress: 100,
                        convertedProgress: 100,
                        converted: true,
                        uploaded: true,
                        error: null,
                        zipFolder: true,
                        size: zipFile.size,
                      },
                      ...prevFiles.slice(index + 1),
                    ]
                  })
                  setUploadedFilesNames((prev) => {
                    const index = prev.findIndex((cf) => cf === name)
                    return [
                      ...prev.slice(0, index + 1),
                      zipFile.name,
                      ...prev.slice(index + 1),
                    ]
                  })
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
            .catch(({data, errors}) => {
              clearInterval(interval)
              if (data.data.zipFiles && data) {
                data.data.zipFiles.forEach((zipFile) => {
                  setFiles((prevFiles) =>
                    prevFiles.concat({
                      name: zipFile.name,
                      uploadProgress: 100,
                      convertedProgress: 100,
                      converted: true,
                      uploaded: true,
                      error: errors.find((item) => item.name === zipFile.name)
                        ? errors.find((item) => item.name === zipFile.name)
                            .message
                        : false,
                      zipFolder: true,
                      size: zipFile.size,
                    }),
                  )
                  setFiles((prevFiles) =>
                    prevFiles.map((f) =>
                      f.file === file
                        ? {
                            ...f,
                            convertedProgress: 100,
                            converted: true,
                          }
                        : f,
                    ),
                  )
                  setUploadedFilesNames((prev) => prev.concat([zipFile.name]))
                })
              } else if (errors?.length > 0) {
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
              } else {
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
              }
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
    const {ext, size, error} = file
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
    } else if (error === UPLOAD_ERRORS.EMPTY_FILE) {
      return 'Error: File is empty'
    }
  }

  const restartConversions = () => {
    clearIntervals()
    CreateProjectActions.enableAnalyzeButton(false)
    setFiles((prevFiles) =>
      prevFiles.map((f) => ({...f, converted: false, convertedProgress: 0})),
    )

    files.forEach((f) => {
      if (f.uploaded && !f.error && !f.zipFolder) {
        const interval = startConvertFakeProgress(f.file)
        filesInterval.current.push(interval)
        convertFileRequest({
          file_name: f.name,
          source_lang: sourceLang.code,
          target_lang: targetLangs.map((lang) => lang.id).join(),
          segmentation_rule: segmentationRule,
          icu_enabled: icuEnabled,
          ...(typeof currentFiltersExtractionParameters === 'object'
            ? {
                filters_extraction_parameters_template: JSON.stringify(
                  currentFiltersExtractionParameters,
                ),
              }
            : {
                filters_extraction_parameters_template_id:
                  extractionParameterTemplateId,
              }),
        })
          .then(({data, warnings}) => {
            clearInterval(interval)
            setFiles((prevFiles) =>
              prevFiles.map((file) =>
                file.file === f.file
                  ? {
                      ...file,
                      convertedProgress: 100,
                      converted: true,
                      warning: warnings ? warnings[0].message : null,
                    }
                  : file,
              ),
            )
            if (data.data.zipFiles) {
              data.data.zipFiles.forEach((zipFile) => {
                setFiles((prevFiles) =>
                  prevFiles.map((file) =>
                    zipFile.name === file.name
                      ? {
                          ...file,
                          convertedProgress: 100,
                          converted: true,
                        }
                      : file,
                  ),
                )
              })
            }
            CreateProjectActions.enableAnalyzeButton(true)
          })
          .catch((errors) => {
            clearInterval(interval)
            setFiles((prevFiles) =>
              prevFiles.map((file) =>
                file.file === f.file
                  ? {
                      ...file,
                      uploaded: false,
                      error: errors?.length
                        ? errors[0].message
                        : 'Server error, try again.',
                    }
                  : file,
              ),
            )
          })
      }
    })
  }

  const deleteFile = (file) => {
    setFiles((prevFiles) => prevFiles.filter((f) => f.name !== file.name))
    setUploadedFilesNames((prev) => prev.filter((f) => f !== file.name))
    fileUploadDelete({
      file: file.name,
      source: sourceLang.code,
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

    // check if it removes tm key created from file
    if (file.ext === EXTENSIONS.tmx) {
      if (files.filter(({ext}) => ext === file.ext).length > 1) {
        const tmFromFileName = tmKeys.find(
          ({isTmFromFile}) => isTmFromFile,
        ).name
        if (tmFromFileName === file.name) {
          const filteredFilesTmx = files
            .filter(({name}) => name !== file.name)
            .filter(({ext}) => ext === file.ext)

          const newTmFromFileName = filteredFilesTmx[0].name
          setTmKeys((prevState) =>
            prevState.map((tm) =>
              tm.isTmFromFile ? {...tm, name: newTmFromFileName} : tm,
            ),
          )
          modifyingCurrentTemplate((prevTemplate) => ({
            ...prevTemplate,
            tm: prevTemplate.tm.map((tm) =>
              tm.isTmFromFile ? {...tm, name: newTmFromFileName} : tm,
            ),
          }))
        }
      } else {
        setTmKeys((prevState) =>
          prevState.filter(({isTmFromFile}) => !isTmFromFile),
        )
        modifyingCurrentTemplate((prevTemplate) => ({
          ...prevTemplate,
          tm: prevTemplate.tm.filter(({isTmFromFile}) => !isTmFromFile),
        }))
      }
    }
  }

  const deleteAllFiles = () => {
    clearIntervals()
    files.forEach((file) => {
      fileUploadDelete({
        file: file.name,
        source: sourceLang.code,
        segmentationRule,
        filtersTemplateId: extractionParameterTemplateId,
      })
    })

    CreateProjectActions.hideErrors()

    // check if it removes tm key created from file
    if (files.some(({ext}) => ext === EXTENSIONS.tmx)) {
      setTmKeys((prevState) =>
        prevState.filter(({isTmFromFile}) => !isTmFromFile),
      )
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        tm: prevTemplate.tm.filter(({isTmFromFile}) => !isTmFromFile),
      }))
    }

    setFiles([])
    setUploadedFilesNames([])
  }

  const handleDrop = useCallback(
    (e) => {
      e.preventDefault()
      CreateProjectActions.hideErrors()
      dragCounter.current = 0
      let files = Array.from(e.dataTransfer.files)

      for (var i = 0; i < files.length; i++) {
        // iterate in the files dropped
        let f = files[i]
        if (f.type === '' && f.size % 4096 === 0) {
          CreateProjectActions.showError(
            'Uploading unzipped folders is not allowed. Please upload individual files, or a zipped folder.',
          )
          files = files.filter((file) => file !== f)
        }
      }
      handleFiles(files)
      setIsDragging(false)
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
    handleFiles(Array.from(e.target.files))
    e.target.value = ''
  }

  const handleDragOver = (e) => {
    e.preventDefault()
  }

  const clearIntervals = () => {
    filesInterval.current.forEach((interval) => clearInterval(interval))
    filesInterval.current = []
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
              <div
                key={idx}
                className={`file-item ${f.zipFolder ? 'zip-folder' : ''}`}
              >
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
            <Button type={BUTTON_TYPE.WARNING} onClick={deleteAllFiles}>
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
