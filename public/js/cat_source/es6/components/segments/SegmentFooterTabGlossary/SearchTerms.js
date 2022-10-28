import React, {useContext, useEffect, useState} from 'react'
import SegmentActions from '../../../actions/SegmentActions'
import {SegmentedControl} from '../../common/SegmentedControl'
import IconClose from '../../icons/IconClose'
import IconSearch from '../../icons/IconSearch'
import {TabGlossaryContext} from './TabGlossaryContext'

export const SearchTerms = () => {
  const {
    searchTerm,
    setSearchTerm,
    segment,
    previousSearchTermRef,
    setIsLoading,
    haveKeysGlossary,
    openForm,
  } = useContext(TabGlossaryContext)

  const [searchTypes, setSearchTypes] = useState([
    {id: '0', name: 'Source', selected: true},
    {id: '1', name: 'Target'},
  ])

  useEffect(() => {
    let debounce

    if (!searchTerm && searchTerm !== previousSearchTermRef.current) {
      // empty search glossary GET
      console.log('Reset glossary GET')
      SegmentActions.setGlossaryForSegmentBySearch(segment.sid)
    } else if (searchTerm) {
      // start serching term with debounce
      const onSubmitSearch = () => {
        const searchingIn = searchTypes.find(({selected}) => selected).name
        const data = {
          sentence: searchTerm,
          idSegment: segment.sid,
          sourceLanguage:
            searchingIn === 'Source' ? config.source_code : config.target_code,
          targetLanguage:
            searchingIn === 'Source' ? config.target_code : config.source_code,
        }
        SegmentActions.searchGlossary(data)
        setIsLoading(true)
      }
      debounce = setTimeout(() => {
        console.log('Searching:', searchTerm)
        onSubmitSearch()
      }, 500)
    }

    previousSearchTermRef.current = searchTerm

    return () => {
      clearTimeout(debounce)
    }
  }, [
    searchTerm,
    segment.sid,
    segment.segment,
    searchTypes,
    previousSearchTermRef,
    setIsLoading,
  ])

  return (
    <div className={'glossary_search'}>
      <div className="glossary_search-container">
        <IconSearch />
        <input
          name="search_term"
          className={'glossary_search-input'}
          placeholder={'Search term'}
          value={searchTerm}
          onChange={(event) => setSearchTerm(event.target.value)}
          disabled={!haveKeysGlossary}
        />
        <div
          className={`search_term_reset_button ${
            searchTerm
              ? 'search_term_reset_button--visible'
              : 'search_term_reset_button--hidden'
          }`}
          onClick={() => setSearchTerm('')}
        >
          <IconClose />
        </div>
        <SegmentedControl
          name="search"
          className="search-type"
          options={searchTypes}
          disabled={!haveKeysGlossary}
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
      <div className="glossary__button-add-container">
        <button className={'glossary__button-add'} onClick={openForm}>
          + Add Term
        </button>
      </div>
    </div>
  )
}
