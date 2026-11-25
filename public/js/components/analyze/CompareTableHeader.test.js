import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import '@testing-library/jest-dom'
import CompareTableHeader from './CompareTableHeader'
import {ANALYSIS_WORKFLOW_TYPES, UNIT_COUNT} from '../../constants/Constants'

const job = {
  source: 'EN',
  source_name: 'English',
  target: 'IT',
  target_name: 'Italian',
  id: 42,
}

const baseProps = {
  job,
  countUnit: UNIT_COUNT.WORDS,
  workflowType: ANALYSIS_WORKFLOW_TYPES.STANDARD,
  thereIsChunkOutsourced: () => false,
  status: 'DONE',
  openSplitModal: jest.fn((id) => (e) => e && e.preventDefault && id),
  openMergeModal: jest.fn((id) => (e) => e && e.preventDefault && id),
  isSplit: false,
}

beforeEach(() => {
  global.config = {
    jobAnalysis: false,
    splitEnabled: true,
    splitFeatureAvailable: true,
  }
})

test('renders languages, id and word count unit label', () => {
  render(<CompareTableHeader {...baseProps} />)
  expect(screen.getByText('EN')).toBeInTheDocument()
  expect(screen.getByText('IT')).toBeInTheDocument()
  expect(screen.getByText('ID: 42')).toBeInTheDocument()
  expect(screen.getByText('Total word count')).toBeInTheDocument()
})

test('shows character count label when countUnit is CHARACTERS', () => {
  render(
    <CompareTableHeader {...baseProps} countUnit={UNIT_COUNT.CHARACTERS} />,
  )
  expect(screen.getByText('Total character count')).toBeInTheDocument()
})

test('shows the Industry weighted column only for STANDARD workflow', () => {
  const {rerender} = render(<CompareTableHeader {...baseProps} />)
  expect(screen.getByText('Industry weighted')).toBeInTheDocument()

  rerender(
    <CompareTableHeader
      {...baseProps}
      workflowType={ANALYSIS_WORKFLOW_TYPES.MTQE}
    />,
  )
  expect(screen.queryByText('Industry weighted')).not.toBeInTheDocument()
})

test('shows a Split button when not split, calling openSplitModal on click', async () => {
  const spy = jest.fn()
  const openSplitModal = jest.fn((id) => (e) => {
    e.preventDefault()
    spy(id)
  })
  render(<CompareTableHeader {...baseProps} openSplitModal={openSplitModal} />)

  const splitButton = screen.getByText('Split')
  expect(splitButton.closest('button')).toBeEnabled()

  await userEvent.click(splitButton)
  expect(spy).toHaveBeenCalledWith(42)
})

test('disables Split when status is not DONE or the job is already outsourced', () => {
  const {rerender} = render(<CompareTableHeader {...baseProps} status="BUSY" />)
  expect(screen.getByText('Split').closest('button')).toBeDisabled()

  rerender(
    <CompareTableHeader
      {...baseProps}
      status="DONE"
      thereIsChunkOutsourced={() => true}
    />,
  )
  expect(screen.getByText('Split').closest('button')).toBeDisabled()
})

test('shows a Merge button when isSplit is true, calling openMergeModal on click', async () => {
  const spy = jest.fn()
  const openMergeModal = jest.fn((id) => (e) => {
    e.preventDefault()
    spy(id)
  })
  render(
    <CompareTableHeader
      {...baseProps}
      isSplit={true}
      openMergeModal={openMergeModal}
    />,
  )

  const mergeButton = screen.getByText('Merge')
  await userEvent.click(mergeButton)
  expect(spy).toHaveBeenCalledWith(42)
})

test('renders no split/merge action when jobAnalysis mode is active', () => {
  global.config = {jobAnalysis: true, splitEnabled: true}
  render(<CompareTableHeader {...baseProps} />)
  expect(screen.queryByText('Split')).not.toBeInTheDocument()
  expect(screen.queryByText('Merge')).not.toBeInTheDocument()
})

test('shows a disabled, hoverable Split hint when splitEnabled is false', async () => {
  global.config = {
    jobAnalysis: false,
    splitEnabled: false,
    splitFeatureAvailable: true,
  }
  const openSplitModal = jest.fn((id) => (e) => e && e.preventDefault && id)
  render(<CompareTableHeader {...baseProps} openSplitModal={openSplitModal} />)

  const splitButton = screen.getByText('Split').closest('button')
  expect(splitButton).toHaveAttribute('aria-disabled', 'true')

  await userEvent.click(splitButton)
  expect(openSplitModal).not.toHaveBeenCalled()
})

test('shows a disabled, hoverable Merge hint when splitEnabled is false', async () => {
  global.config = {
    jobAnalysis: false,
    splitEnabled: false,
    splitFeatureAvailable: true,
  }
  const openMergeModal = jest.fn((id) => (e) => e && e.preventDefault && id)
  render(
    <CompareTableHeader
      {...baseProps}
      isSplit
      openMergeModal={openMergeModal}
    />,
  )

  const mergeButton = screen.getByText('Merge').closest('button')
  expect(mergeButton).toHaveAttribute('aria-disabled', 'true')

  await userEvent.click(mergeButton)
  expect(openMergeModal).not.toHaveBeenCalled()
})

test('renders no split action when splitFeatureAvailable is false', () => {
  global.config = {
    jobAnalysis: false,
    splitEnabled: true,
    splitFeatureAvailable: false,
  }
  render(<CompareTableHeader {...baseProps} />)
  expect(screen.queryByText('Split')).not.toBeInTheDocument()
})
