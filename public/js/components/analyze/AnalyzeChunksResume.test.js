import React from 'react'
import {fromJS} from 'immutable'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'

import AnalyzeChunksResume from './AnalyzeChunksResume'
import ModalsActions from '../../actions/ModalsActions'

const JOB_ID = 90

const project = fromJS({
  id: 9,
  name: 'Test',
  analysis: {workflow_type: 'standard'},
  jobs: [{id: JOB_ID, password: 'a5b852c4fe52'}],
})

const chunk = {
  id: JOB_ID,
  password: 'a5b852c4fe52',
  total_equivalent: 100,
  total_raw_wc: 100,
  urls: {
    t: 'https://dev.matecat.com/translate/Test/en-US-la-XN/90-a5b852c4fe52',
  },
}

const jobsAnalysis = [
  {
    id: JOB_ID,
    source_name: 'English US',
    target_name: 'Latin',
    count_unit: 'words',
    outsource_available: false,
    total_equivalent: 100,
    chunks: [chunk],
  },
]

const renderComponent = () =>
  render(
    <AnalyzeChunksResume
      project={project}
      jobsAnalysis={jobsAnalysis}
      status="DONE"
      showAnalysis={true}
      openAnalysisReport={() => {}}
    />,
  )

const splitButton = () => screen.queryByText('Split')?.closest('.split.button')

describe('AnalyzeChunksResume split button', () => {
  beforeEach(() => {
    global.config = {
      basepath: 'http://localhost/',
      enableMultiDomainApi: false,
      jobAnalysis: false,
      splitFeatureAvailable: true,
      splitEnabled: true,
    }
    jest.spyOn(ModalsActions, 'openSplitJobModal').mockImplementation(() => {})
  })

  afterEach(() => {
    jest.restoreAllMocks()
  })

  test('is absent when the split feature is not available in this UI', () => {
    global.config.splitFeatureAvailable = false

    renderComponent()

    expect(screen.queryByText('Split')).not.toBeInTheDocument()
  })

  test('is clickable when the caller may split', async () => {
    renderComponent()

    const button = splitButton()
    expect(button).toBeInTheDocument()
    expect(button).not.toHaveClass('disabled')

    await userEvent.click(button)

    expect(ModalsActions.openSplitJobModal).toHaveBeenCalledTimes(1)
  })

  // The point of having two flags: availability keeps the button on the page, split_enabled decides
  // whether it does anything. Hiding it here instead would leave the caller with no clue the action
  // exists, and leaving it live would offer an action the split endpoints answer 403 to.
  test('stays visible but inert when the caller may not split', async () => {
    global.config.splitEnabled = false

    renderComponent()

    const button = splitButton()
    expect(button).toBeInTheDocument()
    expect(button).toHaveClass('split-not-allowed')
    expect(button).toHaveAttribute('aria-disabled', 'true')

    await userEvent.click(button)

    expect(ModalsActions.openSplitJobModal).not.toHaveBeenCalled()
  })

  // The refused button must say why, and it can only do that while it still receives pointer events —
  // hence the modifier class rather than semantic's `disabled`, which sets pointer-events: none.
  test('explains on hover why the caller may not split', async () => {
    global.config.splitEnabled = false

    renderComponent()

    await userEvent.hover(splitButton())

    expect(
      await screen.findByText(
        'Only the project owner or a member of its team can split a job',
      ),
    ).toBeInTheDocument()
  })
})
