import React, {Fragment} from 'react'
import PropTypes from 'prop-types'

export const TmKeyRow = ({row}) => {
  const {name} = row

  return (
    <Fragment>
      <input type="checkbox" />
      <input type="checkbox" />
      <span>{name}</span>
      <span>I</span>
      <button>Import TMX</button>
    </Fragment>
  )
}

TmKeyRow.propTypes = {
  row: PropTypes.object.isRequired,
}
