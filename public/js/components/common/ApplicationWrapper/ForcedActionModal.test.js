import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {FORCE_ACTIONS, ForcedActionModal} from './ForcedActionModal'

describe('ForcedActionModal', () => {
  afterEach(() => jest.restoreAllMocks())

  it('renders the disconnect message when action is DISCONNECT', () => {
    render(<ForcedActionModal action={FORCE_ACTIONS.DISCONNECT} />)
    expect(screen.getByText('Please Sign in again')).toBeInTheDocument()
    expect(screen.getByText('Reload')).toBeInTheDocument()
  })

  it('renders the reload message by default', () => {
    render(<ForcedActionModal />)
    expect(screen.getByText('Update Required')).toBeInTheDocument()
    expect(screen.getByText('Refresh page')).toBeInTheDocument()
  })

  it('does not throw when the disconnect reload button is clicked', () => {
    jest.spyOn(console, 'error').mockImplementation(() => {})
    render(<ForcedActionModal action={FORCE_ACTIONS.DISCONNECT} />)
    expect(() => fireEvent.click(screen.getByText('Reload'))).not.toThrow()
  })

  it('does not throw when the refresh button is clicked', () => {
    jest.spyOn(console, 'error').mockImplementation(() => {})
    render(<ForcedActionModal action={FORCE_ACTIONS.RELOAD} />)
    expect(() =>
      fireEvent.click(screen.getByText('Refresh page')),
    ).not.toThrow()
  })
})
