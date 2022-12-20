import React from 'react'

const SupportedFilesModal = ({supportedFiles}) => {
  const keys = Object.keys(supportedFiles)
  return (
    <div className="supported-formats">
      <div className="fileformat">
        {keys.map((name) => (
          <div className="format-box" key={name}>
            <h3>{name}</h3>
            <ul>
              {supportedFiles[name].map((item, index) => (
                <li key={index}>
                  <span className={item[0].class}>{item[0].ext}</span>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </div>
    </div>
  )
}

export default SupportedFilesModal
