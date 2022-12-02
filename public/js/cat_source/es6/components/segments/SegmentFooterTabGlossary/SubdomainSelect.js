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
        {({name, index, queryFilter, resetQueryFilter, onClose}) => {
          const createSubdomainFn =
            queryFilter.trim() &&
            !subdomains.find(({name}) => name === queryFilter)
              ? () => {
                  const newEntry = {
                    name: queryFilter,
                    id: subdomains.length.toString(),
                  }

                  setSubdomains((prevState) => [...prevState, newEntry])
                  setTimeout(
                    () => updateSelectActive('subdomain', newEntry),
                    100,
                  )
                  resetQueryFilter()
                  onClose()
                }
              : () => false
          createSubdomainFnRef.current = createSubdomainFn

          return {
            ...(index === 0 && {
              beforeRow: (
                <>
                  {queryFilter.trim() &&
                    !subdomains.find(({name}) => name === queryFilter) && (
                      <button
                        className="button-create-option"
                        onClick={createSubdomainFn}
                      >
                        + Create subdomain <b>{queryFilter}</b>
                      </button>
                    )}
                  {!queryFilter && selectsActive.subdomain && (
                    <button
                      className="button-create-option"
                      onClick={() => {
                        updateSelectActive('subdomain', undefined)
                        onClose()
                      }}
                    >
                      Deselect subdomain
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
