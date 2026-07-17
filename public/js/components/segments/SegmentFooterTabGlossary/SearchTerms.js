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
    openForm,
    isLoading,
    notifyLoadingStatusToParent,
  } = useContext(TabGlossaryContext)

  const [searchTypes, setSearchTypes] = useState([
    {id: '0', name: 'Source', selected: true},
    {id: '1', name: 'Target'},
  ])

  useEffect(() => {
    let debounce

    if (!searchTerm && searchTerm !== previousSearchTermRef.current) {
      // empty search glossary GET
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
        SegmentActions.searchGlossary({
          ...data,
          isSearchingInTarget: searchingIn === 'Target',
        })
        notifyLoadingStatusToParent(true)
      }
      debounce = setTimeout(() => {
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
    notifyLoadingStatusToParent,
  ])

  return (
    <div className="glossary_search">
      <div className="glossary_search-container">
        <IconSearch />
        <input
          name="search_term"
          className="glossary_search-input"
          placeholder="Search term"
          value={searchTerm}
          onChange={(event) => setSearchTerm(event.target.value)}
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
        <button
          className="glossary__button-add"
          onClick={openForm}
          disabled={isLoading}
        >
          + Add Term
        </button>
      </div>
    </div>
  )
}
