import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {ConfirmDeleteResourceProjectTemplates} from './ConfirmDeleteResourceProjectTemplates'

test('renders content, template list and default footer, and wires up successCallback', () => {
  const successCallback = jest.fn()
  const onClose = jest.fn()
  const {container} = render(
    <ConfirmDeleteResourceProjectTemplates
      content="Remove this glossary?"
      projectTemplatesInvolved={[{name: 'Template A'}]}
      successCallback={successCallback}
      onClose={onClose}
    />,
  )

  const wrapper = container.querySelector(
    '.confirm-delete-resource-project-templates',
  )
  expect(wrapper).toHaveTextContent('Remove this glossary?')
  expect(screen.getByText('Template A')).toBeInTheDocument()
  expect(wrapper).toHaveTextContent(
    'If you confirm, it will be removed from the template(s).',
  )

  fireEvent.click(screen.getByText('Continue'))
  expect(successCallback).toHaveBeenCalledTimes(1)
  expect(onClose).toHaveBeenCalledTimes(1)

  fireEvent.click(screen.getByText('Cancel'))
})

test('renders a custom footerContent when provided', () => {
  const {container} = render(
    <ConfirmDeleteResourceProjectTemplates
      content="c"
      projectTemplatesInvolved={[]}
      successCallback={jest.fn()}
      footerContent="Custom footer text"
      onClose={jest.fn()}
    />,
  )

  expect(
    container.querySelector('.confirm-delete-resource-project-templates'),
  ).toHaveTextContent('Custom footer text')
})
