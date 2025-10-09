import React, {Fragment, useEffect, useState} from 'react'
import PropTypes from 'prop-types'

export const DeepLGlossaryNoneRow = ({row, setRows}) => {
  const [isActive, setIsActive] = useState(false)

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
          data-testid={`deeplglossary-active-${row.id}`}
        />
      </div>
      <div className="glossary-row-name">{row.name}</div>
    </Fragment>
  )
}

DeepLGlossaryNoneRow.propTypes = {
  row: PropTypes.object,
  setRows: PropTypes.func,
}
