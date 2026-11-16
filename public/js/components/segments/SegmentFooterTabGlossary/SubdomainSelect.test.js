import React, {useState} from 'react'
import {render, screen, fireEvent, waitFor} from '@testing-library/react'
import {SubdomainSelect} from './SubdomainSelect'
import {TabGlossaryContext} from './TabGlossaryContext'

const initialSubdomains = [
  {id: '0', name: 'Contracts'},
  {id: '1', name: 'Litigation'},
]

const Harness = ({initialSelectsActive = {}} = {}) => {
  const [subdomains, setSubdomains] = useState(initialSubdomains)
  const [selectsActive, setSelectsActive] = useState({
    keys: [],
    domain: {id: '0', name: 'Legal'},
    subdomain: undefined,
    ...initialSelectsActive,
  })

  return (
    <TabGlossaryContext.Provider
      value={{subdomains, setSubdomains, selectsActive, setSelectsActive}}
    >
      <SubdomainSelect />
      <div data-testid="active-subdomain">
        {selectsActive.subdomain?.name ?? ''}
      </div>
      <div data-testid="subdomains-count">{subdomains.length}</div>
    </TabGlossaryContext.Provider>
  )
}

const openDropdown = () =>
  fireEvent.focus(screen.getByPlaceholderText('No subdomain'))

describe('SubdomainSelect', () => {
  test('renders label and placeholder', () => {
    render(<Harness />)
    expect(screen.getByText('Subdomain')).toBeInTheDocument()
    expect(screen.getByPlaceholderText('No subdomain')).toBeInTheDocument()
  })

  test('selecting an existing subdomain updates selectsActive', () => {
    render(<Harness />)
    openDropdown()
    fireEvent.click(screen.getByText('Contracts'))
    expect(screen.getByTestId('active-subdomain')).toHaveTextContent(
      'Contracts',
    )
  })

  test('typing an unknown subdomain shows the create button and adds it on click', async () => {
    render(<Harness />)
    openDropdown()
    fireEvent.change(screen.getByPlaceholderText('Find a subdomain'), {
      target: {value: 'Appeals'},
    })
    fireEvent.click(screen.getByText('Appeals'))

    expect(screen.getByTestId('subdomains-count')).toHaveTextContent('3')
    await waitFor(() =>
      expect(screen.getByTestId('active-subdomain')).toHaveTextContent(
        'Appeals',
      ),
    )
  })

  test('clicking the reset button clears the active subdomain', () => {
    const {container} = render(
      <Harness initialSelectsActive={{subdomain: initialSubdomains[0]}} />,
    )
    // eslint-disable-next-line testing-library/no-container, testing-library/no-node-access -- reset icon carries no accessible role or testid
    fireEvent.click(container.querySelector('.icon-reset'))
    expect(screen.getByTestId('active-subdomain')).toHaveTextContent('')
  })

  test('is disabled when no domain is selected', () => {
    render(<Harness initialSelectsActive={{domain: undefined}} />)
    openDropdown()
    expect(
      screen.queryByPlaceholderText('Find a subdomain'),
    ).not.toBeInTheDocument()
  })
})
