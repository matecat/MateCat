import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {MandatoryIssues} from './MandatoryIssues'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'

jest.mock('../../../common/Select', () => ({
  Select: ({onSelect, activeOption, options}) => (
    <div>
      <div data-testid="active-option">{activeOption?.name ?? ''}</div>
      {options?.map((opt) => (
        <button
          key={opt.id}
          data-testid={`option-${opt.id}`}
          onClick={() => onSelect(opt)}
        >
          {opt.name}
        </button>
      ))}
    </div>
  ),
}))

const createProjectValue = {SELECT_HEIGHT: 200}

const renderMandatoryIssues = (
  mandatoryIssues,
  modifyingCurrentTemplate = jest.fn(),
) =>
  render(
    <CreateProjectContext.Provider value={createProjectValue}>
      <SettingsPanelContext.Provider
        value={{
          currentProjectTemplate: {mandatoryIssues},
          modifyingCurrentTemplate,
        }}
      >
        <MandatoryIssues />
      </SettingsPanelContext.Provider>
    </CreateProjectContext.Provider>,
  )

describe('MandatoryIssues', () => {
  test('shows "R1 + R2" as active option when mandatoryIssues is ["r1", "r2"]', () => {
    renderMandatoryIssues(['r1', 'r2'])
    expect(screen.getByTestId('active-option').textContent).toBe('R1 + R2')
  })

  test('shows "None" as active option when mandatoryIssues is empty', () => {
    renderMandatoryIssues([])
    expect(screen.getByTestId('active-option').textContent).toBe('None')
  })

  test('shows "Only R1" as active option when mandatoryIssues is ["r1"]', () => {
    renderMandatoryIssues(['r1'])
    expect(screen.getByTestId('active-option').textContent).toBe('Only R1')
  })

  test('shows "Only R2" as active option when mandatoryIssues is ["r2"]', () => {
    renderMandatoryIssues(['r2'])
    expect(screen.getByTestId('active-option').textContent).toBe('Only R2')
  })

  test('selecting "r1" passes updater that sets mandatoryIssues to ["r1"]', () => {
    const modifyingCurrentTemplate = jest.fn()
    renderMandatoryIssues(['r1', 'r2'], modifyingCurrentTemplate)

    fireEvent.click(screen.getByTestId('option-r1'))

    expect(modifyingCurrentTemplate).toHaveBeenCalledTimes(1)
    const updater = modifyingCurrentTemplate.mock.calls[0][0]
    expect(updater({mandatoryIssues: ['r1', 'r2']}).mandatoryIssues).toEqual([
      'r1',
    ])
  })

  test('selecting "r1,r2" passes updater that sets mandatoryIssues to ["r1", "r2"]', () => {
    const modifyingCurrentTemplate = jest.fn()
    renderMandatoryIssues(['r1'], modifyingCurrentTemplate)

    fireEvent.click(screen.getByTestId('option-r1,r2'))

    expect(modifyingCurrentTemplate).toHaveBeenCalledTimes(1)
    const updater = modifyingCurrentTemplate.mock.calls[0][0]
    expect(updater({mandatoryIssues: ['r1']}).mandatoryIssues).toEqual([
      'r1',
      'r2',
    ])
  })

  test('selecting "none" passes updater that sets mandatoryIssues to []', () => {
    const modifyingCurrentTemplate = jest.fn()
    renderMandatoryIssues(['r1', 'r2'], modifyingCurrentTemplate)

    fireEvent.click(screen.getByTestId('option-none'))

    expect(modifyingCurrentTemplate).toHaveBeenCalledTimes(1)
    const updater = modifyingCurrentTemplate.mock.calls[0][0]
    expect(updater({mandatoryIssues: ['r1', 'r2']}).mandatoryIssues).toEqual([])
  })

  test('updater preserves other template fields', () => {
    const modifyingCurrentTemplate = jest.fn()
    renderMandatoryIssues(['r1'], modifyingCurrentTemplate)

    fireEvent.click(screen.getByTestId('option-r2'))

    const updater = modifyingCurrentTemplate.mock.calls[0][0]
    const result = updater({mandatoryIssues: ['r1'], otherField: 'preserved'})
    expect(result.otherField).toBe('preserved')
    expect(result.mandatoryIssues).toEqual(['r2'])
  })
})
