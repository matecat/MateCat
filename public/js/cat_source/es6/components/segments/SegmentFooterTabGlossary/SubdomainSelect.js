import React, {useContext, useEffect, useRef} from 'react'
import {Select} from '../../common/Select'
import {TabGlossaryContext} from './TabGlossaryContext'

export const SubdomainSelect = () => {
  const {subdomains, setSubdomains, selectsActive, setSelectsActive} =
    useContext(TabGlossaryContext)

  const ref = useRef()
  const createSubdomainFnRef = useRef()

  useEffect(() => {
    const {current} = ref

    const onKeyDown = (e) => {
      if (e.key === 'Enter') {
        createSubdomainFnRef?.current?.()
        e.stopPropagation()
      }
    }

    current.addEventListener('keydown', onKeyDown)

    return () => current.removeEventListener('keydown', onKeyDown)
  }, [])

  const updateSelectActive = (key, value) =>
    setSelectsActive((prevState) => ({...prevState, [key]: value}))

  return (
    <div ref={ref}>
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
        }) => {
          const createSubdomainFn =
            queryFilter.trim() &&
            !subdomains.find(({name}) => name === queryFilter)
              ? () => {
                  setSubdomains((prevState) => [
                    ...prevState,
                    {
                      name: queryFilter,
                      id: (prevState.length + 1).toString(),
                    },
                  ])
                  resetQueryFilter()
                }
              : () => false
          createSubdomainFnRef.current = createSubdomainFn

          return {
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
                    onClick={createSubdomainFn}
                  >
                    + Create a subdomain <b>{queryFilter}</b>
                  </button>
                ),
              }),
          }
        }}
      </Select>
    </div>
  )
}
