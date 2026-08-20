import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {GlossaryItem} from './GlossaryItem'
import {TabGlossaryContext} from './TabGlossaryContext'
import '../../../extensions/extensionManifest'
import {
  resetCapabilities,
  setCapability,
} from '../../../extensions/capabilities'
import {GLOSSARY_EDIT} from '../../../extensions/capabilityNames'

const baseItem = {
  term_id: '5',
  metadata: {
    definition: 'A legal term',
    domain: 'Legal',
    subdomain: 'Contracts',
    key: 'key-1',
    key_name: 'My Termbase',
    last_update_date: '2024-01-01',
  },
  source: {term: 'Source term', note: '', sentence: ''},
  target: {term: 'Target term', note: '', sentence: ''},
}

const renderItem = (props = {}, contextValue = {}) =>
  render(
    <TabGlossaryContext.Provider value={{isActive: false, ...contextValue}}>
      <GlossaryItem
        item={baseItem}
        modifyElement={jest.fn()}
        deleteElement={jest.fn()}
        highlight={false}
        {...props}
      />
    </TabGlossaryContext.Provider>,
  )

beforeAll(() => {
  global.config = Object.assign(global.config ?? {}, {
    isSourceRTL: false,
    isTargetRTL: false,
  })
})

describe('GlossaryItem', () => {
  test('renders source and target terms plus definition and badges', () => {
    renderItem()
    expect(screen.getByText('Source term')).toBeInTheDocument()
    expect(screen.getByText('Target term')).toBeInTheDocument()
    expect(screen.getByText('A legal term')).toBeInTheDocument()
    expect(screen.getByText('Legal')).toBeInTheDocument()
    expect(screen.getByText('Contracts')).toBeInTheDocument()
    expect(screen.getByText('My Termbase')).toBeInTheDocument()
  })

  test('falls back to "No name (key)" when key_name is missing', () => {
    renderItem({
      item: {
        ...baseItem,
        metadata: {...baseItem.metadata, key_name: null},
      },
    })
    expect(screen.getByText('No name (key-1)')).toBeInTheDocument()
  })

  test('hides the definition badge when there is no definition', () => {
    renderItem({
      item: {...baseItem, metadata: {...baseItem.metadata, definition: ''}},
    })
    expect(
      document.querySelector('.glossary_definition--hidden'),
    ).toBeInTheDocument()
  })

  test('calls modifyElement when enabled to modify and clicked', () => {
    const modifyElement = jest.fn()
    renderItem({modifyElement, isEnabledToModify: true})
    fireEvent.click(document.querySelector('.glossary_item-actions div'))
    expect(modifyElement).toHaveBeenCalled()
  })

  test('does not call modifyElement when not enabled to modify', () => {
    const modifyElement = jest.fn()
    renderItem({modifyElement, isEnabledToModify: false})
    fireEvent.click(document.querySelector('.glossary_item-actions div'))
    expect(modifyElement).not.toHaveBeenCalled()
  })

  test('shows a lock with a blacklist message when isBlacklist and not editable', () => {
    renderItem({isEnabledToModify: false, isBlacklist: true})
    expect(
      screen.getByLabelText('Forbidden words can only be edited offline'),
    ).toBeInTheDocument()
    expect(screen.getByText('Forbidden term')).toBeInTheDocument()
  })

  test('shows a lock with an ownership message when isBlacklist is false and not editable', () => {
    renderItem({isEnabledToModify: false, isBlacklist: false})
    expect(
      screen.getByLabelText('You can only edit entries from keys that you own'),
    ).toBeInTheDocument()
  })

  test('calls deleteElement when enabled to modify and delete is clicked', () => {
    const deleteElement = jest.fn()
    renderItem({deleteElement, isEnabledToModify: true})
    const actionDivs = document.querySelectorAll('.glossary_item-actions div')
    fireEvent.click(actionDivs[actionDivs.length - 1])
    expect(deleteElement).toHaveBeenCalled()
  })

  test('shows a loader instead of the delete icon while deleting', () => {
    renderItem({isEnabledToModify: true, isStatusDeleting: true})
    expect(document.querySelector('.loader.loader_on')).toBeInTheDocument()
  })

  test('clicking the target label triggers onClick with the target term', () => {
    const onClick = jest.fn()
    renderItem({onClick})
    fireEvent.mouseDown(
      screen.getByLabelText('Click to insert the term in the target segment'),
    )
    expect(onClick).toHaveBeenCalledWith('Target term')
  })

  test('renders notes and tooltips when present', () => {
    renderItem({
      item: {
        ...baseItem,
        source: {...baseItem.source, note: 'source note', sentence: 'src ex'},
        target: {...baseItem.target, note: 'target note', sentence: 'tgt ex'},
      },
    })
    expect(screen.getAllByText('source note').length).toBeGreaterThan(0)
    expect(screen.getAllByText('target note').length).toBeGreaterThan(0)
  })

  test('resize listener is attached and cleaned up when isActive', () => {
    const addSpy = jest.spyOn(window, 'addEventListener')
    const removeSpy = jest.spyOn(window, 'removeEventListener')
    const {unmount} = renderItem(
      {
        item: {
          ...baseItem,
          source: {...baseItem.source, note: 'source note'},
        },
      },
      {isActive: true},
    )
    expect(addSpy).toHaveBeenCalledWith('resize', expect.any(Function))
    unmount()
    expect(removeSpy).toHaveBeenCalledWith('resize', expect.any(Function))
    addSpy.mockRestore()
    removeSpy.mockRestore()
  })

  describe('when the deployment may not edit the glossary', () => {
    afterEach(() => {
      resetCapabilities()
    })

    test('the edit and delete controls are not rendered at all', () => {
      setCapability(GLOSSARY_EDIT, false)
      renderItem({isEnabledToModify: true})
      expect(document.querySelector('.glossary_item-actions')).toBeNull()
    })

    test('the term itself is still shown', () => {
      setCapability(GLOSSARY_EDIT, false)
      renderItem()
      expect(screen.getByText('Source term')).toBeInTheDocument()
      expect(screen.getByText('Target term')).toBeInTheDocument()
    })

    test('the controls come back once the capability is restored', () => {
      setCapability(GLOSSARY_EDIT, false)
      const {unmount} = renderItem({isEnabledToModify: true})
      unmount()

      resetCapabilities()
      renderItem({isEnabledToModify: true})
      expect(
        document.querySelector('.glossary_item-actions'),
      ).toBeInTheDocument()
    })
  })
})
