import React, {useContext, useRef, useState} from 'react'
import {Select} from '../../common/Select'
import {TabGlossaryContext} from './TabGlossaryContext'
import IconAdd from '../../../../img/icons/IconAdd'
import Tooltip, {TOOLTIP_POSITION} from '../../common/Tooltip'
import TEXT_UTILS from '../../../utils/textUtils'

export const DomainSelect = ({disabled = false}) => {
  const {domains, setDomains, selectsActive, setSelectsActive} =
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
        isDisabled={disabled}
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
        showResetButton={true}
        resetFunction={() => updateSelectActive('domain', undefined)}
      >
        {({index, queryFilter, resetQueryFilter, onClose}) => {
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

          const createButtonElement = (
            <div ref={createButtonRef} className="button-create-option-content">
              <IconAdd size={18} />
              <span>
                Create domain &quot;<b>{queryFilter}</b>&quot;
              </span>
            </div>
          )

          return {
            ...(index === 0 && {
              beforeRow: (
                <>
                  {!queryFilter && !domains.length && (
                    <span className="glossary-domain-subdomain-no-records">
                      No domain available.
                      <br />
                      Start typing to create one.
                    </span>
                  )}
                  {queryFilter.trim() &&
                    !domains.find(({name}) => name === queryFilter) && (
                      <li
                        className="dropdown__option button-create-option"
                        onClick={createDomainFn}
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
