import React from 'react'
import {render, screen, fireEvent, act, waitFor} from '@testing-library/react'
import UploadFileLocal from './UploadFileLocal'
import {CreateProjectContext} from './CreateProjectContext'
import {fileUpload} from '../../api/fileUpload'
import {convertFileRequest} from '../../api/convertFileRequest'
import {fileUploadDelete} from '../../api/fileUploadDelete'
import CreateProjectActions from '../../actions/CreateProjectActions'

// --- Mocks ---

jest.mock('../../api/fileUpload', () => ({
  fileUpload: jest.fn(),
}))

jest.mock('../../api/convertFileRequest', () => ({
  convertFileRequest: jest.fn(),
}))

jest.mock('../../api/fileUploadDelete', () => ({
  fileUploadDelete: jest.fn(() => Promise.resolve({})),
}))

jest.mock('../../actions/CreateProjectActions', () => ({
  enableAnalyzeButton: jest.fn(),
  hideErrors: jest.fn(),
  showError: jest.fn(),
  createKeyFromTMXFile: jest.fn(),
}))

jest.mock('../../utils/commonUtils', () => ({
  getIconClass: jest.fn(() => 'extdoc'),
  dispatchCustomEvent: jest.fn(),
}))

jest.mock('../../../img/icons/FileUploadIconBig', () => {
  return function MockFileUploadIconBig() {
    return <div data-testid="file-upload-icon" />
  }
})

global.config = {
  ...global.config,
  maxFileSize: 200 * 1024 * 1024, // 200MB
  maxTMXFileSize: 100 * 1024 * 1024, // 100MB
  maxNumberFiles: 10,
}

// --- Helpers ---

const defaultContextValue = {
  sourceLang: {code: 'en-US', name: 'English'},
  targetLangs: [{id: 'it-IT', name: 'Italian'}],
  currentProjectTemplate: {
    segmentationRule: {id: 'standard'},
    filters_template_id: 1,
    icuEnabled: false,
  },
  setUploadedFilesNames: jest.fn(),
  tmKeys: [],
  setTmKeys: jest.fn(),
  modifyingCurrentTemplate: jest.fn(),
  fileImportFiltersParamsTemplates: {
    templates: [],
  },
}

const renderWithContext = (contextOverrides = {}) => {
  const contextValue = {...defaultContextValue, ...contextOverrides}
  return render(
    <CreateProjectContext.Provider value={contextValue}>
      <UploadFileLocal />
    </CreateProjectContext.Provider>,
  )
}

const createMockFile = (
  name = 'test.docx',
  size = 1024,
  type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
) => {
  const file = new File(['x'.repeat(size)], name, {type})
  return file
}

/**
 * Simulate a successful upload by calling the onSuccess callback
 * that fileUpload receives.
 */
const simulateUploadSuccess = (fileResponse = {}) => {
  fileUpload.mockImplementation((file, onProgress, onSuccess) => {
    onProgress(100)
    const response = [
      {
        name: file.name,
        size: fileResponse.size ?? 2048,
        type: fileResponse.type ?? 'docx',
        ext: file.name.split('.').pop(),
        error: fileResponse.error ?? null,
        ...fileResponse,
      },
    ]
    onSuccess(JSON.stringify(response))
  })
}

const simulateUploadError = (errorMsg = 'Network error') => {
  fileUpload.mockImplementation((file, onProgress, onSuccess, onError) => {
    onError(errorMsg)
  })
}

// --- Tests ---

beforeEach(() => {
  jest.clearAllMocks()
  jest.useFakeTimers()
  convertFileRequest.mockResolvedValue({
    data: {data: {}},
    warnings: null,
  })
  fileUploadDelete.mockResolvedValue({})
})

afterEach(() => {
  jest.useRealTimers()
})

describe('UploadFileLocal', () => {
  describe('Empty state', () => {
    test('renders empty state with drag-and-drop prompt', () => {
      renderWithContext()
      expect(
        screen.getByText('Drop your files to translate them with Matecat'),
      ).toBeInTheDocument()
      expect(screen.getByText('or click to browse')).toBeInTheDocument()
      expect(screen.getByTestId('file-upload-icon')).toBeInTheDocument()
    })

    test('file input is hidden', () => {
      renderWithContext()
      const input = document.getElementById('fileInput')
      expect(input).toBeInTheDocument()
      expect(input.style.display).toBe('none')
    })
  })

  describe('File upload flow', () => {
    test('uploading a file shows file name in the list', async () => {
      simulateUploadSuccess()
      renderWithContext()

      const input = document.getElementById('fileInput')
      const file = createMockFile('document.docx')

      await act(async () => {
        fireEvent.change(input, {target: {files: [file]}})
        jest.advanceTimersByTime(200)
      })

      expect(screen.getByText('document.docx')).toBeInTheDocument()
    })

    test('shows uploaded file size after successful upload + conversion', async () => {
      simulateUploadSuccess({size: 512000})
      convertFileRequest.mockResolvedValue({
        data: {data: {}},
        warnings: null,
      })

      renderWithContext()

      const input = document.getElementById('fileInput')
      const file = createMockFile('report.docx')

      await act(async () => {
        fireEvent.change(input, {target: {files: [file]}})
        jest.advanceTimersByTime(500)
      })

      await waitFor(() => {
        expect(screen.getByText('report.docx')).toBeInTheDocument()
      })
    })

    test('calls fileUpload API when a file is selected', async () => {
      simulateUploadSuccess()
      renderWithContext()

      const input = document.getElementById('fileInput')
      const file = createMockFile('test.docx')

      await act(async () => {
        fireEvent.change(input, {target: {files: [file]}})
      })

      expect(fileUpload).toHaveBeenCalledTimes(1)
      expect(fileUpload).toHaveBeenCalledWith(
        file,
        expect.any(Function),
        expect.any(Function),
        expect.any(Function),
      )
    })

    test('calls convertFileRequest after successful upload', async () => {
      simulateUploadSuccess()
      renderWithContext()

      const input = document.getElementById('fileInput')
      const file = createMockFile('test.docx')

      await act(async () => {
        fireEvent.change(input, {target: {files: [file]}})
        jest.advanceTimersByTime(200)
      })

      expect(convertFileRequest).toHaveBeenCalledWith(
        expect.objectContaining({
          file_name: 'test.docx',
          source_lang: 'en-US',
          target_lang: 'it-IT',
          segmentation_rule: 'standard',
        }),
      )
    })

    test('upload error displays error message on the file', async () => {
      simulateUploadError('Connection error')
      renderWithContext()

      const input = document.getElementById('fileInput')
      const file = createMockFile('test.docx')

      await act(async () => {
        fireEvent.change(input, {target: {files: [file]}})
      })

      expect(screen.getByText('test.docx')).toBeInTheDocument()
    })

    test('conversion error shows error message', async () => {
      simulateUploadSuccess()
      convertFileRequest.mockRejectedValue({
        errors: [{message: 'Conversion failed'}],
      })

      renderWithContext()

      const input = document.getElementById('fileInput')
      const file = createMockFile('bad.docx')

      await act(async () => {
        fireEvent.change(input, {target: {files: [file]}})
        jest.advanceTimersByTime(200)
      })

      await waitFor(() => {
        expect(screen.getByText('Conversion failed')).toBeInTheDocument()
      })
    })

    test('server error without error details shows generic message', async () => {
      simulateUploadSuccess()
      convertFileRequest.mockRejectedValue({})

      renderWithContext()

      const input = document.getElementById('fileInput')
      const file = createMockFile('bad.docx')

      await act(async () => {
        fireEvent.change(input, {target: {files: [file]}})
        jest.advanceTimersByTime(200)
      })

      await waitFor(() => {
        expect(screen.getByText('Server error, try again.')).toBeInTheDocument()
      })
    })

    test('empty file shows error message', async () => {
      fileUpload.mockImplementation((file, onProgress, onSuccess) => {
        onProgress(100)
        const response = [
          {
            name: file.name,
            size: 0,
            type: 'docx',
            ext: 'docx',
            error: 'minFileSize',
          },
        ]
        onSuccess(JSON.stringify(response))
      })

      renderWithContext()

      const input = document.getElementById('fileInput')
      const file = createMockFile('empty.docx', 0)

      await act(async () => {
        fireEvent.change(input, {target: {files: [file]}})
      })

      expect(screen.getByText('empty.docx')).toBeInTheDocument()
    })
  })

  describe('TMX file handling', () => {
    test('TMX file triggers createKeyFromTMXFile action', async () => {
      simulateUploadSuccess({size: 1024})
      convertFileRequest.mockResolvedValue({
        data: {data: {}},
        warnings: null,
      })

      renderWithContext()

      const input = document.getElementById('fileInput')
      const file = createMockFile('memory.tmx', 1024, 'application/xml')

      await act(async () => {
        fireEvent.change(input, {target: {files: [file]}})
        jest.advanceTimersByTime(200)
      })

      await waitFor(() => {
        expect(CreateProjectActions.createKeyFromTMXFile).toHaveBeenCalledWith({
          filename: 'memory.tmx',
        })
      })
    })
  })

  describe('Delete functionality', () => {
    test('delete button removes file from list', async () => {
      simulateUploadSuccess()
      renderWithContext()

      const input = document.getElementById('fileInput')
      const file = createMockFile('removeme.docx')

      await act(async () => {
        fireEvent.change(input, {target: {files: [file]}})
        jest.advanceTimersByTime(200)
      })

      expect(screen.getByText('removeme.docx')).toBeInTheDocument()

      const deleteButtons = screen.getAllByRole('button', {
        name: /remove file/i,
      })

      await act(async () => {
        fireEvent.click(deleteButtons[0])
      })

      expect(screen.queryByText('removeme.docx')).not.toBeInTheDocument()
    })

    test('delete calls fileUploadDelete API', async () => {
      simulateUploadSuccess()
      renderWithContext()

      const input = document.getElementById('fileInput')
      const file = createMockFile('deleteme.docx')

      await act(async () => {
        fireEvent.change(input, {target: {files: [file]}})
        jest.advanceTimersByTime(200)
      })

      const deleteButtons = screen.getAllByRole('button', {
        name: /remove file/i,
      })

      await act(async () => {
        fireEvent.click(deleteButtons[0])
      })

      expect(fileUploadDelete).toHaveBeenCalledWith(
        expect.objectContaining({
          file: 'deleteme.docx',
          source: 'en-US',
        }),
      )
    })

    test('clear all button removes all files', async () => {
      simulateUploadSuccess()
      renderWithContext()

      const input = document.getElementById('fileInput')
      const file1 = createMockFile('file1.docx')
      const file2 = createMockFile('file2.docx')

      await act(async () => {
        fireEvent.change(input, {target: {files: [file1, file2]}})
        jest.advanceTimersByTime(200)
      })

      expect(screen.getByText('file1.docx')).toBeInTheDocument()
      expect(screen.getByText('file2.docx')).toBeInTheDocument()

      const clearAllButton = screen.getByRole('button', {name: /clear all/i})

      await act(async () => {
        fireEvent.click(clearAllButton)
      })

      expect(screen.queryByText('file1.docx')).not.toBeInTheDocument()
      expect(screen.queryByText('file2.docx')).not.toBeInTheDocument()
    })
  })

  describe('Multiple files', () => {
    test('uploading multiple files shows all in the list', async () => {
      simulateUploadSuccess()
      renderWithContext()

      const input = document.getElementById('fileInput')
      const file1 = createMockFile('doc1.docx')
      const file2 = createMockFile('doc2.docx')

      await act(async () => {
        fireEvent.change(input, {target: {files: [file1, file2]}})
        jest.advanceTimersByTime(200)
      })

      expect(screen.getByText('doc1.docx')).toBeInTheDocument()
      expect(screen.getByText('doc2.docx')).toBeInTheDocument()
    })

    test('duplicate file names get renamed', async () => {
      simulateUploadSuccess()
      renderWithContext()

      const input = document.getElementById('fileInput')
      const file1 = createMockFile('test.docx')

      await act(async () => {
        fireEvent.change(input, {target: {files: [file1]}})
        jest.advanceTimersByTime(200)
      })

      expect(screen.getByText('test.docx')).toBeInTheDocument()

      const file2 = createMockFile('test.docx')

      await act(async () => {
        fireEvent.change(input, {target: {files: [file2]}})
        jest.advanceTimersByTime(200)
      })

      expect(screen.getByText('test_(1).docx')).toBeInTheDocument()
    })
  })

  describe('Drag and drop', () => {
    test('drag enter sets dragging state', async () => {
      renderWithContext()

      const container = document.querySelector('.upload-files-container')

      await act(async () => {
        fireEvent.dragEnter(container, {
          dataTransfer: {files: []},
        })
      })

      expect(container).toHaveClass('dragging')
      expect(screen.getByText('Drop it here')).toBeInTheDocument()
    })

    test('drag leave removes dragging state', async () => {
      renderWithContext()

      const container = document.querySelector('.upload-files-container')

      await act(async () => {
        fireEvent.dragEnter(container, {
          dataTransfer: {files: []},
        })
      })

      expect(container).toHaveClass('dragging')

      await act(async () => {
        fireEvent.dragLeave(container, {
          dataTransfer: {files: []},
        })
      })

      expect(container).not.toHaveClass('dragging')
    })

    test('dropping files processes them', async () => {
      simulateUploadSuccess()
      renderWithContext()

      const container = document.querySelector('.upload-files-container')
      const file = createMockFile('dropped.docx')

      await act(async () => {
        fireEvent.drop(container, {
          dataTransfer: {files: [file]},
        })
        jest.advanceTimersByTime(200)
      })

      expect(screen.getByText('dropped.docx')).toBeInTheDocument()
      expect(fileUpload).toHaveBeenCalled()
    })

    test('dropping a folder shows error', async () => {
      renderWithContext()

      const container = document.querySelector('.upload-files-container')
      // A folder has empty type and size divisible by 4096
      const folder = new File([], 'myfolder', {type: ''})
      Object.defineProperty(folder, 'size', {value: 4096})

      await act(async () => {
        fireEvent.drop(container, {
          dataTransfer: {files: [folder]},
        })
      })

      expect(CreateProjectActions.showError).toHaveBeenCalledWith(
        'Uploading unzipped folders is not allowed. Please upload individual files, or a zipped folder.',
      )
    })
  })

  describe('Action buttons', () => {
    test('shows Add files, Clear all buttons when files exist', async () => {
      simulateUploadSuccess()
      renderWithContext()

      const input = document.getElementById('fileInput')
      const file = createMockFile('test.docx')

      await act(async () => {
        fireEvent.change(input, {target: {files: [file]}})
        jest.advanceTimersByTime(200)
      })

      expect(
        screen.getByRole('button', {name: /add files/i}),
      ).toBeInTheDocument()
      expect(
        screen.getByRole('button', {name: /clear all/i}),
      ).toBeInTheDocument()
    })

    test('shows Clear all failed button when there are errored files', async () => {
      simulateUploadError('Upload failed')
      renderWithContext()

      const input = document.getElementById('fileInput')
      const file = createMockFile('bad.docx')

      await act(async () => {
        fireEvent.change(input, {target: {files: [file]}})
        jest.advanceTimersByTime(200)
      })

      expect(
        screen.getByRole('button', {name: /clear all failed/i}),
      ).toBeInTheDocument()
    })

    test('container has add-files class when files exist', async () => {
      simulateUploadSuccess()
      renderWithContext()

      const input = document.getElementById('fileInput')
      const file = createMockFile('test.docx')

      await act(async () => {
        fireEvent.change(input, {target: {files: [file]}})
        jest.advanceTimersByTime(200)
      })

      const container = document.querySelector('.upload-files-container')
      expect(container).toHaveClass('add-files')
    })
  })

  describe('ZIP file handling', () => {
    test('zip conversion shows extracted files', async () => {
      simulateUploadSuccess()
      convertFileRequest.mockResolvedValue({
        data: {
          data: {
            zipFiles: [
              {name: 'archive.zip/doc1.docx', size: 1024},
              {name: 'archive.zip/doc2.docx', size: 2048},
            ],
          },
        },
        warnings: null,
      })

      renderWithContext()

      const input = document.getElementById('fileInput')
      const file = createMockFile('archive.zip', 4096, 'application/zip')

      await act(async () => {
        fireEvent.change(input, {target: {files: [file]}})
        jest.advanceTimersByTime(200)
      })

      await waitFor(() => {
        expect(screen.getByText('archive.zip')).toBeInTheDocument()
        expect(screen.getByText('archive.zip/doc1.docx')).toBeInTheDocument()
        expect(screen.getByText('archive.zip/doc2.docx')).toBeInTheDocument()
      })
    })
  })

  describe('Max files limit', () => {
    test('shows error when file limit is exceeded', async () => {
      const originalMax = config.maxNumberFiles
      config.maxNumberFiles = 2
      simulateUploadSuccess()
      renderWithContext()

      const input = document.getElementById('fileInput')
      const files = [
        createMockFile('file1.docx'),
        createMockFile('file2.docx'),
        createMockFile('file3.docx'),
      ]

      await act(async () => {
        fireEvent.change(input, {target: {files}})
        jest.advanceTimersByTime(200)
      })

      expect(CreateProjectActions.showError).toHaveBeenCalled()
      config.maxNumberFiles = originalMax
    })
  })

  describe('enableAnalyzeButton', () => {
    test('enables analyze button after successful upload + conversion', async () => {
      simulateUploadSuccess({size: 2048})
      convertFileRequest.mockResolvedValue({
        data: {data: {}},
        warnings: null,
      })

      renderWithContext()

      const input = document.getElementById('fileInput')
      const file = createMockFile('test.docx')

      await act(async () => {
        fireEvent.change(input, {target: {files: [file]}})
        jest.advanceTimersByTime(500)
      })

      await waitFor(() => {
        expect(CreateProjectActions.enableAnalyzeButton).toHaveBeenCalledWith(
          true,
        )
      })
    })
  })
})
