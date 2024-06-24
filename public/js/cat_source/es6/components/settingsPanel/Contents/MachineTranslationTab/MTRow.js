import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import Trash from '../../../../../../../img/icons/Trash'
import InfoIcon from '../../../../../../../img/icons/InfoIcon'

export const MTRow = ({row, deleteMT, onCheckboxClick}) => {
  const {currentProjectTemplate} = useContext(SettingsPanelContext)
  const activeMTEngine = currentProjectTemplate.mt?.id

  return (
    <>
      <div className="settings-panel-mt-row">
        {row.name}
        {row.engine_type === 'MMTLite' && (
          <a
            href="https://guides.matecat.com/default-machine-translation-engine"
            target="_blank"
          >
            <InfoIcon />
          </a>
        )}
        {row.engine_type === 'MMT' && (
          <a
            href="https://guides.matecat.com/modernmt-mmt-plug-in"
            target="_blank"
          >
            <InfoIcon />
          </a>
        )}
        {row.engine_type === 'DeepL' && (
          <a
            href="https://guides.matecat.com/machine-translation-engines#DeepL"
            target="_blank"
          >
            <InfoIcon />
          </a>
        )}
      </div>
      <div className="settings-panel-mt-row-description">{row.description}</div>
      {!config.is_cattool && (
        <div className="settings-panel-cell-center">
          <input
            type="checkbox"
            title="Use in this project"
            data-testid={`checkbox-mt-active-${row.name}`}
            checked={row.id === activeMTEngine ? true : false}
            onChange={() => onCheckboxClick(row)}
          ></input>
        </div>
      )}
      {!row.default && !config.is_cattool && (
        <div className="settings-panel-cell-center">
          <button
            className="grey-button"
            data-testid="delete-mt"
            onClick={deleteMT}
          >
            <Trash size={12} />
            Delete
          </button>
        </div>
      )}
      {config.is_cattool && row.id === activeMTEngine && (
        <>
          <div></div>
          <div className="settings-panel-cell-center">Enabled</div>
        </>
      )}
    </>
  )
}

MTRow.propTypes = {
  row: PropTypes.object.isRequired,
  deleteMT: PropTypes.func,
  onCheckboxClick: PropTypes.func,
}
