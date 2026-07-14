import {render} from '@testing-library/react'
import React from 'react'
import TranslationIssuesSideButton from './TranslationIssuesSideButton'

jest.mock(
  '../review_extended/ReviewExtendedTranslationIssuesSideButton',
  () =>
    function MockReviewSideButton(props) {
      return <div data-testid="review-side-button" {...props} />
    },
)

test('renders ReviewExtendedTranslationIssuesSideButton with forwarded props', () => {
  const {getByTestId} = render(<TranslationIssuesSideButton foo="bar" />)
  expect(getByTestId('review-side-button')).toBeInTheDocument()
})
