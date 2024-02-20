import React from 'react'
import PropTypes from 'prop-types'
import ConfirmMessageModal from './ConfirmMessageModal'

export const ConfirmDeleteResourceProjectTemplates = ({
  projectTemplatesInvolved,
  content,
  successCallback,
  ...rest
}) => {
  const props = {
    ...rest,
    text: (
      <div className="confirm-delete-resource-project-templates">
        {content}:
        <ul>
          {projectTemplatesInvolved.map(({name}, index) => (
            <li key={index}>{name}</li>
          ))}
        </ul>
        If you confirm, it will be removed from the template(s).
      </div>
    ),
    successText: 'Continue',
    cancelText: 'Cancel',
    successCallback,
    closeOnSuccess: true,
  }

  return <ConfirmMessageModal {...props} />
}

ConfirmDeleteResourceProjectTemplates.propTypes = {
  projectTemplatesInvolved: PropTypes.array.isRequired,
  content: PropTypes.string.isRequired,
  successCallback: PropTypes.func.isRequired,
}
