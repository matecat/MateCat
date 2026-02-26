import React, {Fragment, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import Trash from '../../../../../../img/icons/Trash'
import {
  Button,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../../../common/Button/Button'

export const DeepLGlossaryRow = ({
  engineId,
  row,
  setRows,
  isReadOnly,
  deleteGlossaryConfirm,
}) => {
  const [isActive, setIsActive] = useState(false)
  const [isWaitingResult, setIsWaitingResult] = useState(false)

  useEffect(() => {
    setIsActive(row.isActive)
  }, [row.isActive])

  const onChangeIsActive = (e) => {
    const isActive = e.currentTarget.checked
    setRows((prevState) =>
      prevState.map((glossary) => ({
        ...glossary,
        isActive: isActive && glossary.id === row.id,
      })),
    )
  }

  return (
    <Fragment>
      <div>
        <input
          name="active"
          checked={isActive}
          onChange={onChangeIsActive}
          type="radio"
          disabled={isWaitingResult || isReadOnly}
          data-testid={`deeplglossary-active-${row.id}`}
        />
      </div>
      <div className="glossary-row-name">
        <div className="tooltip-input-name">
          <div className="glossary-row-name-input glossary-deepl-row-name-input">
            {row.name}
          </div>
        </div>
      </div>
      {!isReadOnly && (
        <>
          <div className="glossary-row-import-button" />
          <div className="glossary-row-delete">
            <Button
              className="settings-panel-grey-button"
              type={BUTTON_TYPE.ICON}
              size={BUTTON_SIZE.SMALL}
              disabled={isWaitingResult}
              onClick={() => deleteGlossaryConfirm(row)}
              testId={`delete-deeplglossary-${row.id}`}
            >
              <Trash size={16} />
            </Button>
          </div>
          {isWaitingResult && <div className="spinner"></div>}
        </>
      )}
    </Fragment>
  )
}

DeepLGlossaryRow.propTypes = {
  engineId: PropTypes.number,
  row: PropTypes.object,
  setRows: PropTypes.func,
  isReadOnly: PropTypes.bool,
}
