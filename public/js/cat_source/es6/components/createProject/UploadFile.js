import React, {useState} from 'react'
import UploadFileLocal from './UploadFileLocal'
import {UploadGdrive} from './UploadGdrive'

export const FILES_TYPE = {
  GDRIVE: 'gdrive',
  LOCAL: 'local',
}

export const getPrintableFileSize = (filesizeInBytes) => {
  filesizeInBytes = filesizeInBytes / 1024
  let ext = ' KB'
  if (filesizeInBytes > 1024) {
    filesizeInBytes = filesizeInBytes / 1024
    ext = ' MB'
  }
  return Math.round(filesizeInBytes * 100, 2) / 100 + ext
}
export const UploadFile = ({...props}) => {
  const [uploadFilesType, setUploadFilesType] = useState(FILES_TYPE.LOCAL)
  return (
    <>
      <UploadFileLocal setUploadFilesType={setUploadFilesType} {...props} />
      <UploadGdrive setUploadFilesType={setUploadFilesType} {...props} />
    </>
  )
}
