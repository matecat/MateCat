import React from 'react'
import CommonUtils from '../../utils/commonUtils'

const isZip = (item) => item[0].ext === 'zip'

const SupportedFilesModal = ({supportedFiles}) => {
  const keys = Object.keys(supportedFiles)
  // A ZIP is a container for other formats, not a format of its own: it gets a
  // note instead of a section.
  const acceptsZip = keys.some((name) => supportedFiles[name].some(isZip))
  const formats = keys
    .map((name) => ({
      name,
      items: supportedFiles[name].filter((item) => !isZip(item)),
    }))
    .filter(({items}) => items.length > 0)
    .sort((a, b) => b.items.length - a.items.length)

  return (
    <div className="supported-formats">
      <div className="fileformat">
        {formats.map(({name, items}) => (
          <div className="format-box" key={name}>
            <h4>{name}</h4>
            <div className={'file-list'}>
              {items.map((item, index) => (
                <div key={index}>
                  {CommonUtils.getFileIcon(item[0].ext)}
                  <span>{item[0].ext}</span>
                </div>
              ))}
            </div>
          </div>
        ))}
        {acceptsZip && (
          <p className="zip-note">
            You can also upload ZIP archives containing files in any of these
            formats.
          </p>
        )}
      </div>
    </div>
  )
}

export default SupportedFilesModal
