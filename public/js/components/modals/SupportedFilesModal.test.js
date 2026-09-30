import React from 'react'
import {render, screen} from '@testing-library/react'
import SupportedFilesModal from './SupportedFilesModal'

test('renders a format box with icon and extension for every supported file group', () => {
  const supportedFiles = {
    'Text formats': [[{ext: 'txt'}]],
    'Office formats': [[{ext: 'docx'}], [{ext: 'xlsx'}]],
  }

  render(<SupportedFilesModal supportedFiles={supportedFiles} />)

  expect(screen.getByText('Text formats')).toBeInTheDocument()
  expect(screen.getByText('Office formats')).toBeInTheDocument()
  expect(screen.getByText('txt')).toBeInTheDocument()
  expect(screen.getByText('docx')).toBeInTheDocument()
  expect(screen.getByText('xlsx')).toBeInTheDocument()
})

test('renders nothing when there are no supported file groups', () => {
  const {container} = render(<SupportedFilesModal supportedFiles={{}} />)

  expect(container.querySelector('.format-box')).not.toBeInTheDocument()
})
