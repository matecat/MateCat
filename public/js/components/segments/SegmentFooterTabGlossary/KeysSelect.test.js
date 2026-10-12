import React, {useState} from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {KeysSelect} from './KeysSelect'
import {TabGlossaryContext} from './TabGlossaryContext'

jest.mock('../../../actions/CatToolActions', () => ({
  openSettingsPanel: jest.fn(),
}))

jest.mock('../../../utils/segmentUtils', () => ({
  setSelectedKeysGlossary: jest.fn(),
}))

const key1 = {id: '1', name: 'Key One'}
const key2 = {id: '2', name: 'Key Two'}

const Harness = ({keys = [], modifyElement, onToggleOption} = {}) => {
  const [selectsActive, setSelectsActive] = useState({keys: []})

  return (
    <TabGlossaryContext.Provider
      value={{keys, selectsActive, setSelectsActive, modifyElement}}
    >
      <KeysSelect onToggleOption={onToggleOption} />
      <div data-testid="active-keys">
        {selectsActive.keys.map(({name}) => name).join(',')}
      </div>
    </TabGlossaryContext.Provider>
  )
}

const openDropdown = () =>
  fireEvent.focus(screen.getByPlaceholderText('Select a termbase'))

describe('KeysSelect', () => {
  test('renders label', () => {
    render(<Harness keys={[key1, key2]} />)
    expect(screen.getByText('Termbase*')).toBeInTheDocument()
  })

  test('when there are keys, toggling an option updates selectsActive and persists to storage', () => {
    const onToggleOption = jest.fn()
    render(<Harness keys={[key1, key2]} onToggleOption={onToggleOption} />)
    openDropdown()
    fireEvent.click(screen.getByText('Key One'))

    expect(screen.getByTestId('active-keys')).toHaveTextContent('Key One')
    expect(
      require('../../../utils/segmentUtils').setSelectedKeysGlossary,
    ).toHaveBeenCalledWith([key1])
    expect(onToggleOption).toHaveBeenCalledWith(key1)
  })

  test('toggling an already-selected key removes it', () => {
    render(<Harness keys={[key1, key2]} />)
    openDropdown()
    const getOption = () =>
      screen
        .getAllByText('Key One')
        .find((element) => element.closest('li'))
        .closest('li')

    fireEvent.click(getOption())
    expect(screen.getByTestId('active-keys')).toHaveTextContent('Key One')

    fireEvent.click(getOption())
    expect(screen.getByTestId('active-keys')).toHaveTextContent('')
  })

  test('when there are no keys, shows a create-termbase-key option', () => {
    render(<Harness keys={[]} />)
    openDropdown()
    const createButton = screen.getByText('+ Create termbase key')
    expect(createButton).toBeInTheDocument()

    fireEvent.click(createButton)

    expect(
      require('../../../actions/CatToolActions').openSettingsPanel,
    ).toHaveBeenCalled()
  })

  test('is disabled while modifying an existing element', () => {
    render(<Harness keys={[key1, key2]} modifyElement={{term_id: 1}} />)
    openDropdown()
    expect(screen.queryByText('Key One')).not.toBeInTheDocument()
  })
})
