import React from 'react'
import {render, screen} from '@testing-library/react'
import {buildVolumeAnalysis} from '../../../../mocks/volumeAnalysisMock'
import {deriveAnalysisView} from '../utils/deriveAnalysisView'
import {countErrorSegments, JobActions} from './JobActions'

jest.mock('../../common/JobDownloadMenu', () => ({
  JobDownloadMenu: ({password}) => (
    <div data-testid="download-menu">{password}</div>
  ),
}))

const {jobs} = deriveAnalysisView({analysis: buildVolumeAnalysis()})
const [italian, french] = jobs
const project = {id: 20, password: 'projpass', jobs: []}

const renderActions = (props = {}) =>
  render(
    <JobActions
      project={project}
      job={french}
      chunk={null}
      actionsEnabled={true}
      {...props}
    />,
  )

describe('JobActions', () => {
  beforeEach(() => {
    global.config = {
      ...global.config,
      splitEnabled: true,
      splitFeatureAvailable: true,
    }
  })

  test('an unsplit job can be split and downloaded', () => {
    renderActions()
    expect(screen.getByRole('button', {name: 'Split'})).toBeEnabled()
    expect(screen.getByTestId('download-menu')).toHaveTextContent('frpass')
  })

  test('the job page shows the download only', () => {
    renderActions({showSplitMerge: false})
    expect(
      screen.queryByRole('button', {name: 'Split'}),
    ).not.toBeInTheDocument()
    expect(screen.getByTestId('download-menu')).toBeInTheDocument()
  })

  test('a whole split job merges and downloads with its first chunk', () => {
    renderActions({job: italian})
    expect(screen.getByRole('button', {name: 'Merge'})).toBeEnabled()
    expect(screen.getByTestId('download-menu')).toHaveTextContent('chunkpass1')
  })

  test('a chunk of a split job has no download', () => {
    renderActions({job: italian, chunk: italian.chunks[1]})
    expect(screen.queryByTestId('download-menu')).not.toBeInTheDocument()
  })

  test('countErrorSegments counts each segment once across categories', () => {
    expect(
      countErrorSegments({ERROR: {Categories: {A: [1, 2], B: [2, 3]}}}),
    ).toBe(3)
    expect(countErrorSegments({})).toBe(0)
    expect(countErrorSegments(undefined)).toBe(0)
  })
})
