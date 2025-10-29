import React, {useState} from 'react'
import PropTypes from 'prop-types'
import {Select} from '../../common/Select'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../common/Button/Button'
import ModalsActions from '../../../actions/ModalsActions'

export const BulkChangePassword = ({jobs, successCallback}) => {
  const [typeSelected, setTypeSelected] = useState()

  const jobsWithRevisePassword = jobs.filter(
    ({revise_passwords}) => revise_passwords && revise_passwords.length > 1,
  )

  const options = [
    {id: '0', name: 'Translate'},
    {id: '1', name: 'Revise'},
    ...(jobsWithRevisePassword.length ? [{id: '2', name: '2nd Revise'}] : []),
  ]

  const onSelect = (option) => setTypeSelected(option)

  return (
    <div className="content-bulk-modal">
      <Select
        name="teams"
        label="Change password"
        placeholder="Select"
        isPortalDropdown={true}
        options={options}
        activeOption={typeSelected}
        checkSpaceToReverse={false}
        onSelect={onSelect}
        maxHeightDroplist={300}
      />
      <div className="content-bulk-modal-control">
        <Button
          mode={BUTTON_MODE.OUTLINE}
          size={BUTTON_SIZE.MEDIUM}
          onClick={() => ModalsActions.onCloseModal()}
        >
          Cancel
        </Button>
        <Button
          type={BUTTON_TYPE.PRIMARY}
          size={BUTTON_SIZE.MEDIUM}
          disabled={typeof typeSelected === 'undefined'}
          onClick={() =>
            successCallback({
              revision_number:
                typeSelected?.id !== '0' ? parseInt(typeSelected?.id) : null,
            })
          }
        >
          Continue
        </Button>
      </div>
    </div>
  )
}

BulkChangePassword.propTypes = {
  jobs: PropTypes.array.isRequired,
  successCallback: PropTypes.func.isRequired,
}
