import React from 'react'
import {render, screen} from '@testing-library/react'
import SupportedFilesModal from './SupportedFilesModal'
import {getSupportedFiles} from '../../api/getSupportedFiles'

jest.mock('../../api/getSupportedFiles', () => ({
  getSupportedFiles: jest.fn(),
}))

beforeEach(() => {
  getSupportedFiles.mockReset()
})

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

test('replaces the ZIP group with a note instead of listing it as a format', () => {
  const supportedFiles = {
    Archives: [[{ext: 'zip'}]],
    Documents: [[{ext: 'docx'}]],
  }

  render(<SupportedFilesModal supportedFiles={supportedFiles} />)

  expect(screen.queryByText('Archives')).not.toBeInTheDocument()
  expect(screen.queryByText('zip')).not.toBeInTheDocument()
  expect(screen.getByText('docx')).toBeInTheDocument()
  expect(
    screen.getByText(
      'You can also upload ZIP archives containing files in any of these formats.',
    ),
  ).toBeInTheDocument()
})

test('shows no ZIP note when ZIP is not accepted', () => {
  render(
    <SupportedFilesModal supportedFiles={{Documents: [[{ext: 'docx'}]]}} />,
  )

  expect(screen.queryByText(/ZIP archive/)).not.toBeInTheDocument()
})

test('lists the groups with the most formats first', () => {
  const supportedFiles = {
    Images: [[{ext: 'png'}]],
    Documents: [[{ext: 'doc'}], [{ext: 'docx'}], [{ext: 'pdf'}]],
    Subtitling: [[{ext: 'srt'}], [{ext: 'vtt'}]],
  }

  render(<SupportedFilesModal supportedFiles={supportedFiles} />)

  expect(
    screen.getAllByRole('heading', {level: 4}).map((h) => h.textContent),
  ).toEqual(['Documents', 'Subtitling', 'Images'])
})

test('fetches the list itself when opened before the page has loaded it', async () => {
  getSupportedFiles.mockResolvedValue({Documents: [[{ext: 'docx'}]]})

  render(<SupportedFilesModal supportedFiles={undefined} />)

  expect(await screen.findByText('docx')).toBeInTheDocument()
  expect(getSupportedFiles).toHaveBeenCalledTimes(1)
})

test('does not fetch the list when the page passes it in', () => {
  render(
    <SupportedFilesModal supportedFiles={{Documents: [[{ext: 'docx'}]]}} />,
  )

  expect(getSupportedFiles).not.toHaveBeenCalled()
})
