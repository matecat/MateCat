import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import React from 'react'

import {
  UnmodifiedFuzzyMatchModal,
  HIDE_UNMODIFIED_FUZZY_MATCH_MODAL_STORAGE,
} from './UnmodifiedFuzzyMatchModal'
import ModalsActions from '../../actions/ModalsActions'

jest.mock('../../actions/ModalsActions', () => ({
  onCloseModal: jest.fn(),
}))

describe('UnmodifiedFuzzyMatchModal', () => {
  beforeEach(() => {
    localStorage.clear()
    jest.clearAllMocks()
  })

  test('storage key is scoped to both job and user', () => {
    expect(HIDE_UNMODIFIED_FUZZY_MATCH_MODAL_STORAGE).toBe(
      'unmodified-fuzzy-match-modal' + config.id_job + '-' + config.userMail,
    )
    expect(HIDE_UNMODIFIED_FUZZY_MATCH_MODAL_STORAGE).toContain(
      config.userMail,
    )
  })

  test('persists the "don\'t show again" choice under the job+user key on confirm', async () => {
    const successCallback = jest.fn()
    render(<UnmodifiedFuzzyMatchModal successCallback={successCallback} />)

    await userEvent.click(
      screen.getByLabelText(/don't show this again for this job/i),
    )
    await userEvent.click(screen.getByText('Confirm anyway'))

    expect(
      localStorage.getItem(HIDE_UNMODIFIED_FUZZY_MATCH_MODAL_STORAGE),
    ).toBe('1')
    expect(successCallback).toHaveBeenCalledTimes(1)
  })

  test('does not persist the preference when the checkbox is left unchecked', async () => {
    const successCallback = jest.fn()
    render(<UnmodifiedFuzzyMatchModal successCallback={successCallback} />)

    await userEvent.click(screen.getByText('Confirm anyway'))

    expect(
      localStorage.getItem(HIDE_UNMODIFIED_FUZZY_MATCH_MODAL_STORAGE),
    ).toBeNull()
  })

  test('canceling calls onCloseModal and cancelCallback without persisting the checkbox', async () => {
    const cancelCallback = jest.fn()
    render(<UnmodifiedFuzzyMatchModal cancelCallback={cancelCallback} />)

    await userEvent.click(screen.getByText('Cancel'))

    expect(
      localStorage.getItem(HIDE_UNMODIFIED_FUZZY_MATCH_MODAL_STORAGE),
    ).toBeNull()
    expect(cancelCallback).toHaveBeenCalledTimes(1)
    expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
  })

  test('works without optional callbacks provided', async () => {
    render(<UnmodifiedFuzzyMatchModal />)

    await userEvent.click(screen.getByText('Confirm anyway'))

    expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
  })
})
