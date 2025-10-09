import React, {Fragment, useContext, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import LabelWithTooltip from '../../../../common/LabelWithTooltip'
import {SettingsPanelContext} from '../../../SettingsPanelContext'

export const LaraGlossaryRow = ({row, setRows, isReadOnly}) => {
  const {portalTarget} = useContext(SettingsPanelContext)

  const [isActive, setIsActive] = useState(row.isActive)

  useEffect(() => {
    setIsActive(row.isActive)
  }, [row.isActive])

  const onChangeIsActive = (e) => {
    const isActive = e.currentTarget.checked
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
        <input
          checked={isActive}
          onChange={onChangeIsActive}
          type="checkbox"
          disabled={isReadOnly}
          data-testid={`laraglossary-active-${row.id}`}
        />
      </div>
      <div className="glossary-row-name">
        {setInputNameContainer(
          <input
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
