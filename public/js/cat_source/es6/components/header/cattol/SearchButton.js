import React from 'react'
import SearchUtils from './search/searchUtils'
import {useHotkeys} from 'react-hotkeys-hook'
import {Shortcuts} from '../../../utils/shortcuts'

export const SearchButton = () => {
  useHotkeys(
    Shortcuts.cattol.events.openSearch.keystrokes[Shortcuts.shortCutsKeyType],
    (e) => openSearch(e),
    {enableOnContentEditable: true},
  )
  const openSearch = (event) => {
    SearchUtils.toggleSearch(event)
  }
  return (
    <>
      {SearchUtils.searchEnabled && (
        <div
          className="action-submenu ui floating dropdown"
          id="action-search"
          title="Search or Filter results"
          onClick={openSearch}
        >
          <svg
            width="30px"
            height="30px"
            viewBox="-4 -4 31 31"
            version="1.1"
            xmlns="http://www.w3.org/2000/svg"
          >
            <g
              id="Icon/Search/Active"
              stroke="none"
              strokeWidth="1"
              fill="none"
              fillRule="evenodd"
            >
              <path
                d="M23.3028148,20.1267654 L17.8057778,14.629284 C16.986716,15.9031111 15.9027654,16.9865185 14.6289383,17.8056296 L20.1264198,23.3031111 C21.0040494,24.1805432 22.4270123,24.1805432 23.3027654,23.3031111 C24.1804444,22.4271111 24.1804444,21.0041481 23.3028148,20.1267654 Z"
                id="Path"
                fill="#FFFFFF"
              />
              <circle
                id="Oval"
                stroke="#FFFFFF"
                strokeWidth="1.5"
                cx="9"
                cy="9"
                r="8.25"
              />
              <path
                className="st1"
                d="M9,16 C5.13400675,16 2,12.8659932 2,9 C2,5.13400675 5.13400675,2 9,2 C12.8659932,2 16,5.13400675 16,9 C16,12.8659932 12.8659932,16 9,16 Z M3.74404938,8.9854321 L5.2414321,8.9854321 C5.2414321,6.92108642 6.9211358,5.24153086 8.9854321,5.24153086 L8.9854321,3.744 C6.0957037,3.744 3.74404938,6.09565432 3.74404938,8.9854321 Z"
                id="Combined-Shape"
                fill="#FFFFFF"
              />
            </g>
          </svg>
        </div>
      )}
    </>
  )
}
