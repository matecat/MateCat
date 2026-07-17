import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'

import LexiqaTooltipInfo from './LexiqaTooltipInfo.component'
import LXQ from '../../../utils/lxq.main'

jest.mock('../../../utils/lxq.main', () => ({
  ignoreError: jest.fn(),
}))

const errorMessage = {msg: 'error message', error: 'spelling', type: 'error'}
const suggestionMessage = {
  msg: 'suggested word',
  start: 2,
  end: 6,
  type: 'suggestion',
}

afterEach(() => {
  jest.clearAllMocks()
})

test('renders error messages with an ignore control', () => {
  render(<LexiqaTooltipInfo messages={[errorMessage]} onReplaceWord={jest.fn()} />)

  expect(screen.getByText('error message')).toBeInTheDocument()
  expect(screen.getByText('Ignore')).toBeInTheDocument()
})

test('clicking ignore calls LXQ.ignoreError with the error payload', () => {
  render(<LexiqaTooltipInfo messages={[errorMessage]} onReplaceWord={jest.fn()} />)

  fireEvent.click(screen.getByText('Ignore'))

  expect(LXQ.ignoreError).toHaveBeenCalledTimes(1)
  expect(LXQ.ignoreError).toHaveBeenCalledWith(errorMessage.error)
})

test('renders suggestion messages in a suggestion list, separate from errors', () => {
  render(
    <LexiqaTooltipInfo
      messages={[errorMessage, suggestionMessage]}
      onReplaceWord={jest.fn()}
    />,
  )

  expect(screen.getByText('error message')).toBeInTheDocument()
  expect(screen.getByText('suggested word')).toBeInTheDocument()
})

test('clicking a suggestion calls onReplaceWord with the word and its offsets', () => {
  const onReplaceWord = jest.fn()
  render(
    <LexiqaTooltipInfo messages={[suggestionMessage]} onReplaceWord={onReplaceWord} />,
  )

  fireEvent.click(screen.getByText('suggested word'))

  expect(onReplaceWord).toHaveBeenCalledTimes(1)
  expect(onReplaceWord).toHaveBeenCalledWith({
    newWord: suggestionMessage.msg,
    start: suggestionMessage.start,
    end: suggestionMessage.end,
  })
})

test('renders nothing extra when there are no suggestions', () => {
  render(<LexiqaTooltipInfo messages={[errorMessage]} onReplaceWord={jest.fn()} />)

  expect(screen.queryByRole('list')).not.toBeInTheDocument()
})
