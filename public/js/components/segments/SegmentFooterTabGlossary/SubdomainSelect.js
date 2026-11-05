import React, {useContext, useRef, useState} from 'react'
import {Select} from '../../common/Select'
import {TabGlossaryContext} from './TabGlossaryContext'
import IconAdd from '../../../../img/icons/IconAdd'
import TEXT_UTILS from '../../../utils/textUtils'
import Tooltip, {TOOLTIP_POSITION} from '../../common/Tooltip'

export const SubdomainSelect = ({disabled = false}) => {
  const {subdomains, setSubdomains, selectsActive, setSelectsActive} =
    useContext(TabGlossaryContext)

  const [isCreateItemTooltipVisible, setIsCreateItemTooltipVisible] =
    useState(false)

  const ref = useRef()
  const createButtonRef = useRef()

  const updateSelectActive = (key, value) =>
    setSelectsActive((prevState) => ({...prevState, [key]: value}))

  return (
    <div ref={ref}>
      <Select
        className="glossary-select domain-select"
        dropdownClassName="glossary-domain-subdomain-dropdown"
        isPortalDropdown={true}
        name="glossary-term-subdomain"
        label="Subdomain"
        placeholder="No subdomain"
        showSearchBar
        searchPlaceholder="Find a subdomain"

        options={subdomains}
        activeOption={selectsActive.subdomain}
        checkSpaceToReverse={false}
        isDisabled={!selectsActive.domain || disabled}
        onSelect={(option) => {
          if (option) {
            updateSelectActive('subdomain', option)
          }
        }}
        showResetButton={true}
        resetFunction={() => updateSelectActive('subdomain', undefined)}
      >
        {({index, queryFilter, resetQueryFilter, onClose}) => {
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

          const createButtonElement = (
            <div ref={createButtonRef} className="button-create-option-content">
              <IconAdd size={18} />
              <span>
                Create subdomain &quot;<b>{queryFilter}</b>&quot;
              </span>
            </div>
          )

          return {
            ...(index === 0 && {
              beforeRow: (
                <>
                  {!queryFilter && !subdomains.length && (
                    <span className="glossary-domain-subdomain-no-records">
                      No subdomain available.
                      <br />
                      Start typing to create one.
                    </span>
                  )}

                  {queryFilter.trim() &&
                    !subdomains.find(({name}) => name === queryFilter) && (
                      <li
                        className="dropdown__option button-create-option"
                        onClick={createSubdomainFn}
                        onMouseEnter={() =>
                          setIsCreateItemTooltipVisible(
                            TEXT_UTILS.isContentTextEllipsis(
                              createButtonRef.current.children[1],
                            ),
                          )
                        }
                      >
                        {isCreateItemTooltipVisible ? (
                          <Tooltip
                            content={queryFilter}
                            position={TOOLTIP_POSITION.LEFT}
                          >
                            {createButtonElement}
                          </Tooltip>
                        ) : (
                          createButtonElement
                        )}
                      </li>
                    )}
                </>
              ),
            }),
          }
        }}
      </Select>
    </div>
  )
}
