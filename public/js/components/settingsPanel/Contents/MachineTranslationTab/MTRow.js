import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import InfoIcon from '../../../../../img/icons/InfoIcon'
import {Button, BUTTON_SIZE} from '../../../common/Button/Button'
import Trash from '../../../../../img/icons/Trash'

export const MTRow = ({row, deleteMT, onCheckboxClick}) => {
  const {currentProjectTemplate} = useContext(SettingsPanelContext)
  const activeMTEngine = currentProjectTemplate.mt?.id

  return (
    <>
      <div className="settings-panel-cell-center">
        <input
          type="checkbox"
          title="Use in this project"
          data-testid={`checkbox-mt-active-${row.name}`}
          checked={row.id === activeMTEngine ? true : false}
          onChange={() => onCheckboxClick(row)}
          disabled={config.is_cattool && row.id === activeMTEngine}
        ></input>
      </div>

      <div className="settings-panel-mt-row">
        {row.name}
        {row.engine_type === 'MMTLite' && (
          <a
            href="https://guides.matecat.com/default-machine-translation-engine"
            target="_blank"
            rel="noreferrer"
          >
            <InfoIcon />
          </a>
        )}
        {row.engine_type === 'MMT' && (
          <a
            href="https://guides.matecat.com/modernmt-mmt-plug-in"
            target="_blank"
            rel="noreferrer"
          >
            <InfoIcon />
          </a>
        )}
        {row.engine_type === 'DeepL' && (
          <a
            href="https://guides.matecat.com/deepl"
            target="_blank"
            rel="noreferrer"
          >
            <InfoIcon />
          </a>
        )}
        {row.engine_type === 'Lara' && (
          <a
            href="https://guides.matecat.com/lara"
            target="_blank"
            rel="noreferrer"
          >
            <InfoIcon />
          </a>
        )}
        {row.engine_type === 'Intento' && (
          <a
            href="https://guides.matecat.com/intento"
            target="_blank"
            rel="noreferrer"
          >
            <InfoIcon />
          </a>
        )}
      </div>
      <div className="settings-panel-mt-row-description">{row.description}</div>
      {!row.default && !config.is_cattool && (
        <div>
          <Button
            className="settings-panel-grey-button"
            size={BUTTON_SIZE.SMALL}
            onClick={deleteMT}
          >
            <Trash size={16} /> Delete
          </Button>
        </div>
      )}
    </>
  )
}

MTRow.propTypes = {
  row: PropTypes.object.isRequired,
  deleteMT: PropTypes.func,
  onCheckboxClick: PropTypes.func,
}
