import React, {useState} from 'react'
import {fileUpload} from '../../api/fileUpload'
import {convertFileRequest} from '../../api/convertFileRequest'
import CreateProjectActions from '../../actions/CreateProjectActions'
import {Button, BUTTON_SIZE} from '../common/Button/Button'
import {DeleteIcon} from '../segments/SegmentFooterTabGlossary'
import {fileUploadDelete} from '../../api/fileUploadDelete'

function UploadFile({
  uplodedFilesNames,
  sourceLang,
  targetLangs,
  xliffConfigTemplateId,
  segmentationRule,
}) {
  const [files, setFiles] = useState([])
  const [isDragging, setIsDragging] = useState(false)

  const handleFiles = (selectedFiles) => {
    const fileList = Array.from(selectedFiles).map((file) => ({
      file,
      progress: 0,
      uploaded: false,
      error: null,
      zipFolder: false,
      size: 0,
    }))
    setFiles((prevFiles) => prevFiles.concat(fileList))
    fileList.forEach(({file}) => {
      const onProgress = (progress) => {
        setFiles((prevFiles) =>
          prevFiles.map((f) => (f.file === file ? {...f, progress} : f)),
        )
      }

      const onSuccess = (files) => {
        const fileResponse = JSON.parse(files)[0]
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
          file_name: file.name,
          source_lang: sourceLang,
          target_lang: targetLangs.map((lang) => lang.id).join(),
          segmentation_rule: segmentationRule,
          filters_extraction_parameters_template_id: xliffConfigTemplateId,
          restarted_conversion: false,
        }).then(({data}) => {
          if (data.data.zipFiles) {
            const zipFiles = JSON.parse(data.data.zipFiles)
            zipFiles.forEach((zipFile) => {
              setFiles((prevFiles) =>
                prevFiles.map((f) =>
                  f.file === file
                    ? {
                        ...zipFile.name,
                        uploaded: true,
                        zipFolder: true,
                        size: fileResponse.size,
                        type: fileResponse.type,
                      }
                    : f,
                ),
              )
              uplodedFilesNames.push(zipFile)
            })
          } else {
            uplodedFilesNames.push(file.name)
          }
          CreateProjectActions.enableAnalyzeButton(true)
        })
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
    setFiles((prevFiles) => prevFiles.filter((f) => f.file.name !== fileName))
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
  }

  const handleChange = (e) => {
    handleFiles(e.target.files)
  }

  const handleDragOver = (e) => {
    e.preventDefault()
  }
  const handleDragEnter = (e) => {
    e.preventDefault()
    setIsDragging(true)
  }

  const handleDragLeave = (e) => {
    e.preventDefault()
    setIsDragging(false)
  }

  return (
    <div>
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
            {f.file.name} - {f.progress.toFixed(2)}%
            {f.uploaded && ' (Completato) - size:' + f.size}
            {f.error && ` (Errore: ${f.error})`}
            <Button
              size={BUTTON_SIZE.ICON_SMALL}
              onClick={() => deleteFile(f.file.name)}
            >
              <DeleteIcon />
            </Button>
          </li>
        ))}
      </ul>
    </div>
  )
}

export default UploadFile
