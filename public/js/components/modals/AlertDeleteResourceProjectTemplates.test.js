import React from 'react'
import {render, screen} from '@testing-library/react'
import {AlertDeleteResourceProjectTemplates} from './AlertDeleteResourceProjectTemplates'

test('renders content and the list of involved project templates', () => {
  const {container} = render(
    <AlertDeleteResourceProjectTemplates
      content="Some glossaries were removed."
      projectTemplatesInvolved={[{name: 'Template A'}, {name: 'Template B'}]}
      onClose={jest.fn()}
    />,
  )

  const wrapper = container.querySelector(
    '.confirm-delete-resource-project-templates',
  )
  expect(wrapper).toHaveTextContent('Some glossaries were removed.')
  expect(screen.getByText('Template A')).toBeInTheDocument()
  expect(screen.getByText('Template B')).toBeInTheDocument()
  expect(wrapper).toHaveTextContent(
    /All deleted glossaries have been removed from the template/,
  )
})

test('renders no list when no project templates are involved', () => {
  const {container} = render(
    <AlertDeleteResourceProjectTemplates
      content="No templates affected."
      projectTemplatesInvolved={[]}
      onClose={jest.fn()}
    />,
  )

  expect(container.querySelector('ul')).not.toBeInTheDocument()
})
