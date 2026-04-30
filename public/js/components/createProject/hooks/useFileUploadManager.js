import {useState, useRef, useCallback, useMemo, useEffect} from 'react'
import {isEqual} from 'lodash'
import {fileUpload} from '../../../api/fileUpload'
import {convertFileRequest} from '../../../api/convertFileRequest'
import {fileUploadDelete} from '../../../api/fileUploadDelete'
import CreateProjectActions from '../../../actions/CreateProjectActions'
import CommonUtils from '../../../utils/commonUtils'

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

/**
 * Custom hook that manages file upload lifecycle:
 * uploading, converting, deleting, and restarting conversions.
 */
export function useFileUploadManager({
  sourceLang,
  targetLangs,
  segmentationRule,
  extractionParameterTemplateId,
  currentFiltersExtractionParameters,
  icuEnabled,
  setUploadedFilesNames,
  tmKeys,
  setTmKeys,
  modifyingCurrentTemplate,
}) {
  const [files, setFiles] = useState([])
  const filesInterval = useRef([])
  const previousFiltersExtractionParameters = useRef()

  // --- helpers ---

  const clearIntervals = useCallback(() => {
    filesInterval.current.forEach((interval) => clearInterval(interval))
    filesInterval.current = []
  }, [])

  const startConvertFakeProgress = useCallback((file) => {
    let step = 0.5
    let currentProgress = 0
    return setInterval(() => {
      currentProgress += step
      const progress =
        Math.round((Math.atan(currentProgress) / (Math.PI / 2)) * 100 * 1000) /
        1000

      setFiles((prevFiles) =>
        prevFiles.map((f) =>
          f.file === file ? {...f, convertProgress: progress} : f,
        ),
      )
      if (progress >= 70) {
        step = 0.1
      }
    }, 100)
  }, [])

  const getFileErrorMessage = useCallback((file) => {
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
  }, [])

  const buildConvertParams = useCallback(
    (fileName, isRestart = false) => ({
      file_name: fileName,
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
      ...(isRestart ? {} : {restarted_conversion: false}),
    }),
    [
      sourceLang,
      targetLangs,
      segmentationRule,
      icuEnabled,
      currentFiltersExtractionParameters,
      extractionParameterTemplateId,
    ],
  )

  // --- main actions ---

  const handleConvertSuccess = useCallback(
    ({data, warnings, file, name, ext, interval}) => {
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
        CreateProjectActions.createKeyFromTMXFile({filename: file.name})
      }
      CreateProjectActions.enableAnalyzeButton(true)
    },
    [setUploadedFilesNames],
  )

  const handleConvertError = useCallback(
    ({data, errors, file, name, interval}) => {
      clearInterval(interval)
      if (data?.data?.zipFiles && data) {
        data.data.zipFiles.forEach((zipFile) => {
          setFiles((prevFiles) =>
            prevFiles.concat({
              name: zipFile.name,
              uploadProgress: 100,
              convertedProgress: 100,
              converted: true,
              uploaded: true,
              error: errors?.find((item) => item.name === zipFile.name)
                ? errors.find((item) => item.name === zipFile.name).message
                : false,
              zipFolder: true,
              size: zipFile.size,
            }),
          )
          setFiles((prevFiles) =>
            prevFiles.map((f) =>
              f.file === file
                ? {...f, convertedProgress: 100, converted: true}
                : f,
            ),
          )
          setUploadedFilesNames((prev) => prev.concat([zipFile.name]))
        })
      } else if (errors?.length > 0) {
        setFiles((prevFiles) =>
          prevFiles.map((f) =>
            f.file === file
              ? {...f, uploaded: false, error: errors[0].message}
              : f,
          ),
        )
      } else {
        setFiles((prevFiles) =>
          prevFiles.map((f) =>
            f.file === file
              ? {...f, uploaded: false, error: 'Server error, try again.'}
              : f,
          ),
        )
      }
    },
    [setUploadedFilesNames],
  )

  const handleFiles = useCallback(
    (selectedFiles) => {
      const fileList = selectedFiles.map((file) => {
        let name = file.name
        const filesSameName = files.filter((f) => f.originalName === name)
        if (filesSameName.length > 0) {
          name = `${file.name.split('.')[0]}_(${filesSameName.length}).${file.name.split('.')[1]}`
        }
        const ext = file.name.split('.').pop()
        CommonUtils.dispatchCustomEvent('uploaded-file', {extension: ext})
        return {
          file,
          originalName: file.name,
          name,
          uploadProgress: 0,
          convertProgress: 0,
          uploaded: false,
          converted: false,
          error: null,
          zipFolder: false,
          size: 0,
          ext,
        }
      })

      const totalFiles = files.length + fileList.length
      if (totalFiles > config.maxNumberFiles) {
        const excessFiles = totalFiles - config.maxNumberFiles
        fileList.slice(-excessFiles).forEach((f) => {
          f.error = 'File limit exceeded'
        })
      }

      setFiles((prevFiles) => prevFiles.concat(fileList))

      fileList.forEach(({file, name, ext, error}) => {
        if (error) return

        const onProgress = (progress) => {
          setFiles((prevFiles) =>
            prevFiles.map((f) =>
              f.name === name ? {...f, uploadProgress: progress} : f,
            ),
          )
        }

        const onSuccess = (responseText) => {
          const fileResponse = JSON.parse(responseText)[0]
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

            convertFileRequest(buildConvertParams(name))
              .then(({data, warnings}) => {
                handleConvertSuccess({
                  data,
                  warnings,
                  file,
                  name,
                  ext,
                  interval,
                })
              })
              .catch(({data, errors}) => {
                handleConvertError({data, errors, file, name, interval})
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
    },
    [
      files,
      getFileErrorMessage,
      startConvertFakeProgress,
      buildConvertParams,
      handleConvertSuccess,
      handleConvertError,
    ],
  )

  const restartConversions = useCallback(() => {
    clearIntervals()
    CreateProjectActions.enableAnalyzeButton(false)
    setFiles((prevFiles) =>
      prevFiles.map((f) => ({...f, converted: false, convertedProgress: 0})),
    )

    files.forEach((f) => {
      if (f.uploaded && !f.error && !f.zipFolder) {
        const interval = startConvertFakeProgress(f.file)
        filesInterval.current.push(interval)

        convertFileRequest(buildConvertParams(f.name, true))
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
                      ? {...file, convertedProgress: 100, converted: true}
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
  }, [files, clearIntervals, startConvertFakeProgress, buildConvertParams])

  const deleteFile = useCallback(
    (file) => {
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
          prevFiles.filter(
            (f) => !(f.zipFolder && f.name.startsWith(file.name)),
          ),
        )
        setUploadedFilesNames((prev) =>
          prev.filter((f) => !f.startsWith(file.name)),
        )
      }

      CreateProjectActions.hideErrors()

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
    },
    [
      files,
      sourceLang,
      segmentationRule,
      extractionParameterTemplateId,
      tmKeys,
      setTmKeys,
      modifyingCurrentTemplate,
      setUploadedFilesNames,
    ],
  )

  const deleteAllFiles = useCallback(() => {
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
  }, [
    files,
    sourceLang,
    segmentationRule,
    extractionParameterTemplateId,
    clearIntervals,
    setTmKeys,
    modifyingCurrentTemplate,
    setUploadedFilesNames,
  ])

  // --- effects ---

  // Restart conversions when key params change
  useEffect(() => {
    restartConversions()
  }, [sourceLang, extractionParameterTemplateId, segmentationRule])

  // Restart conversions when unsaved filter params change
  useEffect(() => {
    if (
      !isEqual(
        currentFiltersExtractionParameters,
        previousFiltersExtractionParameters.current,
      )
    )
      restartConversions()

    previousFiltersExtractionParameters.current =
      currentFiltersExtractionParameters
  }, [currentFiltersExtractionParameters])

  // Enable/disable analyze button based on file status
  useEffect(() => {
    const hasIncompleteFiles =
      files.some((f) => !f.uploaded || !f.converted || f.error) ||
      !files.some((f) => f.ext !== EXTENSIONS.tmx)
    CreateProjectActions.enableAnalyzeButton(!hasIncompleteFiles)
    if (files.length === config.maxNumberFiles) {
      CreateProjectActions.showError(
        'No more files can be loaded (the limit of ' +
          config.maxNumberFiles +
          ' has been exceeded).',
      )
    } else if (files.length > config.maxNumberFiles) {
      CreateProjectActions.showError(
        `Maximum ${config.maxNumberFiles} files allowed. Please remove all files with errors from the list below.`,
      )
    }
  }, [files])

  return {
    files,
    handleFiles,
    deleteFile,
    deleteAllFiles,
  }
}
