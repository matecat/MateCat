import React from 'react'
import PropTypes from 'prop-types'
import AlertModal from './AlertModal'

export const AlertDeleteResourceProjectTemplates = ({
  projectTemplatesInvolved,
  content,
  ...rest
}) => {
  const props = {
    ...rest,
    text: (
      <div className="confirm-delete-resource-project-templates">
        {content}
        {projectTemplatesInvolved.length > 0 && (
          <ul>
            {projectTemplatesInvolved.map(({name}, index) => (
              <li key={index}>{name}</li>
            ))}
          </ul>
        )}
        All deleted glossaries have been removed from the template(s)
      </div>
    ),
  }

  return <AlertModal {...props} />
}

AlertDeleteResourceProjectTemplates.propTypes = {
  projectTemplatesInvolved: PropTypes.array.isRequired,
  content: PropTypes.string.isRequired,
}
