import React, {useEffect, useState} from 'react'
import SearchUtils from './search/searchUtils'
import {useHotkeys} from 'react-hotkeys-hook'
import {Shortcuts} from '../../../utils/shortcuts'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../../common/Button/Button'
import Search from '../../../../img/icons/Search'
import CatToolStore from '../../../stores/CatToolStore'
import CatToolConstants from '../../../constants/CatToolConstants'
import SearchFilled from '../../../../img/icons/SearchFilled'

export const SearchButton = () => {
  const [searchOpen, setSearchOpen] = useState(SearchUtils.searchOpen)
  useHotkeys(
    Shortcuts.cattol.events.openSearch.keystrokes[Shortcuts.shortCutsKeyType],
    (e) => openSearch(e),
    {enableOnContentEditable: true},
  )
  const openSearch = (event) => {
    SearchUtils.toggleSearch(event)
  }
  useEffect(() => {
    const closeSearch = (container) => {
      if ((container && container === 'search') || !container) {
        setSearchOpen((prevState) => !prevState)
      }
    }
    CatToolStore.addListener(CatToolConstants.TOGGLE_CONTAINER, closeSearch)
    CatToolStore.addListener(CatToolConstants.CLOSE_SUBHEADER, closeSearch)
    CatToolStore.addListener(CatToolConstants.SHOW_CONTAINER, closeSearch)
    return () => {
      CatToolStore.removeListener(
        CatToolConstants.TOGGLE_CONTAINER,
        closeSearch,
      )
      CatToolStore.removeListener(CatToolConstants.CLOSE_SUBHEADER, closeSearch)
      CatToolStore.removeListener(CatToolConstants.SHOW_CONTAINER, closeSearch)
    }
  }, [])
  return (
    SearchUtils.searchEnabled &&
    (searchOpen ? (
      <Button
        size={BUTTON_SIZE.ICON_STANDARD}
        mode={BUTTON_MODE.GHOST}
        onClick={openSearch}
      >
        <SearchFilled />
      </Button>
    ) : (
      <Button
        size={BUTTON_SIZE.ICON_STANDARD}
        mode={BUTTON_MODE.GHOST}
        onClick={openSearch}
      >
        <Search />
      </Button>
    ))
  )
}
