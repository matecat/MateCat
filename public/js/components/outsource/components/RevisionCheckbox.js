import React from 'react'
import {formatWithCommas} from '../outsourceConstants'
import {Checkbox, CHECKBOX_SIZE, CHECKBOX_STATE} from '../../common/Checkbox'

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
        <div className={'revision-checkbox ' + checkboxDisabledClass}>
          <Checkbox
            label="Add revision"
            value={revision ? CHECKBOX_STATE.CHECKED : CHECKBOX_STATE.UNCHECKED}
            onChange={onToggle}
            disabled={outsourceConfirmed}
          />
        </div>
      </div>
      {!outsourceConfirmed && (
        <div className="job-price">
          + {priceCurrencySymbol}{' '}
          {formatWithCommas(getCurrencyPrice(revisionPrice))}
        </div>
      )}
    </div>
  )
}

export default RevisionCheckbox
