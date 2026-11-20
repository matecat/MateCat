import React, {Fragment, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import {Radio, RADIO_SIZE} from '../../../../common/Radio/Radio'

export const DeepLGlossaryNoneRow = ({row, setRows}) => {
  const [isActive, setIsActive] = useState(false)

  useEffect(() => {
    setIsActive(row.isActive)
  }, [row.isActive])

  const onChangeIsActive = (checked) => {
    const isActive = checked
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
        <Radio
          name="active"
          size={RADIO_SIZE.SMALL}
          checked={isActive}
          onChange={onChangeIsActive}
          testId={`deeplglossary-active-${row.id}`}
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
