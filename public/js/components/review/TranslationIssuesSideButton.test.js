import React from 'react'
import {render, screen} from '@testing-library/react'
import TranslationIssuesSideButton from './TranslationIssuesSideButton'

jest.mock(
  '../review_extended/ReviewExtendedTranslationIssuesSideButton',
  () => (props) => <div data-testid="inner" data-count={props.count} />,
)

test('forwards all props to ReviewExtendedTranslationIssuesSideButton', () => {
  render(<TranslationIssuesSideButton count={3} />)
  expect(screen.getByTestId('inner')).toHaveAttribute('data-count', '3')
})
