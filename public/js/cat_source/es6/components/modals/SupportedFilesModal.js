import React from 'react'

const SupportedFilesModal = () => {
  const supportedFiles = config.supported_file_types_array
  const keys = Object.keys(supportedFiles)

  return (
    <div className="supported-formats">
      <div className="fileformat">
        {keys.map((name) => (
          <div className="format-box" key={name}>
            <h3>{name}</h3>
            <div className={'file-list'}>
              {supportedFiles[name].map((item, index) => (
                <div key={index}>
                  <span className={item[0].class}>{item[0].ext}</span>
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
