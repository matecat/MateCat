import React from 'react'
import {formatPriceWithCommas} from '../outsourceConstants'

const RevisionCheckbox = ({
  revision,
  outsourceConfirmed,
  onToggle,
  priceCurrencySymbol,
  getCurrencyPrice,
  revisionPrice,
}) => {
  const checkboxDisabledClass = outsourceConfirmed ? 'disabled' : ''

  return (
    <div className="revision-box">
      <div className="add-revision">
        <div className={'ui checkbox ' + checkboxDisabledClass}>
          <input
            type="checkbox"
            checked={revision}
            onChange={onToggle}
          />
          <label>Add Revision</label>
        </div>
      </div>
      {!outsourceConfirmed && (
        <div className="job-price">
          {priceCurrencySymbol}{' '}
          {formatPriceWithCommas(getCurrencyPrice(revisionPrice))}
        </div>
      )}
    </div>
  )
}

export default RevisionCheckbox

