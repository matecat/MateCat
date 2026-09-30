import React from 'react'
import {render, screen} from '@testing-library/react'

import {IcuHighlight} from './IcuHighlight'

jest.mock('../common/Tooltip', () => ({
  __esModule: true,
  default: ({children, content}) => (
    <div data-testid="tooltip">
      <div data-testid="tooltip-content">{content}</div>
      {children}
    </div>
  ),
}))

const baseProps = {
  start: 0,
  end: 5,
  blockKey: 'block-1',
}

test('renders plain content when there is no matching token', () => {
  render(
    <IcuHighlight {...baseProps} tokens={[]} isTarget={true}>
      {'{count}'}
    </IcuHighlight>,
  )

  expect(screen.getByText('{count}')).toBeInTheDocument()
  expect(screen.queryByTestId('tooltip')).not.toBeInTheDocument()
  expect(document.querySelector('.icuItem-error')).toBeNull()
})

test('renders plain content when the matching token is not an error', () => {
  const tokens = [{start: 0, end: 5, blockKey: 'block-1', type: 'valid'}]
  render(
    <IcuHighlight {...baseProps} tokens={tokens} isTarget={true}>
      {'{count}'}
    </IcuHighlight>,
  )

  expect(screen.queryByTestId('tooltip')).not.toBeInTheDocument()
})

test('renders plain content for a matching error token on the source side', () => {
  const tokens = [{start: 0, end: 5, blockKey: 'block-1', type: 'error', message: ['bad']}]
  render(
    <IcuHighlight {...baseProps} tokens={tokens} isTarget={false}>
      {'{count}'}
    </IcuHighlight>,
  )

  expect(screen.queryByTestId('tooltip')).not.toBeInTheDocument()
  expect(document.querySelector('.icuItem-error')).toBeNull()
})

test('renders an error tooltip with all messages for a matching target error token', () => {
  const tokens = [
    {
      start: 0,
      end: 5,
      blockKey: 'block-1',
      type: 'error',
      message: ['missing closing brace', 'unexpected token'],
    },
  ]
  render(
    <IcuHighlight {...baseProps} tokens={tokens} isTarget={true}>
      {'{count}'}
    </IcuHighlight>,
  )

  expect(screen.getByTestId('tooltip')).toBeInTheDocument()
  expect(document.querySelector('.icuItem-error')).toBeInTheDocument()
  expect(screen.getByText('ICU syntax error')).toBeInTheDocument()
  expect(screen.getByText('missing closing brace')).toBeInTheDocument()
  expect(screen.getByText('unexpected token')).toBeInTheDocument()
})
