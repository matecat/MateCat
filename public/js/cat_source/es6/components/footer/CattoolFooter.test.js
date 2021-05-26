import {render, screen} from '@testing-library/react'
import React from 'react'

import {CattolFooter} from './CattoolFooter'
// import CatToolActions from '../../actions/CatToolActions'

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
    <CattolFooter
      languagesArray={Object.values(langsIndex)}
      source="it"
      target="en"
      idProject={projectId}
      idJob={jobId}
      isCJK={false}
      isReview={false}
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

  expect(screen.getByText('Calculating word count...')).toBeVisible()

  /**
   * @TODO add tests for after loading is complete.
   */

  // CatToolActions.setProgress({TODO: 0})

  // await waitFor(() => {
  //   expect(elProgressAmount).toHaveTextContent('0')
  // })
})
