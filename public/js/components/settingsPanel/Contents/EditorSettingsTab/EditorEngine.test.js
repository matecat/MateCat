import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {EditorEngine} from './EditorEngine'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'

const originalBuildFlag = process.env.ENABLE_NEW_EDITOR
beforeEach(() => (process.env.ENABLE_NEW_EDITOR = 'true'))
afterAll(() => {
  if (originalBuildFlag === undefined) delete process.env.ENABLE_NEW_EDITOR
  else process.env.ENABLE_NEW_EDITOR = originalBuildFlag
})

const renderComponent = (metadata = {}, setUserMetadataKey = jest.fn()) =>
  render(
    <ApplicationWrapperContext.Provider
      value={{userInfo: {metadata}, setUserMetadataKey}}
    >
      <EditorEngine />
    </ApplicationWrapperContext.Provider>,
  )

test('is on for a user who never picked an editor', () => {
  renderComponent()
  expect(screen.getByTestId('switch-editor-engine')).toBeChecked()
})

test('is off for a user who switched back to the previous editor', () => {
  renderComponent({editor_engine: 'draft'})
  expect(screen.getByTestId('switch-editor-engine')).not.toBeChecked()
})

test('turning it off saves the previous editor, turning it on again saves the new one', async () => {
  const setUserMetadataKey = jest.fn()
  renderComponent({}, setUserMetadataKey)
  const toggle = screen.getByTestId('switch-editor-engine')

  await userEvent.click(toggle)
  expect(setUserMetadataKey).toHaveBeenLastCalledWith('editor_engine', 'draft')
  expect(toggle).not.toBeChecked()

  await userEvent.click(toggle)
  expect(setUserMetadataKey).toHaveBeenLastCalledWith('editor_engine', 'cm6')
  expect(toggle).toBeChecked()
})
