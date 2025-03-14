import React, {useContext} from 'react'
import UploadFileLocal from './UploadFileLocal'
import {UploadGdrive} from './UploadGdrive'
import ModalsActions from '../../actions/ModalsActions'
import {CreateProjectContext} from './CreateProjectContext'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'

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
  const {openGDrive} = useContext(CreateProjectContext)
  const {isUserLogged} = useContext(ApplicationWrapperContext)
  return typeof isUserLogged === 'boolean' ? (
    isUserLogged ? (
      <>
        {!openGDrive && <UploadFileLocal {...props} />}

        <UploadGdrive {...props} />
      </>
    ) : (
      <div className="upload-box-not-logged">
        <h2>
          <a onClick={ModalsActions.openLoginModal}>Sign in</a> to create a
          project.
        </h2>
        <span>Start translating now!</span>
      </div>
    )
  ) : (
    <div className="upload-waiting-logged"></div>
  )
}
