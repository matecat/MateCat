import React, {useState} from 'react'
import PropTypes from 'prop-types'

import Checkmark from '../../../../../../../img/icons/Checkmark'
import Close from '../../../../../../../img/icons/Close'

export const ImportTMX = ({row}) => {
  const [files, setFiles] = useState([])

  const onChangeFiles = (e) => {
    if (e.target.files) setFiles(Array.from(e.target.files))
  }

  const onSubmit = (event) => {
    console.log(event.target)

    event.preventDefault()
  }

  const onReset = () => setFiles([])

  return (
    <div className="translation-memory-glossary-tab-import-tmx">
      <form className="import-tmx-form" onSubmit={onSubmit} onReset={onReset}>
        <div>
          <span>Select a tmx file to import</span>
          <input
            type="file"
            onChange={onChangeFiles}
            name="uploaded_file[]"
            accept=".tmx"
            multiple="multiple"
          />
        </div>
        <div className="translation-memory-glossary-tab-buttons-group align-center">
          {files.length > 0 && (
            <button
              type="submit"
              className="ui primary button settings-panel-button-icon tm-key-create-resource-row-button"
            >
              <Checkmark size={16} />
              Confirm
            </button>
          )}

          <button
            type="reset"
            className="ui button orange tm-key-create-resource-row-button"
          >
            <Close />
          </button>
        </div>
      </form>
    </div>
  )
}

ImportTMX.propTypes = {
  row: PropTypes.object.isRequired,
}
