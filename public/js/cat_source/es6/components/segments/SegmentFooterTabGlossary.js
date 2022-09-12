import React, {useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import {Select} from './../common/Select'
import InfoIcon from '../../../../../img/icons/InfoIcon'
import {SegmentedControl} from '../common/SegmentedControl'
import {getTmKeysJob} from '../../api/getTmKeysJob/getTmKeysJob'

const TERM_FORM_FIELDS = {
  DEFINITION: 'definition',
  ORIGINAL_TERM: 'originalTerm',
  ORIGINAL_DESCRIPTION: 'originalDescription',
  ORIGINAL_EXAMPLE: 'originalExample',
  TRANSLATED_TERM: 'translatedTerm',
  TRANSLATED_DESCRIPTION: 'translatedDescription',
  TRANSLATED_EXAMPLE: 'translatedExample',
}

const initialState = {
  keys: [],
  domains: [
    {
      name: 'Uber',
      id: '1',
    },
    {
      name: 'Patreon',
      id: '2',
    },
    {
      name: 'Ebay',
      id: '3',
    },
  ],
  subdomains: [
    {
      name: 'Rider',
      id: '1',
    },
    {
      name: 'Eats',
      id: '2',
    },
  ],
  terms: [],
  termForm: Object.entries(TERM_FORM_FIELDS).reduce(
    (acc, [, value]) => ({...acc, [value]: ''}),
    {},
  ),
}

export const SegmentFooterTabGlossary = ({active_class, segment}) => {
  const [searchTerm, setSearchTerm] = useState('')
  const [searchTypes, setSearchTypes] = useState([
    {id: '0', name: 'Source', selected: true},
    {id: '1', name: 'Target'},
  ])
  const [showForm, setShowForm] = useState(false)
  const [showMore, setShowMore] = useState(false)
  const [keys, setKeys] = useState(initialState.keys)
  const [domains, setDomains] = useState(initialState.domains)
  const [subdomains, setSubdomains] = useState(initialState.subdomains)
  const [selectsActive, setSelectsActive] = useState({
    keys: [],
    domain: undefined,
    subdomain: undefined,
  })
  const [terms, setTerms] = useState(initialState.terms)
  const [modifyElement, setModifyElement] = useState()
  const [termForm, setTermForm] = useState(initialState.termForm)

  // get TM keys
  useEffect(() => {
    let cleaned = false

    getTmKeysJob().then(
      ({tm_keys: tmKeys}) =>
        !cleaned && setKeys(tmKeys.map((item) => ({...item, id: item.key}))),
    )

    return () => (cleaned = true)
  }, [])

  useEffect(() => {
    if (!segment?.glossary) return
    console.log('----> segment glossary', segment.glossary)
    setTerms(segment.glossary)
  }, [segment?.glossary])

  // set active keys, domain and subdomain
  useEffect(() => {
    const defaultKeys = keys.length ? [keys[0]] : []
    const defaultDomain = domains[0]
    const defaultSubdomain = subdomains[0]

    const {metadata} = modifyElement || {}

    setSelectsActive((prevState) => ({
      ...prevState,
      keys: keys.find(({id}) => id === metadata?.key)
        ? [keys.find(({id}) => id === metadata?.key)]
        : defaultKeys,
      domain:
        domains.find(({name}) => name === metadata?.domain) ?? defaultDomain,
      subdomain:
        subdomains.find(({name}) => name === metadata?.subdomain) ??
        defaultSubdomain,
    }))
  }, [modifyElement, keys, domains, subdomains])

  // prefill term form
  useEffect(() => {
    if (!modifyElement) {
      setTermForm(initialState.termForm)
      return
    }
    const {
      DEFINITION,
      ORIGINAL_TERM,
      ORIGINAL_DESCRIPTION,
      ORIGINAL_EXAMPLE,
      TRANSLATED_TERM,
      TRANSLATED_DESCRIPTION,
      TRANSLATED_EXAMPLE,
    } = TERM_FORM_FIELDS
    const {metadata, source, target} = modifyElement
    setTermForm({
      [DEFINITION]: metadata.definition,
      [ORIGINAL_TERM]: source.term,
      [ORIGINAL_DESCRIPTION]: source.note,
      [ORIGINAL_EXAMPLE]: source.sentence,
      [TRANSLATED_TERM]: target.term,
      [TRANSLATED_DESCRIPTION]: target.note,
      [TRANSLATED_EXAMPLE]: target.sentence,
    })
  }, [modifyElement])

  // execute search api
  const onSubmitSearch = () => {
    const searchingIn = searchTypes.find(({selected}) => selected).name
    const data = {
      sentence: searchTerm,
      id_client: config.id_client,
      id_job: config.id_job,
      password: config.password,
      source_language:
        searchingIn === 'Source' ? config.source_code : config.target_code,
      target_language:
        searchingIn === 'Source' ? config.target_code : config.source_code,
    }
    console.log(data)
  }

  const openAddTerm = () => {
    setModifyElement(undefined)
    setShowForm(true)
  }

  const modifyItem = (term) => {
    setShowMore(true)
    setModifyElement(term)
    setShowForm(true)
  }

  const updateSelectActive = (key, value) =>
    setSelectsActive((prevState) => ({...prevState, [key]: value}))

  const updateTermForm = (key, value) =>
    setTermForm((prevState) => ({...prevState, [key]: value}))

  const getFormBox = () => {
    const {
      DEFINITION,
      ORIGINAL_TERM,
      ORIGINAL_DESCRIPTION,
      ORIGINAL_EXAMPLE,
      TRANSLATED_TERM,
      TRANSLATED_DESCRIPTION,
      TRANSLATED_EXAMPLE,
    } = TERM_FORM_FIELDS
    return (
      <div className={'glossary_add-container'}>
        <div className={'glossary-form-line'}>
          <div className={'input-with-label__wrapper'}>
            <label>Definition</label>
            <input
              name="glossary-term-definition"
              value={termForm[DEFINITION]}
              onChange={(event) =>
                updateTermForm(DEFINITION, event.target.value)
              }
            />
          </div>
          <div className={'glossary-tm-container'}>
            <Select
              className="glossary-select"
              name="glossary-term-tm"
              label="Glossary"
              placeholder="Select a glossary"
              showSearchBar
              searchPlaceholder="Find a glossary"
              multipleSelect="dropdown"
              options={keys}
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
              optionTemplate={({name, isActive}) => (
                <div className="glossary-option">
                  <input
                    type="checkbox"
                    name={name}
                    checked={isActive}
                    onChange={() => false}
                  />
                  <label htmlFor={`${name}`}>{name}</label>
                </div>
              )}
            />

            <div className={'input-with-label__wrapper'}>
              <Select
                className="glossary-select domain-select"
                name="glossary-term-domain"
                label="Domain"
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
                optionTemplate={({name}) => (
                  <div className="domain-option">{name}</div>
                )}
                onRenderOption={({
                  index,
                  optionsLength,
                  queryFilter,
                  resetQueryFilter,
                }) =>
                  index === optionsLength - 1 &&
                  queryFilter.trim() && (
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
                  )
                }
              />
            </div>
            <div className={'input-with-label__wrapper'}>
              <Select
                className="glossary-select domain-select"
                name="glossary-term-subdomain"
                label="Subdomain"
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
                optionTemplate={({name}) => (
                  <div className="domain-option">{name}</div>
                )}
                onRenderOption={({
                  index,
                  optionsLength,
                  queryFilter,
                  resetQueryFilter,
                }) =>
                  index === optionsLength - 1 &&
                  queryFilter.trim() && (
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
                  )
                }
              />
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
            <button
              className={'glossary__button-cancel'}
              onClick={() => {
                setShowForm(false)
                setShowMore(false)
                setModifyElement(undefined)
              }}
            >
              Cancel
            </button>
            <button className={'glossary__button-add'}>Add</button>
          </div>
        </div>
      </div>
    )
  }
  return (
    <div className={`tab sub-editor glossary ${active_class}`}>
      {showForm ? (
        getFormBox()
      ) : (
        <>
          <div className={'glossary_search'}>
            <div className={'glossary_search-container'}>
              <input
                name="search_term"
                className={'glossary_search-input'}
                placeholder={'Search term'}
                value={searchTerm}
                onChange={(event) => setSearchTerm(event.target.value)}
                onKeyDown={(event) => event.key === 'Enter' && onSubmitSearch()}
              />
              <SegmentedControl
                name="search"
                className="search-type"
                options={searchTypes}
                selectedId={searchTypes.find(({selected}) => selected).id}
                onChange={(value) => {
                  setSearchTypes((prevState) =>
                    prevState.map((tab) => ({
                      ...tab,
                      selected: tab.id === value,
                    })),
                  )
                }}
              />
            </div>
            <button
              className={'glossary__button-add'}
              onClick={() => openAddTerm()}
            >
              + Add Term
            </button>
          </div>
          <div className={'glossary_items'}>
            {terms.map((term, index) => (
              <GlossaryItem
                key={index}
                item={term}
                modifyElement={() => modifyItem(term)}
              />
            ))}
          </div>
        </>
      )}
    </div>
  )
}

SegmentFooterTabGlossary.propTypes = {
  active_class: PropTypes.string,
  segment: PropTypes.object,
}

const GlossaryItem = ({item, modifyElement}) => {
  const {metadata, source, target} = item
  return (
    <div className={'glossary_item'}>
      <div className={'glossary_item-header'}>
        <div className={'glossary_definition-container'}>
          <span className={'glossary_definition'}>
            <GlossaryDefinitionIcon />
            {metadata.definition}
          </span>
          <span className={'glossary_badge'}>{metadata.domain}</span>
          <span className={'glossary_badge'}>{metadata.subdomain}</span>
          <div className={'glossary_source'}>
            <b>{metadata.key_name}</b>
            <span>{metadata.last_update}</span>
          </div>
        </div>
        <div className={'glossary_item-actions'}>
          <div onClick={() => modifyElement()}>
            <ModifyIcon />
          </div>
          <div onClick={() => {}}>
            <DeleteIcon />
          </div>
        </div>
      </div>

      <div className={'glossary_item-body'}>
        <div className={'glossary-item_column'}>
          <div className={'glossary_word'}>
            {`${source.term} `}
            <div>
              <InfoIcon size={16} />
              <div className={'glossary_item-tooltip'}>{source.sentence}</div>
            </div>
          </div>
          <div className={'glossary-description'}>{source.note}</div>
        </div>
        <div className={'glossary-item_column'}>
          <div className={'glossary_word'}>
            {`${target.term} `}
            <div>
              <InfoIcon size={16} />
              <div className={'glossary_item-tooltip'}>{target.sentence}</div>
            </div>
          </div>
          <div className={'glossary-description'}>{target.note}</div>
        </div>
      </div>
    </div>
  )
}

const DeleteIcon = () => {
  return (
    <svg width="14" height="16" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path
        d="M1 3.333a.667.667 0 0 0 0 1.334V3.333Zm12 1.334a.667.667 0 1 0 0-1.334v1.334ZM11.667 4h.666a.667.667 0 0 0-.666-.667V4Zm-9.334 9.333h-.666.666ZM3.667 4A.667.667 0 1 0 5 4H3.667Zm2-2.667V.667v.666Zm2.666 0V.667v.666ZM9 4a.667.667 0 0 0 1.333 0H9ZM6.333 7.333a.667.667 0 1 0-1.333 0h1.333Zm-1.333 4a.667.667 0 0 0 1.333 0H5Zm4-4a.667.667 0 0 0-1.333 0H9Zm-1.333 4a.667.667 0 1 0 1.333 0H7.667ZM1 4.667h1.333V3.333H1v1.334Zm1.333 0H13V3.333H2.333v1.334ZM11 4v9.333h1.333V4H11Zm0 9.333c0 .177-.07.347-.195.472l.943.943a2 2 0 0 0 .585-1.415H11Zm-.195.472a.667.667 0 0 1-.472.195v1.333a2 2 0 0 0 1.415-.585l-.943-.943Zm-.472.195H3.667v1.333h6.666V14Zm-6.666 0a.667.667 0 0 1-.472-.195l-.943.943a2 2 0 0 0 1.415.585V14Zm-.472-.195A.667.667 0 0 1 3 13.333H1.667a2 2 0 0 0 .585 1.415l.943-.943ZM3 13.333V4H1.667v9.333H3Zm-.667-8.666h9.334V3.333H2.333v1.334ZM5 4V2.667H3.667V4H5Zm0-1.333c0-.177.07-.347.195-.472l-.943-.943a2 2 0 0 0-.585 1.415H5Zm.195-.472A.667.667 0 0 1 5.667 2V.667a2 2 0 0 0-1.415.585l.943.943ZM5.667 2h2.666V.667H5.667V2Zm2.666 0c.177 0 .347.07.472.195l.943-.943A2 2 0 0 0 8.333.667V2Zm.472.195A.667.667 0 0 1 9 2.667h1.333a2 2 0 0 0-.585-1.415l-.943.943ZM9 2.667V4h1.333V2.667H9ZM5 7.333v4h1.333v-4H5Zm2.667 0v4H9v-4H7.667Z"
        fillRule="evenodd"
        fill="currentColor"
      />
    </svg>
  )
}

const ModifyIcon = () => {
  return (
    <svg width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path
        d="M7.333 3.333a.667.667 0 0 0 0-1.333v1.333Zm-4.666-.666V2v.667ZM1.333 4H.667h.666Zm0 9.333H.667h.666ZM14 8.667a.667.667 0 0 0-1.333 0H14Zm-1.667-7-.471-.472.471.472Zm1-.415V.586v.666Zm1 2.415-.471-.472.471.472ZM8 10l.162.647a.668.668 0 0 0 .31-.176L8 10Zm-2.667.667-.647-.162a.667.667 0 0 0 .809.808l-.162-.646ZM6 8l-.471-.471a.667.667 0 0 0-.176.31L6 8Zm1.333-6H2.667v1.333h4.666V2ZM2.667 2a2 2 0 0 0-1.415.586l.943.943a.667.667 0 0 1 .472-.196V2Zm-1.415.586A2 2 0 0 0 .667 4H2c0-.177.07-.346.195-.471l-.943-.943ZM.667 4v9.333H2V4H.667Zm0 9.333a2 2 0 0 0 .585 1.415l.943-.943A.667.667 0 0 1 2 13.333H.667Zm.585 1.415a2 2 0 0 0 1.415.585V14a.666.666 0 0 1-.472-.195l-.943.943Zm1.415.585H12V14H2.667v1.333Zm9.333 0a2 2 0 0 0 1.414-.585l-.943-.943A.666.666 0 0 1 12 14v1.333Zm1.414-.585A2 2 0 0 0 14 13.332h-1.333c0 .177-.07.347-.196.472l.943.943ZM14 13.332V8.667h-1.333v4.666H14ZM12.805 2.138c.14-.14.33-.219.528-.219V.586c-.552 0-1.08.219-1.471.61l.943.942Zm.528-.219c.198 0 .389.079.529.22l.943-.944c-.39-.39-.92-.61-1.472-.61V1.92Zm.529.22c.14.14.219.33.219.528h1.333c0-.552-.22-1.082-.61-1.472l-.942.943Zm.219.528a.748.748 0 0 1-.22.528l.944.943c.39-.39.61-.92.61-1.471H14.08Zm-.22.528L7.53 9.53l.942.942 6.334-6.333-.943-.943ZM7.839 9.353l-2.666.667.323 1.293 2.667-.666-.324-1.294ZM5.98 10.828l.667-2.666-1.294-.324-.667 2.667 1.294.323Zm.491-2.357 6.334-6.333-.943-.943L5.529 7.53l.942.942Z"
        fillRule="evenodd"
        fill="currentColor"
      />
    </svg>
  )
}

const GlossaryDefinitionIcon = () => {
  return (
    <svg width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path
        d="M13.333 12.667v-2H4.667a2 2 0 0 0-2 2m3.2 2H11.2c.747 0 1.12 0 1.405-.146.251-.128.455-.332.583-.582.145-.286.145-.659.145-1.406V3.467c0-.747 0-1.12-.145-1.406a1.333 1.333 0 0 0-.583-.582c-.285-.146-.658-.146-1.405-.146H5.867c-1.12 0-1.68 0-2.108.218a2 2 0 0 0-.875.874c-.217.428-.217.988-.217 2.108v6.934c0 1.12 0 1.68.217 2.108a2 2 0 0 0 .875.874c.427.218.987.218 2.108.218Z"
        stroke="currentColor"
      />
    </svg>
  )
}

const MoreIcon = () => {
  return (
    <svg width="12" height="8" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path
        d="M5.2 6.933 1.2 1.6A1 1 0 0 1 2 0h8a1 1 0 0 1 .8 1.6l-4 5.333a1 1 0 0 1-1.6 0Z"
        fill="#AEBDCD"
      />
    </svg>
  )
}
