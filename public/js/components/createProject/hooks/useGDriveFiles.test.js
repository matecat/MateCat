import {renderHook, act} from '@testing-library/react'
import React from 'react'
import {useGDriveFiles} from './useGDriveFiles'
import {openGDriveFiles} from '../../../api/openGDriveFiles'
import {getGoogleDriveUploadedFiles} from '../../../api/getGoogleDriveUploadedFiles'
import {deleteGDriveUploadedFile} from '../../../api/deleteGdriveUploadedFile'
import {changeGDriveSourceLang} from '../../../api/changeGDriveSourceLang'
import CreateProjectActions from '../../../actions/CreateProjectActions'

jest.mock('../../../api/openGDriveFiles', () => ({openGDriveFiles: jest.fn()}))
jest.mock('../../../api/getGoogleDriveUploadedFiles', () => ({
  getGoogleDriveUploadedFiles: jest.fn(),
}))
jest.mock('../../../api/deleteGdriveUploadedFile', () => ({
  deleteGDriveUploadedFile: jest.fn(),
}))
jest.mock('../../../api/changeGDriveSourceLang', () => ({
  changeGDriveSourceLang: jest.fn(),
}))
jest.mock('../../../actions/CreateProjectActions', () => ({
  enableAnalyzeButton: jest.fn(),
  hideErrors: jest.fn(),
  showError: jest.fn(),
}))

global.config = {...global.config, maxNumberFiles: 10}

const baseProps = () => ({
  sourceLang: {code: 'en-US'},
  targetLangs: [{id: 'it-IT'}],
  segmentationRule: 'standard',
  extractionParameterTemplateId: 1,
  currentFiltersExtractionParameters: undefined,
  setUploadedFilesNames: jest.fn(),
  setOpenGDrive: jest.fn(),
})

// uploads a single file through the real picker success flow so `files` is
// non-empty before exercising branches that depend on files.length > 0
const uploadOneFile = async (result) => {
  openGDriveFiles.mockResolvedValueOnce({success: true})
  getGoogleDriveUploadedFiles.mockResolvedValueOnce({
    files: [
      {fileName: 'a.docx', fileExtension: 'docx', fileSize: 100, fileId: '1'},
    ],
  })
  await act(async () => {
    result.current.pickerCallback({action: 'picked', docs: [{id: 'doc-1'}]})
  })
}

describe('useGDriveFiles', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    global.google = {
      picker: {
        Response: {ACTION: 'action', DOCUMENTS: 'docs'},
        Action: {CANCEL: 'cancel', PICKED: 'picked'},
      },
    }
    jest.spyOn(console, 'error').mockImplementation(() => {})
  })

  afterEach(() => {
    console.error.mockRestore()
  })

  test('pickerCallback CANCEL closes the panel when there are no files', () => {
    const props = baseProps()
    const {result} = renderHook((p) => useGDriveFiles(p), {initialProps: props})
    props.setOpenGDrive.mockClear()
    act(() => {
      result.current.pickerCallback({action: 'cancel'})
    })
    expect(props.setOpenGDrive).toHaveBeenCalledWith(false)
  })

  test('pickerCallback CANCEL leaves the panel open when files already exist', async () => {
    const props = baseProps()
    const {result} = renderHook((p) => useGDriveFiles(p), {initialProps: props})
    await uploadOneFile(result)
    props.setOpenGDrive.mockClear()
    act(() => {
      result.current.pickerCallback({action: 'cancel'})
    })
    expect(props.setOpenGDrive).not.toHaveBeenCalled()
  })

  test('pickerCallback PICKED calls openGDriveFiles and lists files on success', async () => {
    openGDriveFiles.mockResolvedValue({success: true})
    getGoogleDriveUploadedFiles.mockResolvedValue({
      files: [
        {fileName: 'a.docx', fileExtension: 'docx', fileSize: 100, fileId: '1'},
      ],
    })
    const props = baseProps()
    const {result} = renderHook((p) => useGDriveFiles(p), {initialProps: props})
    await act(async () => {
      result.current.pickerCallback({action: 'picked', docs: [{id: 'doc-1'}]})
    })
    expect(openGDriveFiles).toHaveBeenCalledWith({
      stateJson: JSON.stringify({exportIds: ['doc-1'], action: 'open'}),
      sourceLang: 'en-US',
      targetLang: 'it-IT',
      segmentation_rule: 'standard',
      filters_extraction_parameters_template_id: 1,
    })
    expect(result.current.files).toHaveLength(1)
    expect(CreateProjectActions.enableAnalyzeButton).toHaveBeenCalledWith(true)
    expect(CreateProjectActions.hideErrors).toHaveBeenCalled()
  })

  test('pickerCallback builds a JSON filters template payload when currentFiltersExtractionParameters is an object', async () => {
    openGDriveFiles.mockResolvedValue({success: true})
    getGoogleDriveUploadedFiles.mockResolvedValue({files: []})
    const props = {
      ...baseProps(),
      currentFiltersExtractionParameters: {foo: 'bar'},
    }
    const {result} = renderHook((p) => useGDriveFiles(p), {initialProps: props})
    await act(async () => {
      result.current.pickerCallback({action: 'picked', docs: [{id: 'doc-1'}]})
    })
    const call = openGDriveFiles.mock.calls[0][0]
    expect(call.filters_extraction_parameters_template).toBe(
      JSON.stringify({foo: 'bar'}),
    )
    expect(call.filters_extraction_parameters_template_id).toBeUndefined()
  })

  test('pickerCallback shows the server-provided message for an InvalidArgumentException', async () => {
    openGDriveFiles.mockResolvedValue({
      success: false,
      error_class: 'InvalidArgumentException',
      error_msg: 'Bad file',
    })
    const props = baseProps()
    const {result} = renderHook((p) => useGDriveFiles(p), {initialProps: props})
    props.setOpenGDrive.mockClear()
    await act(async () => {
      result.current.pickerCallback({action: 'picked', docs: [{id: 'doc-1'}]})
    })
    expect(CreateProjectActions.showError).toHaveBeenCalledWith('Bad file')
    expect(props.setOpenGDrive).toHaveBeenCalledWith(false)
  })

  test('pickerCallback prefixes the message for a Google Service Exception', async () => {
    openGDriveFiles.mockResolvedValue({
      success: false,
      error_class: 'Google\\Service\\Exception',
      error_msg: 'Quota exceeded',
    })
    const props = baseProps()
    const {result} = renderHook((p) => useGDriveFiles(p), {initialProps: props})
    await act(async () => {
      result.current.pickerCallback({action: 'picked', docs: [{id: 'doc-1'}]})
    })
    expect(CreateProjectActions.showError).toHaveBeenCalledWith(
      'There was an error retrieving the file from Google Drive: Quota exceeded',
    )
  })

  test('pickerCallback shows a guide link when the server responds with error_code 404', async () => {
    openGDriveFiles.mockResolvedValue({success: false, error_code: 404})
    const props = baseProps()
    const {result} = renderHook((p) => useGDriveFiles(p), {initialProps: props})
    await act(async () => {
      result.current.pickerCallback({action: 'picked', docs: [{id: 'doc-1'}]})
    })
    expect(CreateProjectActions.showError).toHaveBeenCalled()
    const message = CreateProjectActions.showError.mock.calls[0][0]
    expect(React.isValidElement(message)).toBe(true)
    const link = React.Children.toArray(message.props.children).find(
      (child) => child?.type === 'a',
    )
    expect(link.props.href).toBe(
      'https://guides.matecat.com/google-drive-files-upload-issues',
    )
  })

  test('pickerCallback falls back to the generic message for an unrecognized failure', async () => {
    openGDriveFiles.mockResolvedValue({
      success: false,
      error_class: 'SomeOtherException',
      error_msg: 'x',
    })
    const props = baseProps()
    const {result} = renderHook((p) => useGDriveFiles(p), {initialProps: props})
    await act(async () => {
      result.current.pickerCallback({action: 'picked', docs: [{id: 'doc-1'}]})
    })
    expect(CreateProjectActions.showError).toHaveBeenCalledWith(
      'There was an error retrieving the file from Google Drive. Try again and if the error persists contact the Support.',
    )
  })

  test('pickerCallback shows a generic error message and closes the panel when openGDriveFiles rejects', async () => {
    openGDriveFiles.mockRejectedValue(new Error('network'))
    const props = baseProps()
    const {result} = renderHook((p) => useGDriveFiles(p), {initialProps: props})
    props.setOpenGDrive.mockClear()
    await act(async () => {
      result.current.pickerCallback({action: 'picked', docs: [{id: 'doc-1'}]})
    })
    expect(CreateProjectActions.showError).toHaveBeenCalled()
    expect(
      React.isValidElement(CreateProjectActions.showError.mock.calls[0][0]),
    ).toBe(true)
    expect(props.setOpenGDrive).toHaveBeenCalledWith(false)
  })

  test('shows the list-request error only when the server responds with code 400', async () => {
    deleteGDriveUploadedFile.mockResolvedValue({success: true})
    getGoogleDriveUploadedFiles.mockRejectedValue({code: 400, msg: 'Oops'})
    const props = baseProps()
    const {result} = renderHook((p) => useGDriveFiles(p), {initialProps: props})
    await act(async () => {
      result.current.deleteFile({id: '1', name: 'a.docx'})
    })
    expect(CreateProjectActions.showError).toHaveBeenCalled()
    const message = CreateProjectActions.showError.mock.calls[0][0]
    expect(message.props.children).toBe('Oops')
  })

  test('does not show an error when the list request fails with a non-400 code', async () => {
    deleteGDriveUploadedFile.mockResolvedValue({success: true})
    getGoogleDriveUploadedFiles.mockRejectedValue({code: 500, msg: 'Oops'})
    const props = baseProps()
    const {result} = renderHook((p) => useGDriveFiles(p), {initialProps: props})
    await act(async () => {
      result.current.deleteFile({id: '1', name: 'a.docx'})
    })
    expect(CreateProjectActions.showError).not.toHaveBeenCalled()
  })

  test('deleteFile calls deleteGDriveUploadedFile and re-lists files on success', async () => {
    deleteGDriveUploadedFile.mockResolvedValue({success: true})
    getGoogleDriveUploadedFiles.mockResolvedValue({files: []})
    const props = baseProps()
    const {result} = renderHook((p) => useGDriveFiles(p), {initialProps: props})
    await act(async () => {
      result.current.deleteFile({id: '1', name: 'a.docx'})
    })
    expect(deleteGDriveUploadedFile).toHaveBeenCalledWith({
      fileId: '1',
      source: 'en-US',
      segmentationRule: 'standard',
      filtersTemplateId: 1,
    })
    expect(getGoogleDriveUploadedFiles).toHaveBeenCalled()
    expect(CreateProjectActions.hideErrors).toHaveBeenCalled()
  })

  test('deleteFile updates the uploaded names but does not re-list when the response is not successful', async () => {
    deleteGDriveUploadedFile.mockResolvedValue({success: false})
    const props = baseProps()
    const {result} = renderHook((p) => useGDriveFiles(p), {initialProps: props})
    await act(async () => {
      result.current.deleteFile({id: '1', name: 'a.docx'})
    })
    expect(props.setUploadedFilesNames).toHaveBeenCalled()
    expect(getGoogleDriveUploadedFiles).not.toHaveBeenCalled()
  })

  test('deleteFile clears the file list when deleteGDriveUploadedFile rejects', async () => {
    const props = baseProps()
    const {result} = renderHook((p) => useGDriveFiles(p), {initialProps: props})
    await uploadOneFile(result)
    expect(result.current.files).toHaveLength(1)
    deleteGDriveUploadedFile.mockRejectedValue(new Error('fail'))
    await act(async () => {
      result.current.deleteFile({id: '1', name: 'a.docx'})
    })
    expect(result.current.files).toEqual([])
  })

  // Documents real production behavior: `files.length >= maxNumberFiles` is
  // checked before `files.length > maxNumberFiles`, and the first is always
  // true whenever the second is, so the "Maximum X files allowed" message on
  // the second branch is unreachable dead code. Only the "No more files can
  // be loaded" message is ever actually shown.
  test('shows the "no more files can be loaded" message once the file count reaches the limit', async () => {
    global.config = {...global.config, maxNumberFiles: 1}
    getGoogleDriveUploadedFiles.mockResolvedValue({
      files: [
        {fileName: 'a.docx', fileExtension: 'docx', fileSize: 1, fileId: '1'},
        {fileName: 'b.docx', fileExtension: 'docx', fileSize: 1, fileId: '2'},
      ],
    })
    openGDriveFiles.mockResolvedValue({success: true})
    const props = baseProps()
    const {result} = renderHook((p) => useGDriveFiles(p), {initialProps: props})
    await act(async () => {
      result.current.pickerCallback({action: 'picked', docs: [{id: 'doc-1'}]})
    })
    expect(CreateProjectActions.showError).toHaveBeenCalledWith(
      'No more files can be loaded (the limit of 1 has been exceeded).',
    )
    expect(CreateProjectActions.showError).not.toHaveBeenCalledWith(
      expect.stringMatching(/Maximum 1 files allowed/),
    )
    global.config = {...global.config, maxNumberFiles: 10}
  })

  test('restarts conversions via changeGDriveSourceLang when sourceLang changes after files exist', async () => {
    changeGDriveSourceLang.mockResolvedValue({})
    const props = baseProps()
    const {result, rerender} = renderHook((p) => useGDriveFiles(p), {
      initialProps: props,
    })
    await uploadOneFile(result)
    changeGDriveSourceLang.mockClear()
    await act(async () => {
      rerender({...props, sourceLang: {code: 'fr-FR'}})
    })
    expect(changeGDriveSourceLang).toHaveBeenCalledWith({
      sourceLang: 'fr-FR',
      segmentation_rule: 'standard',
      filters_extraction_parameters_template_id: 1,
    })
    expect(CreateProjectActions.enableAnalyzeButton).toHaveBeenCalledWith(false)
  })

  test('shows an error when changeGDriveSourceLang rejects during restartConversions', async () => {
    changeGDriveSourceLang.mockRejectedValue(new Error('fail'))
    const props = baseProps()
    const {result, rerender} = renderHook((p) => useGDriveFiles(p), {
      initialProps: props,
    })
    await uploadOneFile(result)
    changeGDriveSourceLang.mockClear()
    changeGDriveSourceLang.mockRejectedValue(new Error('fail'))
    await act(async () => {
      rerender({...props, sourceLang: {code: 'fr-FR'}})
    })
    expect(CreateProjectActions.showError).toHaveBeenCalled()
    expect(
      React.isValidElement(CreateProjectActions.showError.mock.calls[0][0]),
    ).toBe(true)
  })

  test('restarts conversions when currentFiltersExtractionParameters changes, but not on an equal rerender', async () => {
    changeGDriveSourceLang.mockResolvedValue({})
    const props = baseProps()
    const {result, rerender} = renderHook((p) => useGDriveFiles(p), {
      initialProps: props,
    })
    await uploadOneFile(result)
    changeGDriveSourceLang.mockClear()

    await act(async () => {
      rerender({...props, currentFiltersExtractionParameters: {foo: 'bar'}})
    })
    expect(changeGDriveSourceLang).toHaveBeenCalledTimes(1)

    changeGDriveSourceLang.mockClear()
    await act(async () => {
      rerender({...props, currentFiltersExtractionParameters: {foo: 'bar'}})
    })
    expect(changeGDriveSourceLang).not.toHaveBeenCalled()
  })
})
