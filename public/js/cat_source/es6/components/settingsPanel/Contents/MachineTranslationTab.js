import React, {useState} from 'react'
import IconAdd from '../../icons/IconAdd'

export const MachineTranslationTab = () => {
  const [addMTVisible, setAddMTVisible] = useState(false)
  return (
    <div className="machine-translation-tab">
      {!addMTVisible ? (
        <div className="add-mt-button">
          <button
            className="ui primary button"
            onClick={() => setAddMTVisible(true)}
          >
            <IconAdd /> Add MT engine
          </button>
        </div>
      ) : (
        <div className="add-mt-container">
          <h2>Add New MT</h2>
          <button
            className="ui button orange"
            onClick={() => setAddMTVisible(false)}
          >
            Close
          </button>
        </div>
      )}
    </div>
  )
}
