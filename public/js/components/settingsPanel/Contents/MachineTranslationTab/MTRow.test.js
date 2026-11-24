import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {MTRow} from './MTRow'
import {SettingsPanelContext} from '../../SettingsPanelContext'

const Wrapper = ({
  row,
  deleteMT = jest.fn(),
  onCheckboxClick = jest.fn(),
  currentProjectTemplate = {mt: {id: 1}},
}) => (
  <SettingsPanelContext.Provider value={{currentProjectTemplate}}>
    <MTRow row={row} deleteMT={deleteMT} onCheckboxClick={onCheckboxClick} />
  </SettingsPanelContext.Provider>
)

beforeEach(() => {
  global.config = {is_cattool: false}
})

describe('MTRow', () => {
  test('renders the engine name', () => {
    render(<Wrapper row={{id: 2, name: 'DeepL'}} />)

    expect(screen.getByText('DeepL')).toBeInTheDocument()
  })

  test('renders the checkbox unchecked when the row is not the active engine', () => {
    render(<Wrapper row={{id: 2, name: 'DeepL'}} />)

    expect(screen.getByTestId('checkbox-mt-active-DeepL')).not.toBeChecked()
  })

  test('renders the checkbox checked when the row is the active engine', () => {
    render(
      <Wrapper
        row={{id: 1, name: 'DeepL'}}
        currentProjectTemplate={{mt: {id: 1}}}
      />,
    )

    expect(screen.getByTestId('checkbox-mt-active-DeepL')).toBeChecked()
  })

  test('calls onCheckboxClick with the row when the checkbox is clicked', () => {
    const onCheckboxClick = jest.fn()
    const row = {id: 2, name: 'DeepL'}
    render(<Wrapper row={row} onCheckboxClick={onCheckboxClick} />)

    fireEvent.click(screen.getByTestId('checkbox-mt-active-DeepL'))

    expect(onCheckboxClick).toHaveBeenCalledWith(row)
  })

  test('disables the checkbox for the active engine inside the cattool', () => {
    global.config = {is_cattool: true}
    render(
      <Wrapper
        row={{id: 1, name: 'DeepL'}}
        currentProjectTemplate={{mt: {id: 1}}}
      />,
    )

    expect(screen.getByTestId('checkbox-mt-active-DeepL')).toBeDisabled()
  })

  test('renders the delete button when the row is not default outside the cattool', () => {
    render(<Wrapper row={{id: 2, name: 'DeepL', default: false}} />)

    expect(screen.getByTestId('delete-mt')).toBeInTheDocument()
  })

  test('calls deleteMT when the delete button is clicked', () => {
    const deleteMT = jest.fn()
    render(
      <Wrapper row={{id: 2, name: 'DeepL', default: false}} deleteMT={deleteMT} />,
    )

    fireEvent.click(screen.getByTestId('delete-mt'))

    expect(deleteMT).toHaveBeenCalled()
  })

  test('hides the delete button for the default engine', () => {
    render(<Wrapper row={{id: 2, name: 'DeepL', default: true}} />)

    expect(screen.queryByTestId('delete-mt')).not.toBeInTheDocument()
  })

  test('hides the delete button inside the cattool', () => {
    global.config = {is_cattool: true}
    render(<Wrapper row={{id: 2, name: 'DeepL', default: false}} />)

    expect(screen.queryByTestId('delete-mt')).not.toBeInTheDocument()
  })
})
