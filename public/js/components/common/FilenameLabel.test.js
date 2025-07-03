import {render} from '@testing-library/react'
import {screen} from '@testing-library/dom'
import React from 'react'
import {FilenameLabel} from './FilenameLabel'

test('Rendering element', () => {
  render(<FilenameLabel>testFile.srt</FilenameLabel>)

  expect(screen.getByTestId('filename-label')).toHaveTextContent('testFile.srt')
  expect(screen.getByText('testFile')).toBeInTheDocument()
  expect(screen.getByText('.srt')).toBeInTheDocument()
})
