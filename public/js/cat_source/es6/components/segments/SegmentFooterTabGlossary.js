/**
 * React Component .

 */
import React, {useState} from 'react'
import {Select} from './../common/Select'
import InfoIcon from '../../../../../img/icons/InfoIcon'
const keys = [
  {
    name: 'Ciao',
    id: 'a3a708aad524b8fd4468',
  },
  {
    name: 'Concetta',
    id: '9baba936e9624fc4c663',
  },
  {
    name: 'No Name',
    id: 'c0a28df1943c5f854f75',
  },
  {
    name: 'No Name',
    id: '3e8e2d0de4a63e0272e7',
  },
  {
    name: 'Test creazione chiave 1',
    id: '20fb44b40065351c90f1',
  },
  {
    name: 'No Name',
    id: '55f7032ecd4f01323fbe',
  },
  {
    name: 'es_XC.tmx',
    id: '95ec21ba2634a410b0d1',
  },
  {
    name: 'prova',
    id: '1b5e94f1d67c287509b3',
  },
  {
    name: 'test ',
    id: 'b6e38ab1e2c8393ded0d',
  },
  {
    name: 'Ms test pl',
    id: '53d198c4085b5293c798',
  },
  {
    name: 'Micro1 test',
    id: 'c04a88eaafb2841d1d43',
  },
  {
    name: 'MMMM test',
    id: '192881653391f54ee955',
  },
  {
    name: 'test1 micro',
    id: '43dda923f2914ef12350',
  },
  {
    name: 'micro test',
    id: 'c9889f3bb2cf906486ee',
  },
  {
    name: 'micro test',
    id: 'edfc29151e12f8ea7382',
  },
  {
    name: 'Micro Test',
    id: '19f61ecc47d6c4f3d2a6',
  },
  {
    name: 'Private TM and Glossary',
    id: '08b645519fc29d460437',
  },
  {
    name: 'Microsoft test',
    id: 'ea8e383ec331c768424a',
  },
  {
    name: 'Microsoft',
    id: 'eb44d624758710490a92',
  },
  {
    name: 'Micro german',
    id: '7263f04f9711301dbd35',
  },
  {
    name: 'Micro french',
    id: '32407df35ad4da726706',
  },
  {
    name: 'Micro',
    id: '4c9cb9fdc101a8ff24d9',
  },
  {
    name: 'Micro',
    id: 'fa973db1815c326acb6a',
  },
  {
    name: 'MS',
    id: 'fa4a22b355baf83394b9',
  },
  {
    name: 'Private TM and Glossary',
    id: '4849101ed342147449e2',
  },
  {
    name: 'Private TM and Glossary',
    id: 'daf31bb28574f1f79d10',
  },
  {
    name: 'No Name',
    id: '821ec669486c553f76a5',
  },
  {
    name: 'guess tag test Finnish',
    id: '6626a5790f0723a3032c',
  },
  {
    name: 'es_XC.tmx',
    id: 'cf70b5aad3b8802e4f77',
  },
  {
    name: '1234567812345678123456734567456756565667ssssssssssssss',
    id: 'e9ed98bb5137ed81f40f',
  },
  {
    name: 'No Name',
    id: 'e7e4e69f2af1fb26834b',
  },
  {
    name: 'No Name',
    id: 'b1997669fde460b74375',
  },
]

const initialState = {
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
      name: 'ebay',
      id: '3',
    },
  ],
  subDomains: [
    {
      name: 'Rider',
      id: '1',
    },
    {
      name: 'Ciccio',
      id: '2',
    },
    {
      name: 'Franco',
      id: '3',
    },
  ],
}

export const SegmentFooterTabGlossary = ({active_class}) => {
  const [searchSource, setSearchSource] = useState()
  const [searchTarget, setSearchTarget] = useState()
  const [showForm, setShowForm] = useState(false)
  const [showMore, setShowMore] = useState(false)
  const [domains, setDomains] = useState(initialState.domains)
  const [subDomains, setSubDomains] = useState(initialState.subDomains)
  const [activeKeys, setActiveKeys] = useState([keys[0]])
  const [activeDomain, setActiveDomain] = useState(domains[0])
  const [activeSubDomain, setActiveSubDomain] = useState(subDomains[0])
  const [modifyElement, setModifyElement] = useState()
  const openAddTerm = () => {
    setShowForm(true)
  }

  const modifyItem = () => {
    setShowMore(true)
    setModifyElement({})
    setShowForm(true)
  }

  const getFormBox = () => {
    return (
      <div className={'glossary_add-container'}>
        <div className={'glossary-form-line'}>
          <div className={'input-with-label__wrapper'}>
            <label>Definition</label>
            <input name="glossary-term-definition" />
          </div>
          <div className={'glossary-tm-container'}>
            <Select
              className={'glossary-select'}
              name="glossary-term-tm"
              label="Glossary"
              placeholder={'Select a glossary'}
              showSearchBar
              multipleSelect="dropdown"
              options={keys}
              activeOptions={activeKeys}
              checkSpaceToReverse={false}
              onToggleOption={(option) => {
                if (option) {
                  if (activeKeys.some((item) => item.id === option.id)) {
                    setActiveKeys(
                      activeKeys.filter((item) => item.id !== option.id),
                    )
                  } else {
                    setActiveKeys(activeKeys.concat([option]))
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
                className={'glossary-select'}
                name="glossary-term-domain"
                label="Domain"
                placeholder={'Select a domain'}
                showSearchBar
                options={domains}
                activeOption={activeDomain}
                checkSpaceToReverse={false}
                onSelect={(option) => {
                  if (option) {
                    setActiveDomain(option)
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
                  queryFilter && (
                    <button
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
                className={'glossary-select'}
                name="glossary-term-subdomain"
                label="Subdomain"
                placeholder={'Select a subdomain'}
                showSearchBar
                options={subDomains}
                activeOption={activeSubDomain}
                checkSpaceToReverse={false}
                onSelect={(option) => {
                  if (option) {
                    setActiveSubDomain(option)
                  }
                }}
              />
            </div>
          </div>
        </div>

        <div className={'glossary-form-line'}>
          <div className={'input-with-label__wrapper'}>
            <label>Original term*</label>
            <input name="glossary-term-original" />
          </div>
          <div className={'input-with-label__wrapper'}>
            <label>Translated term*</label>
            <input name="glossary-term-translated" />
          </div>
        </div>
        {showMore && (
          <div className={'glossary-form-line more-line'}>
            <div>
              <div className={'input-with-label__wrapper'}>
                <label>Description</label>
                <input
                  className={'input-large'}
                  name="glossary-term-description-source"
                />
              </div>
              <div className={'input-with-label__wrapper'}>
                <label>Example phrase</label>
                <input
                  className={'input-large'}
                  name="glossary-term-example-source"
                />
              </div>
            </div>
            <div>
              <div className={'input-with-label__wrapper'}>
                <label>Description</label>
                <input
                  className={'input-large'}
                  name="glossary-term-description-target"
                />
              </div>
              <div className={'input-with-label__wrapper'}>
                <label>Example phrase</label>
                <input
                  className={'input-large'}
                  name="glossary-term-example-target"
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
  const getGlossaryItemBox = () => {
    return new Array(5)
      .fill({})
      .map((item, index) => (
        <GlossaryItem key={index} modifyElement={(item) => modifyItem(item)} />
      ))
  }
  return (
    <div className={`tab sub-editor glossary ${active_class}`}>
      {showForm ? (
        getFormBox()
      ) : (
        <>
          <div className={'glossary_search'}>
            <div className={'glossary_search-source'}>
              <input
                name="search_source"
                className={'glossary_search-input'}
                placeholder={'Search source'}
                onChange={(event) => setSearchSource(event.target.value)}
              />
            </div>
            <div className={'glossary_search-target'}>
              <input
                name="search_target"
                className={'glossary_search-input'}
                placeholder={'Search target'}
                onChange={(event) => setSearchTarget(event.target.value)}
              />
              <button
                className={'glossary__button-add'}
                onClick={() => openAddTerm()}
              >
                + Add Term
              </button>
            </div>
          </div>
          <div className={'glossary_items'}>{getGlossaryItemBox()}</div>
        </>
      )}
    </div>
  )
}

const GlossaryItem = ({item, modifyElement}) => {
  return (
    <div className={'glossary_item'}>
      <div className={'glossary_item-header'}>
        <div className={'glossary_definition-container'}>
          <span className={'glossary_definition'}>
            <GlossaryDefinitionIcon />
            The action or process of paying someone or something or of being
            paid.
          </span>
          <span className={'glossary_badge'}>Uber</span>
          <span className={'glossary_badge'}>Rider</span>
          <div className={'glossary_source'}>
            <b>Uber Glossary</b>
            <span>2022-07-08</span>
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
            Payment{' '}
            <div>
              <InfoIcon size={16} />
              <div className={'glossary_item-tooltip'}>Example</div>
            </div>
          </div>
          <div className={'glossary-description'}>
            The amount a rider, eater, user of UberRUSH, and other products,
            pays to get a ride, get a meal or a package delivered, etc.
          </div>
        </div>
        <div className={'glossary-item_column'}>
          <div className={'glossary_word'}>
            Pagamento{' '}
            <div>
              <InfoIcon size={16} />
              <div className={'glossary_item-tooltip'}>Example</div>
            </div>
          </div>
          <div className={'glossary-description'}>
            L'importo che un rider, un cliente UberEats, un utente di UberRUSH e
            di altri prodotti paga per ottenere una corsa, farsi consegnare un
            pasto o un pacco, ecc
          </div>
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
