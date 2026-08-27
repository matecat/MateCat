import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import JobMetadata from './JobMetadata'
import JobMetadataModal from '../../modals/JobMetadataModal'
import SegmentStore from '../../../stores/SegmentStore'
import ModalsActions from '../../../actions/ModalsActions'
import CatToolStore from '../../../stores/CatToolStore'
import CattolConstants from '../../../constants/CatToolConstants'

jest.mock('../../../actions/ModalsActions')

beforeEach(() => {
  global.config = {userMail: 'user@matecat.com'}
  jest.clearAllMocks()
  jest.spyOn(SegmentStore, 'getCurrentSegment').mockReturnValue(undefined)
})

test('renders nothing when there is no metadata to show', () => {
  const {container} = render(<JobMetadata metadata={undefined} />)
  expect(container.firstChild).toBeNull()
})

test('shows the button when metadata contains project info and opens the modal on click', () => {
  SegmentStore.getCurrentSegment.mockReturnValue({
    id_file: '3',
    id_file_part: '5',
  })
  const projectInfo = {name: 'My project'}
  render(
    <JobMetadata metadata={{project: {project_info: projectInfo}}} />,
  )

  const button = screen.getByRole('button')
  fireEvent.click(button)

  expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
    JobMetadataModal,
    {
      currentFile: 3,
      currentFilePart: 5,
      files: undefined,
      projectInfo,
    },
    'Job instructions and references',
    {minWidth: 600, minHeight: 400, maxWidth: 900},
  )
})

test('does not show the button when metadata has no project info', () => {
  const {container} = render(<JobMetadata metadata={{project: {}}} />)
  expect(container.firstChild).toBeNull()
})

test('shows the button when the store reports files with instructions', () => {
  render(<JobMetadata metadata={undefined} />)
  expect(screen.queryByRole('button')).toBeNull()

  const files = [
    {metadata: {instructions: 'Please read this'}},
    {metadata: {}},
  ]
  act(() => {
    CatToolStore.emit(CattolConstants.STORE_FILES_INFO, files)
  })

  const button = screen.getByRole('button')
  fireEvent.click(button)
  expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
    JobMetadataModal,
    expect.objectContaining({files}),
    'Job instructions and references',
    expect.any(Object),
  )
})

test('ignores files without instructions or references from the store', () => {
  render(<JobMetadata metadata={undefined} />)
  const files = [{metadata: {}}, null]
  act(() => {
    CatToolStore.emit(CattolConstants.STORE_FILES_INFO, files)
  })
  expect(screen.queryByRole('button')).toBeNull()
})
