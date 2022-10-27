import React, {useContext} from 'react'
import {MoreIcon, TERM_FORM_FIELDS} from './SegmentFooterTabGlossary'
import {TabGlossaryContext} from './TabGlossaryContext'
import {Select} from '../../common/Select'
import SegmentActions from '../../../actions/SegmentActions'
import CatToolActions from '../../../actions/CatToolActions'

const TermForm = () => {
  const {
    isLoading,
    keys,
    domains,
    setDomains,
    subdomains,
    setSubdomains,
    termForm,
    setTermForm,
    selectsActive,
    setSelectsActive,
    modifyElement,
    showMore,
    setShowMore,
    resetForm,
    domainsResponse,
    getRequestPayloadTemplate,
    setIsLoading,
    segment,
  } = useContext(TabGlossaryContext)

  const {
    DEFINITION,
    ORIGINAL_TERM,
    ORIGINAL_DESCRIPTION,
    ORIGINAL_EXAMPLE,
    TRANSLATED_TERM,
    TRANSLATED_DESCRIPTION,
    TRANSLATED_EXAMPLE,
  } = TERM_FORM_FIELDS

  const isEmptyKeys = !keys.length

  const updateSelectActive = (key, value) =>
    setSelectsActive((prevState) => ({...prevState, [key]: value}))

  const updateTermForm = (key, value) =>
    setTermForm((prevState) => ({...prevState, [key]: value}))

  const onSubmitAddOrUpdateTerm = () => {
    // check mandatory fields
    const {originalTerm, translatedTerm} = termForm
    const {keys, domain, subdomain} = selectsActive
    if (
      !originalTerm ||
      !translatedTerm ||
      !keys.length ||
      !domain ||
      !subdomain
    )
      return

    setIsLoading(true)
    if (modifyElement) {
      SegmentActions.updateGlossaryItem(getRequestPayloadTemplate())
    } else {
      SegmentActions.addGlossaryItem(getRequestPayloadTemplate())
      CatToolActions.setHaveKeysGlossary(true)

      const updatedDomains = keys.reduce(
        (acc, {key}) => {
          const aggregator = [
            ...(acc[key]?.length ? acc[key] : []),
            ...(!acc[key]?.length ||
            !acc[key]?.find((item) => item.domain === domain.name)
              ? [{domain: domain.name, subdomains: []}]
              : []),
          ]
          return {
            ...acc,
            [key]: aggregator.map((item) =>
              item.domain === domain.name
                ? {
                    domain: domain.name,
                    subdomains: [
                      ...new Set([...item.subdomains, subdomain.name]),
                    ],
                  }
                : item,
            ),
          }
        },
        {...domainsResponse},
      )

      // dispatch actions set domains
      CatToolActions.setDomains({
        sid: segment.sid,
        entries: {...domainsResponse, ...updatedDomains},
      })
    }
  }

  return (
    <div className={'glossary_add-container'}>
      <div className={'glossary-form-line'}>
        <div className={'input-with-label__wrapper'}>
          <label>Definition</label>
          <input
            name="glossary-term-definition"
            value={termForm[DEFINITION]}
            onChange={(event) => updateTermForm(DEFINITION, event.target.value)}
          />
        </div>
        <div className={'glossary-tm-container'}>
          <Select
            className="glossary-select"
            name="glossary-term-tm"
            label="Glossary*"
            placeholder="Select a glossary"
            multipleSelect="dropdown"
            showSearchBar={!isEmptyKeys}
            searchPlaceholder="Find a glossary"
            options={
              keys.length ? keys : [{id: '0', name: '+ Create glossary key'}]
            }
            activeOptions={selectsActive.keys}
            checkSpaceToReverse={false}
            isDisabled={!!modifyElement}
            onToggleOption={(option) => {
              if (option) {
                const {keys: activeKeys} = selectsActive
                if (activeKeys.some((item) => item.id === option.id)) {
                  updateSelectActive(
                    'keys',
                    activeKeys.filter((item) => item.id !== option.id),
                  )
                } else {
                  updateSelectActive('keys', activeKeys.concat([option]))
                }
              }
            }}
          >
            {({name}) => ({
              // customize row with button create glossary key
              ...(isEmptyKeys && {
                row: (
                  <button
                    className="button-create-glossary-key"
                    onClick={() => UI.openLanguageResourcesPanel('tm')}
                  >
                    {name}
                  </button>
                ),
                cancelHandleClick: true,
              }),
            })}
          </Select>

          <div className={'input-with-label__wrapper'}>
            <Select
              className="glossary-select domain-select"
              name="glossary-term-domain"
              label="Domain*"
              placeholder="Select a domain"
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
              }) => ({
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
                        + Create a domain name <b>{queryFilter}</b>
                      </button>
                    ),
                  }),
              })}
            </Select>
          </div>
          <div className={'input-with-label__wrapper'}>
            <Select
              className="glossary-select domain-select"
              name="glossary-term-subdomain"
              label="Subdomain*"
              placeholder="Select a subdomain"
              showSearchBar
              searchPlaceholder="Find a subdomain"
              options={subdomains}
              activeOption={selectsActive.subdomain}
              checkSpaceToReverse={false}
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
              }) => ({
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
                        + Create a subdomain name <b>{queryFilter}</b>
                      </button>
                    ),
                  }),
              })}
            </Select>
          </div>
        </div>
      </div>

      <div className={'glossary-form-line'}>
        <div className={'input-with-label__wrapper'}>
          <label>Original term*</label>
          <input
            name="glossary-term-original"
            value={termForm[ORIGINAL_TERM]}
            onChange={(event) =>
              updateTermForm(ORIGINAL_TERM, event.target.value)
            }
          />
        </div>
        <div className={'input-with-label__wrapper'}>
          <label>Translated term*</label>
          <input
            name="glossary-term-translated"
            value={termForm[TRANSLATED_TERM]}
            onChange={(event) =>
              updateTermForm(TRANSLATED_TERM, event.target.value)
            }
          />
        </div>
      </div>
      {showMore && (
        <div className={'glossary-form-line more-line'}>
          <div>
            <div className={'input-with-label__wrapper'}>
              <label>Description</label>
              <textarea
                className={'input-large'}
                name="glossary-term-description-source"
                value={termForm[ORIGINAL_DESCRIPTION]}
                onChange={(event) =>
                  updateTermForm(ORIGINAL_DESCRIPTION, event.target.value)
                }
              />
            </div>
            <div className={'input-with-label__wrapper'}>
              <label>Example phrase</label>
              <textarea
                className={'input-large'}
                name="glossary-term-example-source"
                value={termForm[ORIGINAL_EXAMPLE]}
                onChange={(event) =>
                  updateTermForm(ORIGINAL_EXAMPLE, event.target.value)
                }
              />
            </div>
          </div>
          <div>
            <div className={'input-with-label__wrapper'}>
              <label>Description</label>
              <textarea
                className={'input-large'}
                name="glossary-term-description-target"
                value={termForm[TRANSLATED_DESCRIPTION]}
                onChange={(event) =>
                  updateTermForm(TRANSLATED_DESCRIPTION, event.target.value)
                }
              />
            </div>
            <div className={'input-with-label__wrapper'}>
              <label>Example phrase</label>
              <textarea
                className={'input-large'}
                name="glossary-term-example-target"
                value={termForm[TRANSLATED_EXAMPLE]}
                onChange={(event) =>
                  updateTermForm(TRANSLATED_EXAMPLE, event.target.value)
                }
              />
            </div>
          </div>
        </div>
      )}
      <div className={'glossary_buttons-container'}>
        <div></div>
        <div
          className={`glossary-more ${!showMore ? 'show-less' : 'show-more'}`}
          onClick={() => setShowMore(!showMore)}
        >
          <MoreIcon />
          <span>{showMore ? 'Hide options' : 'More options'}</span>
        </div>
        <div className={'glossary_buttons'}>
          <button className={'glossary__button-cancel'} onClick={resetForm}>
            Cancel
          </button>
          <button
            className="glossary__button-add"
            onClick={onSubmitAddOrUpdateTerm}
            disabled={isLoading}
          >
            {modifyElement ? 'Update' : 'Add'}
          </button>
        </div>
      </div>
    </div>
  )
}

export default TermForm
