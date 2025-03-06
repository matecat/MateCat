import React, {useState} from 'react'
import {fileUpload} from '../../api/fileUpload'
import {convertFileRequest} from '../../api/convertFileRequest'
import CreateProjectActions from '../../actions/CreateProjectActions'
import {Button, BUTTON_SIZE} from '../common/Button/Button'
import {DeleteIcon} from '../segments/SegmentFooterTabGlossary'
import {fileUploadDelete} from '../../api/fileUploadDelete'
import FileUploadIconBig from '../../../../../img/icons/FileUploadIconBig'
import CommonUtils from '../../utils/commonUtils'

function UploadFile({
  uplodedFilesNames,
  sourceLang,
  targetLangs,
  xliffConfigTemplateId,
  segmentationRule,
}) {
  const [files, setFiles] = useState([])
  const [isDragging, setIsDragging] = useState(false)
  const dragCounter = React.useRef(0)
  const handleFiles = (selectedFiles) => {
    const fileList = Array.from(selectedFiles).map((file) => ({
      file,
      name: file.name,
      uploadProgress: 0,
      uploaded: false,
      converted: false,
      error: null,
      zipFolder: false,
      size: 0,
    }))
    setFiles((prevFiles) => prevFiles.concat(fileList))
    fileList.forEach(({file, name}) => {
      const onProgress = (progress) => {
        setFiles((prevFiles) =>
          prevFiles.map((f) =>
            f.name === name ? {...f, uploadProgress: progress} : f,
          ),
        )
      }

      const onSuccess = (files) => {
        const fileResponse = JSON.parse(files)[0]
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
          convertFileRequest({
            file_name: name,
            source_lang: sourceLang,
            target_lang: targetLangs.map((lang) => lang.id).join(),
            segmentation_rule: segmentationRule,
            filters_extraction_parameters_template_id: xliffConfigTemplateId,
            restarted_conversion: false,
          }).then(({data}) => {
            uplodedFilesNames.push(name)
            if (data.data.zipFiles) {
              const zipFiles = JSON.parse(data.data.zipFiles)
              zipFiles.forEach((zipFile) => {
                setFiles((prevFiles) =>
                  prevFiles.concat({
                    name: zipFile.name,
                    uploadProgress: 100,
                    uploaded: true,
                    error: null,
                    zipFolder: true,
                    size: zipFile.size,
                  }),
                )
                uplodedFilesNames.push(zipFile.name)
              })
            }
            CreateProjectActions.enableAnalyzeButton(true)
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

  const deleteFile = (fileName) => {
    setFiles((prevFiles) => prevFiles.filter((f) => f.name !== fileName))
    uplodedFilesNames = uplodedFilesNames.filter((f) => f !== fileName)
    fileUploadDelete({
      file: fileName,
      source: sourceLang,
      segmentationRule,
      filtersTemplateId: xliffConfigTemplateId,
    })
  }

  const handleDrop = (e) => {
    e.preventDefault()
    handleFiles(e.dataTransfer.files)
    setIsDragging(false)
    dragCounter.current = 0
  }

  const handleChange = (e) => {
    handleFiles(e.target.files)
  }

  const handleDragOver = (e) => {
    e.preventDefault()
  }
  const handleDragEnter = (e) => {
    e.preventDefault()
    dragCounter.current += 1
    if (dragCounter.current === 1) {
      setIsDragging(true)
    }
  }

  const handleDragLeave = (e) => {
    e.preventDefault()
    dragCounter.current -= 1
    if (dragCounter.current === 0) {
      setIsDragging(false)
    }
  }

  const getPrintableFileSize = (filesizeInBytes) => {
    filesizeInBytes = filesizeInBytes / 1024
    let ext = ' KB'
    if (filesizeInBytes > 1024) {
      filesizeInBytes = filesizeInBytes / 1024
      ext = ' MB'
    }
    return Math.round(filesizeInBytes * 100, 2) / 100 + ext
  }

  return (
    <div
      className={`upload-files-container ${isDragging ? 'dragging' : ''}`}
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
      {files.length === 0 ? (
        <div className={`upload-files-start`}>
          <input
            type="file"
            multiple
            style={{display: 'none'}}
            id="fileInput"
            onChange={handleChange}
          />
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
        <div className="upload-files-list">
          {files.map((f, idx) => (
            <div key={idx} className="file-item">
              <div className="file-item-name">
                <span
                  className={`file-icon ${CommonUtils.getIconClass(
                    f.name.split('.')[f.name.split('.').length - 1],
                  )}`}
                />
                {f.name}
              </div>
              {f.error && <div className="file-item-error">{f.error}</div>}
              {f.uploaded && !f.error && getPrintableFileSize(f.size)}
              {!f.uploaded &&
                !f.error &&
                f.progress &&
                f.progress + ' Progress'}
              <Button
                size={BUTTON_SIZE.ICON_SMALL}
                onClick={() => deleteFile(f.name)}
              >
                <DeleteIcon />
              </Button>
            </div>
          ))}
        </div>
      )}
    </div>
    /*<div>
      <div
        style={{
          border: '2px dashed #ccc',
          padding: '20px',
          textAlign: 'center',
          cursor: 'pointer',
        }}
        onDrop={handleDrop}
        onDragEnter={handleDragEnter}
        onDragLeave={handleDragLeave}
        onDragOver={handleDragOver}
        onClick={() => document.getElementById('fileInput').click()}
      >
        {isDragging
          ? 'Drop it here'
          : 'Drop your files to translate them with Matecat or click here to browse'}
      </div>

      <input
        type="file"
        multiple
        style={{display: 'none'}}
        id="fileInput"
        onChange={handleChange}
      />

      <ul>
        {files.map((f, idx) => (
          <li key={idx}>
            {f.name} - {f.uploadProgress.toFixed(2)}%
            {f.uploaded && ' (Completato) - size:' + f.size}
            {f.error && ` (Errore: ${f.error})`}
            <Button
              size={BUTTON_SIZE.ICON_SMALL}
              onClick={() => deleteFile(f.name)}
            >
              <DeleteIcon />
            </Button>
          </li>
        ))}
      </ul>
    </div>*/
  )
}

export default UploadFile
