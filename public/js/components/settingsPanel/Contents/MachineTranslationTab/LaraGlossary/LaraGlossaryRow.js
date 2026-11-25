import React, {Fragment, useContext, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import LabelWithTooltip from '../../../../common/LabelWithTooltip'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import {
  Checkbox,
  CHECKBOX_SIZE,
  CHECKBOX_STATE,
} from '../../../../common/Checkbox'

export const LaraGlossaryRow = ({row, setRows, isReadOnly}) => {
  const {portalTarget} = useContext(SettingsPanelContext)

  const [isActive, setIsActive] = useState(row.isActive)

  useEffect(() => {
    setIsActive(row.isActive)
  }, [row.isActive])

  const onChangeIsActive = (isChecked) => {
    const isActive = isChecked
    setIsActive(isActive)
    setRows((prevState) =>
      prevState.map((glossary) =>
        glossary.id === row.id ? {...glossary, isActive} : glossary,
      ),
    )
  }

  const setInputNameContainer = (children) => (
    <LabelWithTooltip
      className="tooltip-input-name"
      tooltipTarget={portalTarget}
    >
      {children}
    </LabelWithTooltip>
  )

  return (
    <Fragment>
      <div>
        <Checkbox
          value={isActive ? CHECKBOX_STATE.CHECKED : CHECKBOX_STATE.UNCHECKED}
          size={CHECKBOX_SIZE.SMALL}
          onChange={onChangeIsActive}
          disabled={isReadOnly}
          testId={`laraglossary-active-${row.id}`}
        />
      </div>
      <div className="glossary-row-name">
        {setInputNameContainer(
          <input
            type="text"
            className="glossary-row-name-input"
            value={row.name}
            disabled={true}
          />,
        )}
      </div>
    </Fragment>
  )
}

LaraGlossaryRow.propTypes = {
  row: PropTypes.object,
  setRows: PropTypes.func,
  isReadOnly: PropTypes.bool,
}
