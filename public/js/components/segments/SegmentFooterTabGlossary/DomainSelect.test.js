import React, {useState} from 'react'
import {render, screen, fireEvent, waitFor} from '@testing-library/react'
import {DomainSelect} from './DomainSelect'
import {TabGlossaryContext} from './TabGlossaryContext'

const initialDomains = [
  {id: '0', name: 'Legal', subdomains: ['Contracts']},
  {id: '1', name: 'Medical', subdomains: []},
]

const Harness = ({disabled, initialSelectsActive = {}} = {}) => {
  const [domains, setDomains] = useState(initialDomains)
  const [selectsActive, setSelectsActive] = useState({
    keys: [],
    domain: undefined,
    subdomain: undefined,
    ...initialSelectsActive,
  })

  return (
    <TabGlossaryContext.Provider
      value={{domains, setDomains, selectsActive, setSelectsActive}}
    >
      <DomainSelect disabled={disabled} />
      <div data-testid="active-domain">{selectsActive.domain?.name ?? ''}</div>
      <div data-testid="domains-count">{domains.length}</div>
    </TabGlossaryContext.Provider>
  )
}

const openDropdown = () => fireEvent.focus(screen.getByPlaceholderText('No domain'))

describe('DomainSelect', () => {
  test('renders label and placeholder', () => {
    render(<Harness />)
    expect(screen.getByText('Domain')).toBeInTheDocument()
    expect(screen.getByPlaceholderText('No domain')).toBeInTheDocument()
  })

  test('selecting an existing domain updates selectsActive', () => {
    render(<Harness />)
    openDropdown()
    fireEvent.click(screen.getByText('Legal'))
    expect(screen.getByTestId('active-domain')).toHaveTextContent('Legal')
  })

  test('typing an unknown domain shows the create button and adds it on click', async () => {
    render(<Harness />)
    openDropdown()
    fireEvent.change(screen.getByPlaceholderText('Find a domain'), {
      target: {value: 'Finance'},
    })
    fireEvent.click(screen.getByText((content, element) =>
      element?.className === 'button-create-option' &&
      element.textContent.includes('Finance'),
    ))

    expect(screen.getByTestId('domains-count')).toHaveTextContent('3')
    await waitFor(() =>
      expect(screen.getByTestId('active-domain')).toHaveTextContent('Finance'),
    )
  })

  test('pressing Enter with an unknown domain query creates it', async () => {
    render(<Harness />)
    openDropdown()
    const searchInput = screen.getByPlaceholderText('Find a domain')
    fireEvent.change(searchInput, {target: {value: 'Engineering'}})
    fireEvent.keyDown(searchInput, {key: 'Enter'})

    expect(screen.getByTestId('domains-count')).toHaveTextContent('3')
    await waitFor(() =>
      expect(screen.getByTestId('active-domain')).toHaveTextContent(
        'Engineering',
      ),
    )
  })

  test('shows deselect button when a domain is active and clears it', () => {
    render(<Harness initialSelectsActive={{domain: initialDomains[0]}} />)
    openDropdown()
    fireEvent.click(screen.getByText('Deselect domain'))
    expect(screen.getByTestId('active-domain')).toHaveTextContent('')
  })

  test('does not open the dropdown when disabled', () => {
    render(<Harness disabled />)
    openDropdown()
    expect(screen.queryByPlaceholderText('Find a domain')).not.toBeInTheDocument()
  })
})
