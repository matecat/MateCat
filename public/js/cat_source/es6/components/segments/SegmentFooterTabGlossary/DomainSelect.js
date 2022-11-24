import React, {useContext, useEffect, useRef, useState} from 'react'
import {Select} from '../../common/Select'
import {TabGlossaryContext} from './TabGlossaryContext'

export const DomainSelect = () => {
  const {domains, setDomains, selectsActive, setSelectsActive} =
    useContext(TabGlossaryContext)

  const ref = useRef()
  const createDomainFnRef = useRef()

  useEffect(() => {
    const {current} = ref

    const onKeyDown = (e) => {
      if (e.key === 'Enter') {
        createDomainFnRef?.current?.()
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
        {({name, index, queryFilter, resetQueryFilter, onClose}) => {
          const createDomainFn =
            queryFilter.trim() &&
            !domains.find(({name}) => name === queryFilter)
              ? () => {
                  const newEntry = {
                    name: queryFilter,
                    id: domains.length.toString(),
                  }

                  setDomains((prevState) => [...prevState, newEntry])
                  setTimeout(() => updateSelectActive('domain', newEntry), 100)
                  resetQueryFilter()
                  onClose()
                }
              : () => false
          createDomainFnRef.current = createDomainFn

          return {
            ...(index === 0 && {
              beforeRow: (
                <>
                  {queryFilter.trim() &&
                    !domains.find(({name}) => name === queryFilter) && (
                      <button
                        className="button-create-option"
                        onClick={createDomainFn}
                      >
                        + Create domain <b>{queryFilter}</b>
                      </button>
                    )}
                  {!queryFilter && selectsActive.domain && (
                    <button
                      className="button-create-option"
                      onClick={() => {
                        updateSelectActive('domain', undefined)
                        onClose()
                      }}
                    >
                      Deselect domain
                    </button>
                  )}
                </>
              ),
            }),
            // override row content
            row: <div className="domain-option">{name}</div>,
          }
        }}
      </Select>
    </div>
  )
}
