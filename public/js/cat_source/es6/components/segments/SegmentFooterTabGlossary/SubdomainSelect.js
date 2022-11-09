import React, {useContext} from 'react'
import {Select} from '../../common/Select'
import {TabGlossaryContext} from './TabGlossaryContext'

export const SubdomainSelect = () => {
  const {subdomains, setSubdomains, selectsActive, setSelectsActive} =
    useContext(TabGlossaryContext)

  const updateSelectActive = (key, value) =>
    setSelectsActive((prevState) => ({...prevState, [key]: value}))

  return (
    <Select
      className="glossary-select domain-select"
      name="glossary-term-subdomain"
      label="Subdomain"
      placeholder="No subdomain"
      showSearchBar
      searchPlaceholder="Find a subdomain"
      options={subdomains}
      activeOption={selectsActive.subdomain}
      checkSpaceToReverse={false}
      isDisabled={!selectsActive.domain}
      onSelect={(option) => {
        if (option) {
          updateSelectActive('subdomain', option)
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
          selectsActive.subdomain && {
            beforeRow: (
              <button
                className="button-create-option"
                onClick={() => {
                  updateSelectActive('subdomain', undefined)
                  onClose()
                }}
              >
                Deselect subdomain
              </button>
            ),
          }),
        // override row content
        row: <div className="domain-option">{name}</div>,
        // insert button after last row
        ...(index === optionsLength - 1 &&
          queryFilter.trim() &&
          !subdomains.find(({name}) => name === queryFilter) && {
            afterRow: (
              <button
                className="button-create-option"
                onClick={() => {
                  setSubdomains((prevState) => [
                    ...prevState,
                    {
                      name: queryFilter,
                      id: (prevState.length + 1).toString(),
                    },
                  ])
                  resetQueryFilter()
                }}
              >
                + Create a subdomain <b>{queryFilter}</b>
              </button>
            ),
          }),
      })}
    </Select>
  )
}
