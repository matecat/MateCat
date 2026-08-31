import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {SpacePlaceholder} from './SpacePlaceholder'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'
import SegmentActions from '../../../../actions/SegmentActions'
import {setTagSignatureMiddleware} from '../../../segments/utils/DraftMatecatUtils/tagModel'

jest.mock('../../../../actions/SegmentActions', () => ({
  refreshTagMap: jest.fn(),
}))

jest.mock('../../../segments/utils/DraftMatecatUtils/tagModel', () => ({
  setTagSignatureMiddleware: jest.fn(),
}))

const renderComponent = (metadata = {}, setUserMetadataKey = jest.fn()) =>
  render(
    <ApplicationWrapperContext.Provider
      value={{userInfo: {metadata}, setUserMetadataKey}}
    >
      <SpacePlaceholder />
    </ApplicationWrapperContext.Provider>,
  )

beforeEach(() => {
  jest.clearAllMocks()
})

test('renders unchecked by default', () => {
  renderComponent()
  expect(screen.getByTestId('switch-space-counter')).not.toBeChecked()
})

test('renders checked when metadata flag is set', () => {
  renderComponent({show_whitespace: 1})
  expect(screen.getByTestId('switch-space-counter')).toBeChecked()
})

test('toggling persists metadata and refreshes tag map', async () => {
  const setUserMetadataKey = jest.fn()
  renderComponent({}, setUserMetadataKey)

  await userEvent.click(screen.getByTestId('switch-space-counter'))

  expect(setUserMetadataKey).toHaveBeenCalledWith('show_whitespace', 1)
  expect(setTagSignatureMiddleware).toHaveBeenCalledWith(
    'space',
    expect.any(Function),
  )
  expect(setTagSignatureMiddleware.mock.calls[0][1]()).toBe(true)
  expect(SegmentActions.refreshTagMap).toHaveBeenCalled()
})
