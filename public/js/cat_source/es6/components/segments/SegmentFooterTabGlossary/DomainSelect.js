import React, {useContext} from 'react'
import {Select} from '../../common/Select'
import {TabGlossaryContext} from './TabGlossaryContext'

export const DomainSelect = () => {
  const {domains, setDomains, selectsActive, setSelectsActive} =
    useContext(TabGlossaryContext)

  const updateSelectActive = (key, value) =>
    setSelectsActive((prevState) => ({...prevState, [key]: value}))

  return (
    <Select
      className="glossary-select domain-select"
      name="glossary-term-domain"
      label="Domain"
      placeholder="No domain"
      showSearchBar
      searchPlaceholder="Find a domain"
      options={domains}
      activeOption={selectsActive.domain}
      checkSpaceToReverse={false}
      onSelect={(option) => {
        if (option) {
          updateSelectActive('domain', option)
        }
      }}
    >
      {({
        name,
        index,
        optionsLength,
        queryFilter,
        resetQueryFilter,
        onClose,
      }) => ({
        ...(index === 0 &&
          selectsActive.domain && {
            beforeRow: (
              <button
                className="button-create-option"
                onClick={() => {
                  updateSelectActive('domain', undefined)
                  onClose()
                }}
              >
                Deselect domain
              </button>
            ),
          }),
        // override row content
        row: <div className="domain-option">{name}</div>,
        // insert button after last row
        ...(index === optionsLength - 1 &&
          queryFilter.trim() &&
          !domains.find(({name}) => name === queryFilter) && {
            afterRow: (
              <button
                className="button-create-option"
                onClick={() => {
                  setDomains((prevState) => [
                    ...prevState,
                    {
                      name: queryFilter,
                      id: (prevState.length + 1).toString(),
                    },
                  ])
                  resetQueryFilter()
                }}
              >
                + Create a domain <b>{queryFilter}</b>
              </button>
            ),
          }),
      })}
    </Select>
  )
}
