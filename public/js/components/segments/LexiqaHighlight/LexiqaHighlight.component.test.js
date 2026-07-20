import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'

import LexiqaHighlight from './LexiqaHighlight.component'
import LexiqaUtils from '../../../utils/lxq.main'

jest.mock('../../../utils/lxq.main', () => ({
  buildTooltipMessages: jest.fn(),
}))

jest.mock('../TooltipInfo/LexiqaTooltipInfo.component', () => (props) => (
  <div
    data-testid="tooltip-info"
    onClick={() =>
      props.onReplaceWord({newWord: 'fixed', start: props.messages[0].start, end: props.messages[0].end})
    }
  >
    {props.messages.map((m) => m.msg).join(',')}
  </div>
))

const warning = {start: 0, end: 5, blockKey: 'block-1', myClass: 'spelling', color: 'red'}

const baseProps = {
  blockKey: 'block-1',
  start: 0,
  end: 5,
  isSource: false,
  sid: '1',
  getUpdatedSegmentInfo: () => ({segmentOpened: true}),
  replaceWordAt: jest.fn(),
  children: 'wrng word',
}

beforeEach(() => {
  jest.clearAllMocks()
  LexiqaUtils.buildTooltipMessages.mockReturnValue([
    {msg: 'suggested fix', start: 0, end: 5, type: 'suggestion'},
  ])
})

test('renders nothing when no warning matches the given range', () => {
  const {container} = render(
    <LexiqaHighlight {...baseProps} warnings={[]}>
      wrng word
    </LexiqaHighlight>,
  )

  expect(container).toBeEmptyDOMElement()
})

test('highlights the matched range with the warning color', () => {
  render(
    <LexiqaHighlight {...baseProps} warnings={[{...warning, errorid: 'e1'}]}>
      wrng word
    </LexiqaHighlight>,
  )

  expect(screen.getByText('wrng word').style.backgroundColor).toBe('red')
})

test('does not build tooltip messages when the warning has no class/errorid', () => {
  render(
    <LexiqaHighlight {...baseProps} warnings={[{start: 0, end: 5, blockKey: 'block-1'}]}>
      wrng word
    </LexiqaHighlight>,
  )

  expect(LexiqaUtils.buildTooltipMessages).not.toHaveBeenCalled()
})

test('forwards replaceWordAt to LexiqaTooltipInfo through the tooltip content', async () => {
  render(
    <LexiqaHighlight {...baseProps} warnings={[{...warning, errorid: 'e1'}]}>
      wrng word
    </LexiqaHighlight>,
  )

  fireEvent.pointerEnter(screen.getByText('wrng word').parentElement)
  await act(() => new Promise((resolve) => setTimeout(resolve, 350)))

  fireEvent.click(await screen.findByTestId('tooltip-info'))

  expect(baseProps.replaceWordAt).toHaveBeenCalledWith({
    newWord: 'fixed',
    start: 0,
    end: 5,
  })
})
