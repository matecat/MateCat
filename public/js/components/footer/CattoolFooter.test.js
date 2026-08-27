import {render, screen} from '@testing-library/react'
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
      return content != '' && element.textContent == `Job ID: ${jobId}`
    }),
  ).toBeVisible()

  const elLanguagePair = screen.getByTestId('language-pair')
  expect(elLanguagePair).toHaveTextContent('Italian → English')
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
  const getProgressSpy = jest.spyOn(CatToolStore, 'getProgress').mockReturnValue({
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

  screen.getByText('To do').closest('.grey-box-row').querySelector('button').click()

  expect(SegmentActions.gotoNextUntranslatedSegment).toHaveBeenCalled()
  expect(SegmentActions.gotoNextTranslatedSegment).not.toHaveBeenCalled()

  getProgressSpy.mockRestore()
})

test('onClickTodo shows tooltip instead of navigating when translation is completed', () => {
  global.config = {isReview: false, allow_link_to_analysis: false}
  const getProgressSpy = jest.spyOn(CatToolStore, 'getProgress').mockReturnValue({
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

  screen.getByText('To do').closest('.grey-box-row').querySelector('button').click()

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

  screen.getByText('Total words').closest('.grey-box-row').querySelector('button').click()

  expect(openSpy).toHaveBeenCalledWith('/jobanalysis/42-99-secret', '_blank')

  openSpy.mockRestore()
  getProgressSpy.mockRestore()
})
