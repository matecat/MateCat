import React from 'react'
import CommonUtils from '../../utils/commonUtils'

const SupportedFilesModal = ({supportedFiles}) => {
  const keys = Object.keys(supportedFiles)

  return (
    <div className="supported-formats">
      <div className="fileformat">
        {keys.map((name) => (
          <div className="format-box" key={name}>
            <h4>{name}</h4>
            <div className={'file-list'}>
              {supportedFiles[name].map((item, index) => (
                <div key={index}>
                  {CommonUtils.getFileIcon(item[0].ext)}
                  <span>{item[0].ext}</span>
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}

export default SupportedFilesModal
