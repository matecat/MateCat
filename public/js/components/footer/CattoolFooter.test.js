import {fireEvent, render, screen} from '@testing-library/react'
import React from 'react'

import {CattoolFooter} from './CattoolFooter'
import CatToolStore from '../../stores/CatToolStore'
import SegmentActions from '../../actions/SegmentActions'
// import CatToolActions from '../../actions/CatToolActions'

jest.mock('../../actions/SegmentActions', () => ({
  gotoNextTranslatedSegment: jest.fn(),
  gotoNextUntranslatedSegment: jest.fn(),
}))

test('render properly', async () => {
  global.config = {
    allow_link_to_analysis: false,
    secondRevisionsCount: 0,
  }

  const langsIndex = {
    en: {code: 'en', name: 'English'},
    it: {code: 'it', name: 'Italian'},
  }
  const jobId = 123
  const projectId = 456

  render(
    <CattoolFooter
      languagesArray={Object.values(langsIndex)}
      source="it"
      target="en"
      idProject={projectId}
      idJob={jobId}
      isCJK={false}
      password="fake-password"
    />,
  )

  expect(
    screen.getByText((content, element) => {
      return element.id === 'job_id' && element.textContent == `Job ID ${jobId}`
    }),
  ).toBeVisible()

  const elLanguagePair = screen.getByTestId('language-pair')
  expect(elLanguagePair).toHaveTextContent('it → en')
  expect(elLanguagePair).toHaveAttribute('title', 'Italian → English')
  expect(elLanguagePair).toBeVisible()

  expect(screen.getByTestId('progress-bar')).toBeVisible()

  const elProgressAmount = screen.getByTestId('progress-bar-amount')
  expect(elProgressAmount).toBeVisible()
  expect(elProgressAmount).toHaveTextContent('-')

  /**
   * @TODO add tests for after loading is complete.
   */

  // CatToolActions.setProgress({TODO: 0})

  // await waitFor(() => {
  //   expect(elProgressAmount).toHaveTextContent('0')
  // })
})

test('onClickTodo navigates to next untranslated segment when not in review', () => {
  global.config = {isReview: false, allow_link_to_analysis: false}
  const getProgressSpy = jest
    .spyOn(CatToolStore, 'getProgress')
    .mockReturnValue({
      translationCompleted: false,
      raw: {total: 10},
      equivalent: {total: 10},
    })

  render(
    <CattoolFooter
      idProject="1"
      idJob="2"
      password="pass"
      languagesArray={[]}
      source="en-US"
      target="it-IT"
      isCJK={false}
    />,
  )

  screen.getByRole('button', {name: 'Go to next unconfirmed segment'}).click()

  expect(SegmentActions.gotoNextUntranslatedSegment).toHaveBeenCalled()
  expect(SegmentActions.gotoNextTranslatedSegment).not.toHaveBeenCalled()

  getProgressSpy.mockRestore()
})

test('onClickTodo shows tooltip instead of navigating when translation is completed', () => {
  global.config = {isReview: false, allow_link_to_analysis: false}
  const getProgressSpy = jest
    .spyOn(CatToolStore, 'getProgress')
    .mockReturnValue({
      translationCompleted: true,
      raw: {total: 10},
      equivalent: {total: 10},
    })

  render(
    <CattoolFooter
      idProject="1"
      idJob="2"
      password="pass"
      languagesArray={[]}
      source="en-US"
      target="it-IT"
      isCJK={false}
    />,
  )

  screen.getByRole('button', {name: 'Go to next unconfirmed segment'}).click()

  expect(SegmentActions.gotoNextTranslatedSegment).not.toHaveBeenCalled()
  expect(SegmentActions.gotoNextUntranslatedSegment).not.toHaveBeenCalled()

  getProgressSpy.mockRestore()
})

test('onClickOpenJobAnalysis opens the job analysis URL in a new tab', () => {
  global.config = {isReview: false, allow_link_to_analysis: true}
  // undefined (not null): CattoolFooter passes stats straight through to JobProgressBar,
  // which destructures it internally and only falls back to its `stats = {}` default
  // for an omitted/undefined prop, not for an explicit null.
  const getProgressSpy = jest
    .spyOn(CatToolStore, 'getProgress')
    .mockReturnValue(undefined)
  const openSpy = jest.spyOn(window, 'open').mockImplementation(() => {})

  render(
    <CattoolFooter
      idProject="42"
      idJob="99"
      password="secret"
      languagesArray={[]}
      source="en-US"
      target="it-IT"
      isCJK={false}
    />,
  )

  screen.getByRole('button', {name: 'Go to analysis page'}).click()

  expect(openSpy).toHaveBeenCalledWith('/jobanalysis/42-99-secret', '_blank')

  openSpy.mockRestore()
  getProgressSpy.mockRestore()
})

test('shows the weighted count with the raw count in brackets', () => {
  global.config = {isReview: false, allow_link_to_analysis: false}
  const getProgressSpy = jest
    .spyOn(CatToolStore, 'getProgress')
    .mockReturnValue({
      raw: {total: 137.4},
      equivalent: {total: 81.48},
      translate_todo_total: 120,
      translate_todo: 70.6,
    })

  render(
    <CattoolFooter
      idProject="1"
      idJob="2"
      password="pass"
      languagesArray={[]}
      source="en-US"
      target="it-IT"
      isCJK={false}
    />,
  )

  expect(screen.getByText(/^Words:/)).toHaveTextContent('Words: 81 (137)')
  expect(screen.getByText(/^To do:/)).toHaveTextContent('To do: 71 (120)')

  getProgressSpy.mockRestore()
})

test('shows zero counts as 0, not as the unknown-count dash', () => {
  global.config = {isReview: false, allow_link_to_analysis: false}
  const getProgressSpy = jest
    .spyOn(CatToolStore, 'getProgress')
    .mockReturnValue({
      raw: {total: 108},
      equivalent: {total: 0},
      translate_todo_total: 0,
      translate_todo: 0,
    })

  render(
    <CattoolFooter
      idProject="1"
      idJob="2"
      password="pass"
      languagesArray={[]}
      source="en-US"
      target="it-IT"
      isCJK={false}
    />,
  )

  expect(screen.getByText(/^Words:/)).toHaveTextContent('Words: 0 (108)')
  expect(screen.getByText(/^To do:/)).toHaveTextContent('To do: 0 (0)')

  getProgressSpy.mockRestore()
})

test('labels the count as characters for CJK languages', async () => {
  global.config = {isReview: false, allow_link_to_analysis: false}
  const getProgressSpy = jest
    .spyOn(CatToolStore, 'getProgress')
    .mockReturnValue({
      raw: {total: 200},
      equivalent: {total: 150},
    })

  render(
    <CattoolFooter
      idProject="1"
      idJob="2"
      password="pass"
      languagesArray={[]}
      source="en-US"
      target="ja-JP"
      isCJK={true}
    />,
  )

  expect(screen.getByText(/^Characters:/)).toHaveTextContent(
    'Characters: 150 (200)',
  )

  fireEvent.pointerOver(screen.getByText(/^Characters:/))
  expect(
    await screen.findByText('Weighted: 150 / Total: 200'),
  ).toBeInTheDocument()

  getProgressSpy.mockRestore()
})
