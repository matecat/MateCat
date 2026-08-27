import {render, screen, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import React from 'react'

import {ActionMenu} from './ActionMenu'
import {exportQualityReport} from '../../api/exportQualityReport'
import CatToolActions from '../../actions/CatToolActions'
import ModalsActions from '../../actions/ModalsActions'

jest.mock('../../api/exportQualityReport')
jest.mock('../../actions/CatToolActions')
jest.mock('../../actions/ModalsActions')

class ResizeObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
}

window.ResizeObserver = ResizeObserver
global.URL.createObjectURL = jest.fn(() => 'blob:mock')

const openMenu = async () => {
  await userEvent.click(screen.getByRole('button'))
}

beforeEach(() => {
  window.open = jest.fn()
})

test('Quality report menu shows revise and translate links', async () => {
  render(
    <ActionMenu
      jobUrls={{
        revise_urls: [{url: 'https://dev.matecat.com/revise/1'}],
        translate_url: 'https://dev.matecat.com/translate/1',
      }}
    />,
  )

  await openMenu()

  expect(screen.getByText('Revise')).toBeInTheDocument()
  expect(screen.getByText('Translate')).toBeInTheDocument()
  expect(screen.getByText('Download QA report CSV')).toBeInTheDocument()
  expect(screen.getByText('Download QA report JSON')).toBeInTheDocument()
  expect(screen.getByText('Download QA report XML')).toBeInTheDocument()
})

test('Quality report menu hides revise link when there are no revise_urls', async () => {
  render(<ActionMenu jobUrls={{translate_url: 'https://dev.matecat.com/1'}} />)

  await openMenu()

  expect(screen.queryByText('Revise')).not.toBeInTheDocument()
  expect(screen.getByText('Translate')).toBeInTheDocument()
})

test('Clicking Revise opens the first revise url', async () => {
  render(
    <ActionMenu
      jobUrls={{
        revise_urls: [{url: 'https://dev.matecat.com/revise/1'}],
        translate_url: 'https://dev.matecat.com/translate/1',
      }}
    />,
  )

  await openMenu()
  await userEvent.click(screen.getByText('Revise'))

  expect(window.open).toHaveBeenCalledWith('https://dev.matecat.com/revise/1')
})

test('Clicking Translate opens the translate url', async () => {
  render(
    <ActionMenu
      jobUrls={{translate_url: 'https://dev.matecat.com/translate/1'}}
    />,
  )

  await openMenu()
  await userEvent.click(screen.getByText('Translate'))

  expect(window.open).toHaveBeenCalledWith(
    'https://dev.matecat.com/translate/1',
  )
})

test('Downloading the CSV report triggers a download', async () => {
  const blob = new Blob(['csv'])
  exportQualityReport.mockResolvedValueOnce({blob, filename: 'report.csv'})

  render(<ActionMenu jobUrls={{}} />)

  await openMenu()
  await userEvent.click(screen.getByText('Download QA report CSV'))

  await waitFor(() => expect(exportQualityReport).toHaveBeenCalledTimes(1))
})

test('A failing CSV export shows an error notification', async () => {
  exportQualityReport.mockRejectedValueOnce({status: 500})

  render(<ActionMenu jobUrls={{}} />)

  await openMenu()
  await userEvent.click(screen.getByText('Download QA report CSV'))

  await waitFor(() =>
    expect(CatToolActions.addNotification).toHaveBeenCalledWith({
      title: 'Error',
      text: 'Downloading CSV error status code: 500',
      type: 'error',
    }),
  )
})

test('Downloading the JSON report triggers a download', async () => {
  const blob = new Blob(['{}'])
  exportQualityReport.mockResolvedValueOnce({blob, filename: 'report.json'})

  render(<ActionMenu jobUrls={{}} />)

  await openMenu()
  await userEvent.click(screen.getByText('Download QA report JSON'))

  await waitFor(() =>
    expect(exportQualityReport).toHaveBeenCalledWith({format: 'json'}),
  )
})

test('A failing JSON export shows an error notification', async () => {
  exportQualityReport.mockRejectedValueOnce({status: 500})

  render(<ActionMenu jobUrls={{}} />)

  await openMenu()
  await userEvent.click(screen.getByText('Download QA report JSON'))

  await waitFor(() =>
    expect(CatToolActions.addNotification).toHaveBeenCalledWith({
      title: 'Error',
      text: 'Downloading JSON error status code: 500',
      type: 'error',
    }),
  )
})

test('Downloading the XML report triggers a download', async () => {
  const blob = new Blob(['<xml/>'])
  exportQualityReport.mockResolvedValueOnce({blob, filename: 'report.xml'})

  render(<ActionMenu jobUrls={{}} />)

  await openMenu()
  await userEvent.click(screen.getByText('Download QA report XML'))

  await waitFor(() =>
    expect(exportQualityReport).toHaveBeenCalledWith({format: 'xml'}),
  )
})

test('A failing XML export shows an error notification', async () => {
  exportQualityReport.mockRejectedValueOnce({status: 500})

  render(<ActionMenu jobUrls={{}} />)

  await openMenu()
  await userEvent.click(screen.getByText('Download QA report XML'))

  await waitFor(() =>
    expect(CatToolActions.addNotification).toHaveBeenCalledWith({
      title: 'Error',
      text: 'Downloading XML error status code: 500',
      type: 'error',
    }),
  )
})

test('Cattool menu shows revise, analysis, converter and shortcuts entries', async () => {
  render(
    <ActionMenu
      cattoolMenu
      jobUrls={{}}
      allowLinkToAnalysis
      analysisEnabled
      projectName="tesla.docx"
      source_code="en-US"
      target_code="it-IT"
      jid="1"
      password="pwd"
      reviewPassword="revpwd"
      pid="1"
    />,
  )

  await openMenu()

  expect(screen.getByText('Revise')).toBeInTheDocument()
  expect(screen.getByText('Volume analysis')).toBeInTheDocument()
  expect(screen.getByText('XLIFF-to-target converter')).toBeInTheDocument()
  expect(screen.getByText('Shortcuts')).toBeInTheDocument()
})

test('Clicking translate for reviewers opens the translate url', async () => {
  render(
    <ActionMenu
      cattoolMenu
      jobUrls={{}}
      isReview
      projectName="tesla.docx"
      source_code="en-US"
      target_code="it-IT"
      jid="1"
      password="pwd"
    />,
  )

  await openMenu()

  expect(screen.queryByText('Revise')).not.toBeInTheDocument()

  await userEvent.click(screen.getByText('Translate'))

  expect(window.open).toHaveBeenCalledWith(
    '/translate/tesla.docx/en-US-it-IT/1-pwd',
  )
})

test('Cattool menu hides volume analysis when analysis is not enabled', async () => {
  render(
    <ActionMenu
      cattoolMenu
      jobUrls={{}}
      allowLinkToAnalysis
      analysisEnabled={false}
    />,
  )

  await openMenu()

  expect(screen.queryByText('Volume analysis')).not.toBeInTheDocument()
})

test('Clicking volume analysis opens the job analysis url', async () => {
  render(
    <ActionMenu
      cattoolMenu
      jobUrls={{}}
      allowLinkToAnalysis
      analysisEnabled
      pid="1"
      jid="2"
      password="pwd"
    />,
  )

  await openMenu()
  await userEvent.click(screen.getByText('Volume analysis'))

  expect(window.open).toHaveBeenCalledWith('/jobanalysis/1-2-pwd')
})

test('Clicking Revise on the cattool menu opens the revise url', async () => {
  render(
    <ActionMenu
      cattoolMenu
      jobUrls={{}}
      projectName="tesla.docx"
      source_code="en-US"
      target_code="it-IT"
      jid="1"
      reviewPassword="revpwd"
    />,
  )

  await openMenu()
  await userEvent.click(screen.getByText('Revise'))

  expect(window.open).toHaveBeenCalledWith(
    '/revise/tesla.docx/en-US-it-IT/1-revpwd',
  )
})

test('Clicking the XLIFF-to-target converter opens the converter url', async () => {
  render(<ActionMenu cattoolMenu jobUrls={{}} />)

  await openMenu()
  await userEvent.click(screen.getByText('XLIFF-to-target converter'))

  expect(window.open).toHaveBeenCalledWith('/utils/xliff-to-target')
})

test('Clicking Shortcuts opens the shortcuts modal', async () => {
  render(<ActionMenu cattoolMenu jobUrls={{}} />)

  await openMenu()
  await userEvent.click(screen.getByText('Shortcuts'))

  expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
    expect.any(Function),
    {},
    'Shortcuts',
  )
})
