import React, {useEffect} from 'react'
import {Input} from '../../common/Input/Input'
import IconSearch from '../../icons/IconSearch'

const SearchInput = ({onChange}) => {
  const [searchFilter, setSearchFilter] = React.useState('')

  useEffect(() => {
    const tmOut = setTimeout(() => {
      onChange(searchFilter)
    }, 500)

    return () => clearTimeout(tmOut)
  }, [searchFilter, onChange])

  const closeSearch = () => {
    setSearchFilter('')
    onChange('')
  }

  const onKeyPressEvent = (e) => {
    if (e.which == 27) {
      closeSearch()
    } else if (e.which == 13 || e.keyCode == 13) {
      e.preventDefault()
      return false
    }
  }

  return (
    <Input
      name="searchByProjectName"
      placeholder="Search by project name"
      value={searchFilter}
      onChange={(e) => setSearchFilter(e.target.value)}
      onKeyPress={onKeyPressEvent}
      icon={<IconSearch />}
      data-testid="input-search-projects"
    />
  )
}

export default SearchInput
