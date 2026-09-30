import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {UploadXliff} from './UploadXliff'
import {xliffToTargetUpload} from '../../api/xliffToTargetUpload'
import {saveAs} from 'file-saver'

jest.mock('../../api/xliffToTargetUpload')
jest.mock('file-saver', () => ({saveAs: jest.fn()}))

const createXliffFile = (name = 'file.xlf') =>
  new File(['<xliff></xliff>'], name, {type: 'application/xliff+xml'})

const selectFile = (container, file) => {
  const input = container.querySelector('#fileInput')
  fireEvent.change(input, {target: {files: [file]}})
}

beforeEach(() => {
  xliffToTargetUpload.mockReset()
  saveAs.mockReset()
})

describe('UploadXliff', () => {
  test('renders the empty dropzone with no files listed', () => {
    render(<UploadXliff />)

    expect(
      screen.getByText(/drag and drop your xliff here/i),
    ).toBeInTheDocument()
    expect(screen.getByText(/or click to browse/i)).toBeInTheDocument()
    expect(screen.queryByText('Clear all')).not.toBeInTheDocument()
    expect(xliffToTargetUpload).not.toHaveBeenCalled()
  })

  test('selecting a file adds it to the list and starts the upload', () => {
    const {container} = render(<UploadXliff />)
    const file = createXliffFile('source.xlf')

    selectFile(container, file)

    expect(screen.getByText('source.xlf')).toBeInTheDocument()
    expect(xliffToTargetUpload).toHaveBeenCalledWith(
      file,
      expect.any(Function),
      expect.any(Function),
      expect.any(Function),
    )
  })

  test('shows the upload progress reported by the upload function', () => {
    xliffToTargetUpload.mockImplementation((file, onProgress) => {
      onProgress(42)
    })
    const {container} = render(<UploadXliff />)

    selectFile(container, createXliffFile('progress.xlf'))

    expect(screen.getByText('Uploading')).toBeInTheDocument()
    expect(screen.getByText('42%')).toBeInTheDocument()
  })

  test('shows a success message, saves the file, and allows removing it', () => {
    const fileContent = btoa('translated content')
    xliffToTargetUpload.mockImplementation((file, onProgress, onSuccess) => {
      onSuccess(
        JSON.stringify({
          size: 2048,
          type: 'application/xml',
          fileName: 'target.xliff',
          fileContent,
        }),
      )
    })
    const {container} = render(<UploadXliff />)

    selectFile(container, createXliffFile('source.xlf'))

    expect(
      screen.getByText(/file downloaded! check your download folder/i),
    ).toBeInTheDocument()
    expect(screen.getByText('2 KB')).toBeInTheDocument()
    expect(saveAs).toHaveBeenCalledTimes(1)
    expect(saveAs.mock.calls[0][1]).toBe('target.xliff')

    fireEvent.click(screen.getByRole('button', {name: 'Remove file'}))
    expect(screen.queryByText('source.xlf')).not.toBeInTheDocument()
  })

  test('shows a generic error message when the upload function reports an error', () => {
    xliffToTargetUpload.mockImplementation(
      (file, onProgress, onSuccess, onError) => {
        onError('Connection error')
      },
    )
    const {container} = render(<UploadXliff />)

    selectFile(container, createXliffFile('source.xlf'))

    expect(
      screen.getByText(/an error occurred\. please, be sure/i),
    ).toBeInTheDocument()
  })

  test('shows the same generic error message when the upload function throws synchronously', () => {
    xliffToTargetUpload.mockImplementation(() => {
      throw new Error('boom')
    })
    const {container} = render(<UploadXliff />)

    selectFile(container, createXliffFile('source.xlf'))

    expect(
      screen.getByText(/an error occurred\. please, be sure/i),
    ).toBeInTheDocument()
  })
})
