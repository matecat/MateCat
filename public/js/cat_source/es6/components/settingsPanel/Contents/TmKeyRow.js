import React, {Fragment, useContext, useState} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from '../SettingsPanelContext'

export const TmKeyRow = ({row}) => {
  const {setTmKeys} = useContext(SettingsPanelContext)

  const [isActive, setIsActive] = useState(row.isActive ?? false)
  const [name, setName] = useState(row.name)

  const onChangeIsActive = (e) => {
    const isActive = e.currentTarget.checked

    setTmKeys((prevState) =>
      prevState.map((tm) => (tm.id === row.id ? {...tm, isActive} : tm)),
    )
    setIsActive(isActive)
  }

  return (
    <Fragment>
      <div className="tm-key-lookup">
        <input checked={isActive} onChange={onChangeIsActive} type="checkbox" />
      </div>
      <div className="tm-key-update">
        <input type="checkbox" disabled={!row.isActive} />
      </div>
      <input
        className="tm-key-row-name"
        value={name}
        onChange={(e) => setName(e.currentTarget.value)}
      ></input>
      <span>I</span>
      <button>Import TMX</button>
    </Fragment>
  )
}

TmKeyRow.propTypes = {
  row: PropTypes.object.isRequired,
}
