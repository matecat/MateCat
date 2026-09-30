import {renderHook, act, waitFor} from '@testing-library/react'
import {useFileUploadManager} from './useFileUploadManager'
import {fileUpload} from '../../../api/fileUpload'
import {convertFileRequest} from '../../../api/convertFileRequest'
import {fileUploadDelete} from '../../../api/fileUploadDelete'
import CreateProjectActions from '../../../actions/CreateProjectActions'
import CommonUtils from '../../../utils/commonUtils'

jest.mock('../../../api/fileUpload', () => ({fileUpload: jest.fn()}))
jest.mock('../../../api/convertFileRequest', () => ({
  convertFileRequest: jest.fn(),
}))
jest.mock('../../../api/fileUploadDelete', () => ({
  fileUploadDelete: jest.fn(() => Promise.resolve({})),
}))
jest.mock('../../../actions/CreateProjectActions', () => ({
  enableAnalyzeButton: jest.fn(),
  hideErrors: jest.fn(),
  showError: jest.fn(),
  createKeyFromTMXFile: jest.fn(),
}))
jest.mock('../../../utils/commonUtils', () => ({
  dispatchCustomEvent: jest.fn(),
}))

global.config = {
  ...global.config,
  maxFileSize: 200 * 1024 * 1024,
  maxTMXFileSize: 100 * 1024 * 1024,
  maxNumberFiles: 10,
}

const baseProps = () => ({
  sourceLang: {code: 'en-US'},
  targetLangs: [{id: 'it-IT'}],
  segmentationRule: 'standard',
  extractionParameterTemplateId: 1,
  currentFiltersExtractionParameters: undefined,
  icuEnabled: false,
  setUploadedFilesNames: jest.fn(),
  tmKeys: [{id: 1, isTmFromFile: true, name: 'old.tmx'}],
  setTmKeys: jest.fn(),
  modifyingCurrentTemplate: jest.fn(),
})

const uploadFile = async (result, props, file) => {
  fileUpload.mockImplementation((f, onProgress, onSuccess) => {
    onSuccess(
      JSON.stringify([{name: f.name, size: 100, type: 'docx', error: null}]),
    )
  })
  convertFileRequest.mockResolvedValue({data: {data: {}}, warnings: null})
  await act(async () => {
    result.current.handleFiles([file])
  })
}

describe('useFileUploadManager', () => {
  beforeEach(() => jest.clearAllMocks())

  afterEach(async () => {
    jest.clearAllTimers()
  })

  test('deleteFile removes the file and calls fileUploadDelete', async () => {
    const props = baseProps()
    const {result, rerender, unmount} = renderHook(
      (p) => useFileUploadManager(p),
      {initialProps: props},
    )
    const file = new File(['x'], 'doc.docx', {type: 'text/plain'})
    await uploadFile(result, props, file)
    rerender(props)
    expect(result.current.files).toHaveLength(1)
    await act(async () => {
      result.current.deleteFile(result.current.files[0])
    })
    expect(fileUploadDelete).toHaveBeenCalledWith({
      file: 'doc.docx',
      source: 'en-US',
      segmentationRule: 'standard',
      filtersTemplateId: 1,
    })
    expect(CreateProjectActions.hideErrors).toHaveBeenCalled()
    await act(async () => {
      result.current.deleteAllFiles()
    })
    unmount()
  })

  test('deleting the last non-tmx file works without touching tmKeys', async () => {
    const props = baseProps()
    const {result, unmount} = renderHook((p) => useFileUploadManager(p), {
      initialProps: props,
    })
    const file = new File(['x'], 'doc.docx', {type: 'text/plain'})
    await uploadFile(result, props, file)

    await act(async () => {
      result.current.deleteFile(result.current.files[0])
    })
    expect(props.setTmKeys).not.toHaveBeenCalled()
    await act(async () => {
      result.current.deleteAllFiles()
    })
    unmount()
  })

  test('deleting a tmx file removes the isTmFromFile tm key when it is the only tmx file', async () => {
    const props = baseProps()
    const {result, unmount} = renderHook((p) => useFileUploadManager(p), {
      initialProps: props,
    })
    const file = new File(['x'], 'terms.tmx', {type: 'text/plain'})
    await uploadFile(result, props, file)

    await act(async () => {
      result.current.deleteFile(result.current.files[0])
    })
    expect(props.setTmKeys).toHaveBeenCalled()
    const updater = props.setTmKeys.mock.calls[0][0]
    const updated = updater([{id: 1, isTmFromFile: true, name: 'terms.tmx'}])
    expect(updated).toHaveLength(0)
    expect(props.modifyingCurrentTemplate).toHaveBeenCalled()
    const templateUpdater = props.modifyingCurrentTemplate.mock.calls[0][0]
    const updatedTemplate = templateUpdater({
      tm: [{isTmFromFile: true, name: 'terms.tmx'}],
    })
    expect(updatedTemplate.tm).toHaveLength(0)
    await act(async () => {
      result.current.deleteAllFiles()
    })
    unmount()
  })

  test('deleteAllFiles clears files, uploaded names and calls fileUploadDelete per file', async () => {
    const props = baseProps()
    const {result, unmount} = renderHook((p) => useFileUploadManager(p), {
      initialProps: props,
    })
    const file = new File(['x'], 'doc.docx', {type: 'text/plain'})
    await uploadFile(result, props, file)

    await act(async () => {
      result.current.deleteAllFiles()
    })
    expect(result.current.files).toHaveLength(0)
    expect(props.setUploadedFilesNames).toHaveBeenCalledWith([])
    expect(fileUploadDelete).toHaveBeenCalled()
    unmount()
  })

  test('handleFiles marks files beyond maxNumberFiles with an error', async () => {
    global.config = {...global.config, maxNumberFiles: 1}
    const props = baseProps()
    const {result, unmount} = renderHook((p) => useFileUploadManager(p), {
      initialProps: props,
    })
    fileUpload.mockImplementation(() => {})
    const file1 = new File(['x'], 'a.docx')
    const file2 = new File(['x'], 'b.docx')
    await act(async () => {
      result.current.handleFiles([file1, file2])
    })
    expect(result.current.files[1].error).toMatch(/Too many files uploaded/)
    global.config = {...global.config, maxNumberFiles: 10}
    await act(async () => {
      result.current.deleteAllFiles()
    })
    unmount()
  })

  test('handleFiles renames a file that duplicates an existing name', async () => {
    const props = baseProps()
    const {result, unmount} = renderHook((p) => useFileUploadManager(p), {
      initialProps: props,
    })
    const file1 = new File(['x'], 'a.docx')
    await uploadFile(result, props, file1)

    const file2 = new File(['x'], 'a.docx')
    fileUpload.mockImplementation((f, onProgress, onSuccess) => {
      onSuccess(JSON.stringify([{name: f.name, size: 100, error: null}]))
    })
    await act(async () => {
      result.current.handleFiles([file2])
    })
    expect(
      result.current.files.some((f) => f.name === 'a_(1).docx'),
    ).toBe(true)
    await act(async () => {
      result.current.deleteAllFiles()
    })
    unmount()
  })

  test('handleFiles sets an error on the file when fileUpload reports an onError', async () => {
    const props = baseProps()
    const {result, unmount} = renderHook((p) => useFileUploadManager(p), {
      initialProps: props,
    })
    fileUpload.mockImplementation((f, onProgress, onSuccess, onError) => {
      onError('boom')
    })
    const file = new File(['x'], 'a.docx')
    await act(async () => {
      result.current.handleFiles([file])
    })
    expect(result.current.files[0].error).toBe('boom')
    unmount()
  })

  test('handleFiles sets a server error message when convertFileRequest rejects', async () => {
    const props = baseProps()
    const {result, unmount} = renderHook((p) => useFileUploadManager(p), {
      initialProps: props,
    })
    fileUpload.mockImplementation((f, onProgress, onSuccess) => {
      onSuccess(JSON.stringify([{name: f.name, size: 100, error: null}]))
    })
    convertFileRequest.mockRejectedValue({
      data: null,
      errors: [{message: 'Conversion failed'}],
    })
    const file = new File(['x'], 'a.docx')
    await act(async () => {
      result.current.handleFiles([file])
    })
    expect(result.current.files[0].error).toBe('Conversion failed')
    unmount()
  })

  test('restartConversions re-runs convertFileRequest for already-uploaded files when sourceLang changes', async () => {
    const props = baseProps()
    const {result, rerender, unmount} = renderHook(
      (p) => useFileUploadManager(p),
      {initialProps: props},
    )
    const file = new File(['x'], 'a.docx')
    await uploadFile(result, props, file)

    convertFileRequest.mockClear()
    convertFileRequest.mockResolvedValue({data: {data: {}}, warnings: null})
    await act(async () => {
      rerender({...props, sourceLang: {code: 'fr-FR'}})
    })
    expect(convertFileRequest).toHaveBeenCalled()
    expect(CreateProjectActions.enableAnalyzeButton).toHaveBeenCalledWith(
      false,
    )
    await act(async () => {
      result.current.deleteAllFiles()
    })
    unmount()
  })

  test('dispatches a custom uploaded-file event with the file extension', async () => {
    const props = baseProps()
    const {result, unmount} = renderHook((p) => useFileUploadManager(p), {
      initialProps: props,
    })
    fileUpload.mockImplementation(() => {})
    const file = new File(['x'], 'a.docx')
    await act(async () => {
      result.current.handleFiles([file])
    })
    expect(CommonUtils.dispatchCustomEvent).toHaveBeenCalledWith(
      'uploaded-file',
      {extension: 'docx'},
    )
    unmount()
  })

  test('handleConvertSuccess inserts extracted zipFiles entries and deleteFile removes them', async () => {
    const props = baseProps()
    const {result, unmount} = renderHook((p) => useFileUploadManager(p), {
      initialProps: props,
    })
    fileUpload.mockImplementation((f, onProgress, onSuccess) => {
      onSuccess(JSON.stringify([{name: f.name, size: 100, error: null}]))
    })
    convertFileRequest.mockResolvedValue({
      data: {data: {zipFiles: [{name: 'archive.zip/inner.docx', size: 50}]}},
      warnings: null,
    })
    const file = new File(['x'], 'archive.zip')
    await act(async () => {
      result.current.handleFiles([file])
    })
    expect(
      result.current.files.some((f) => f.name === 'archive.zip/inner.docx'),
    ).toBe(true)

    // setUploadedFilesNames is a prop (mocked with jest.fn()), so passing it a
    // functional updater only records the call — React never invokes the
    // callback itself. Replay the recorded updaters manually to exercise
    // their bodies (they mirror the equivalent setFiles logic above).
    const uploadedNamesUpdaters = props.setUploadedFilesNames.mock.calls.map(
      (call) => call[0],
    )
    expect(
      uploadedNamesUpdaters.some(
        (updater) => updater(['archive.zip']).includes('archive.zip/inner.docx'),
      ),
    ).toBe(true)

    props.setUploadedFilesNames.mockClear()
    await act(async () => {
      result.current.deleteFile(
        result.current.files.find((f) => f.name === 'archive.zip'),
      )
    })
    expect(
      result.current.files.some((f) => f.name === 'archive.zip/inner.docx'),
    ).toBe(false)
    const deleteZipUpdater = props.setUploadedFilesNames.mock.calls
      .map((call) => call[0])
      .find(
        (updater) =>
          !updater(['archive.zip', 'archive.zip/inner.docx']).includes(
            'archive.zip/inner.docx',
          ),
      )
    expect(deleteZipUpdater).toBeDefined()
    await act(async () => {
      result.current.deleteAllFiles()
    })
    unmount()
  })

  test('handleConvertError decorates zipFiles entries with their per-file error message', async () => {
    const props = baseProps()
    const {result, unmount} = renderHook((p) => useFileUploadManager(p), {
      initialProps: props,
    })
    fileUpload.mockImplementation((f, onProgress, onSuccess) => {
      onSuccess(JSON.stringify([{name: f.name, size: 100, error: null}]))
    })
    convertFileRequest.mockRejectedValue({
      data: {data: {zipFiles: [{name: 'bad.zip/broken.docx', size: 10}]}},
      errors: [{name: 'bad.zip/broken.docx', message: 'Corrupted entry'}],
    })
    const file = new File(['x'], 'bad.zip')
    await act(async () => {
      result.current.handleFiles([file])
    })
    const zipEntry = result.current.files.find(
      (f) => f.name === 'bad.zip/broken.docx',
    )
    expect(zipEntry.error).toBe('Corrupted entry')
    await act(async () => {
      result.current.deleteAllFiles()
    })
    unmount()
  })

  test('deleting one of several tmx files re-points the isTmFromFile key to the remaining tmx file', async () => {
    const props = {
      ...baseProps(),
      tmKeys: [{id: 1, isTmFromFile: true, name: 'a.tmx'}],
    }
    const {result, unmount} = renderHook((p) => useFileUploadManager(p), {
      initialProps: props,
    })
    const fileA = new File(['x'], 'a.tmx')
    await uploadFile(result, props, fileA)
    fileUpload.mockImplementation((f, onProgress, onSuccess) => {
      onSuccess(JSON.stringify([{name: f.name, size: 100, error: null}]))
    })
    const fileB = new File(['x'], 'b.tmx')
    await act(async () => {
      result.current.handleFiles([fileB])
    })
    expect(result.current.files).toHaveLength(2)

    await act(async () => {
      result.current.deleteFile(
        result.current.files.find((f) => f.name === 'a.tmx'),
      )
    })
    expect(props.setTmKeys).toHaveBeenCalled()
    const tmUpdater = props.setTmKeys.mock.calls[0][0]
    const updatedTm = tmUpdater([
      {id: 1, isTmFromFile: true, name: 'a.tmx'},
    ])
    expect(updatedTm[0].name).toBe('b.tmx')
    expect(props.modifyingCurrentTemplate).toHaveBeenCalled()
    const templateUpdater = props.modifyingCurrentTemplate.mock.calls[0][0]
    const updatedTemplate = templateUpdater({
      tm: [{isTmFromFile: true, name: 'a.tmx'}],
    })
    expect(updatedTemplate.tm[0].name).toBe('b.tmx')
    await act(async () => {
      result.current.deleteAllFiles()
    })
    unmount()
  })

  test('deleteAllFiles clears the isTmFromFile tm key when a tmx file was uploaded', async () => {
    const props = baseProps()
    const {result, unmount} = renderHook((p) => useFileUploadManager(p), {
      initialProps: props,
    })
    const file = new File(['x'], 'terms.tmx')
    await uploadFile(result, props, file)

    await act(async () => {
      result.current.deleteAllFiles()
    })
    expect(props.setTmKeys).toHaveBeenCalled()
    const updater = props.setTmKeys.mock.calls[0][0]
    expect(
      updater([{id: 1, isTmFromFile: true, name: 'terms.tmx'}]),
    ).toHaveLength(0)
    expect(props.modifyingCurrentTemplate).toHaveBeenCalled()
    const templateUpdater = props.modifyingCurrentTemplate.mock.calls[0][0]
    const updatedTemplate = templateUpdater({
      tm: [{isTmFromFile: true, name: 'terms.tmx'}],
    })
    expect(updatedTemplate.tm).toHaveLength(0)
    unmount()
  })

  test('restartConversions re-marks zipFiles entries as converted once convertFileRequest resolves', async () => {
    const props = baseProps()
    const {result, rerender, unmount} = renderHook(
      (p) => useFileUploadManager(p),
      {initialProps: props},
    )
    fileUpload.mockImplementation((f, onProgress, onSuccess) => {
      onSuccess(JSON.stringify([{name: f.name, size: 100, error: null}]))
    })
    convertFileRequest.mockResolvedValue({
      data: {data: {zipFiles: [{name: 'archive.zip/inner.docx', size: 50}]}},
      warnings: null,
    })
    const file = new File(['x'], 'archive.zip')
    await act(async () => {
      result.current.handleFiles([file])
    })
    expect(
      result.current.files.find((f) => f.name === 'archive.zip/inner.docx')
        .converted,
    ).toBe(true)

    // restartConversions resets every file's `converted` flag to false first;
    // for the zipFolder entry, only the zipFiles branch inside the resolved
    // convertFileRequest handler flips it back to true (the outer forEach
    // skips zipFolder entries entirely), so this genuinely exercises that
    // branch rather than passing incidentally.
    convertFileRequest.mockClear()
    convertFileRequest.mockResolvedValue({
      data: {data: {zipFiles: [{name: 'archive.zip/inner.docx', size: 50}]}},
      warnings: null,
    })
    await act(async () => {
      rerender({...props, sourceLang: {code: 'fr-FR'}})
    })
    await waitFor(() =>
      expect(
        result.current.files.find((f) => f.name === 'archive.zip/inner.docx')
          .converted,
      ).toBe(true),
    )
    expect(CreateProjectActions.enableAnalyzeButton).toHaveBeenCalledWith(
      true,
    )
    await act(async () => {
      result.current.deleteAllFiles()
    })
    unmount()
  })

  test('restartConversions sets a file error when convertFileRequest rejects', async () => {
    const props = baseProps()
    const {result, rerender, unmount} = renderHook(
      (p) => useFileUploadManager(p),
      {initialProps: props},
    )
    const file = new File(['x'], 'a.docx')
    await uploadFile(result, props, file)

    convertFileRequest.mockClear()
    convertFileRequest.mockRejectedValue([{message: 'Restart failed'}])
    await act(async () => {
      rerender({...props, sourceLang: {code: 'fr-FR'}})
    })
    await waitFor(() =>
      expect(result.current.files[0].error).toBe('Restart failed'),
    )
    await act(async () => {
      result.current.deleteAllFiles()
    })
    unmount()
  })

  test('changing currentFiltersExtractionParameters restarts conversions', async () => {
    const props = baseProps()
    const {result, rerender, unmount} = renderHook(
      (p) => useFileUploadManager(p),
      {initialProps: props},
    )
    const file = new File(['x'], 'a.docx')
    await uploadFile(result, props, file)

    convertFileRequest.mockClear()
    convertFileRequest.mockResolvedValue({data: {data: {}}, warnings: null})
    await act(async () => {
      rerender({...props, currentFiltersExtractionParameters: {foo: 'bar'}})
    })
    expect(convertFileRequest).toHaveBeenCalled()
    await act(async () => {
      result.current.deleteAllFiles()
    })
    unmount()
  })

  test('handleFiles shows a limit-reached error when the file count exactly equals maxNumberFiles', async () => {
    global.config = {...global.config, maxNumberFiles: 1}
    const props = baseProps()
    const {result, unmount} = renderHook((p) => useFileUploadManager(p), {
      initialProps: props,
    })
    fileUpload.mockImplementation(() => {})
    const file = new File(['x'], 'a.docx')
    await act(async () => {
      result.current.handleFiles([file])
    })
    expect(CreateProjectActions.showError).toHaveBeenCalledWith(
      expect.stringContaining('No more files can be loaded'),
    )
    global.config = {...global.config, maxNumberFiles: 10}
    await act(async () => {
      result.current.deleteAllFiles()
    })
    unmount()
  })

  // Note: getFileErrorMessage's `ext === EXTENSIONS.tmx` (tmx-specific oversize
  // message) branch is unreachable from this call site: the fileUpload response
  // payload built in onSuccess never carries an `ext` field (only name/size/type/
  // error), so `ext` is always undefined here regardless of the uploaded file's
  // extension. Only the non-tmx oversize branch below is exercised in practice.
  test('getFileErrorMessage flags an oversized file', async () => {
    const props = baseProps()
    const {result, unmount} = renderHook((p) => useFileUploadManager(p), {
      initialProps: props,
    })
    fileUpload.mockImplementation((f, onProgress, onSuccess) => {
      onSuccess(
        JSON.stringify([{name: f.name, size: 999 * 1024 * 1024, error: null}]),
      )
    })
    const file = new File(['x'], 'huge.docx')
    await act(async () => {
      result.current.handleFiles([file])
    })
    expect(result.current.files[0].error).toMatch(
      /uploaded file exceed the file size limit/,
    )
    unmount()
  })
})
