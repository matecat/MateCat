import React from 'react'
import TagsInput from 'react-tagsinput'

class LanguageSelectorSearch extends React.Component {
  constructor(props) {
    super(props)
  }

  state = {
    highlightDelete: false,
  }

  componentDidMount() {
    document.addEventListener('mousedown', () => {
      this.setState({highlightDelete: false})
    })
  }

  componentWillUnmount() {
    document.removeEventListener('mousedown', () => {
      this.setState({highlightDelete: false})
    })
  }

  componentDidUpdate(prevProps) {
    if (prevProps.querySearch !== this.props.querySearch) {
      this.setState({
        highlightDelete: false,
      })
    }
  }

  handleChange = (tags) => {
    const {onDeleteLanguage, selectedLanguages} = this.props
    const {highlightDelete} = this.state

    if (highlightDelete) {
      onDeleteLanguage(selectedLanguages[selectedLanguages.length - 1])
      this.setState({
        highlightDelete: false,
      })
    } else {
      this.setState({
        highlightDelete: true,
      })
    }
  }
  removeLanguageWithIconTag = (tagIndex) => {
    const {onDeleteLanguage, selectedLanguages} = this.props
    onDeleteLanguage(selectedLanguages[tagIndex])
  }

  render() {
    const {defaultRenderTag} = this
    const {onQueryChange, querySearch, selectedLanguages} = this.props
    return (
      <TagsInput
        inputValue={querySearch}
        addKeys={[]}
        inputProps={{placeholder: 'Search...'}}
        onChangeInput={onQueryChange}
        renderTag={defaultRenderTag}
        value={selectedLanguages ? selectedLanguages.map((e) => e.name) : []}
        onChange={this.handleChange}
      />
    )
  }

  defaultRenderTag = (props) => {
    const {removeLanguageWithIconTag} = this
    const {highlightDelete} = this.state
    const {selectedLanguages} = this.props
    let {
      tag,
      key,
      disabled,
      onRemove,
      classNameRemove,
      getTagDisplayValue,
      ...other
    } = props
    const highlight =
      highlightDelete && key + 1 === selectedLanguages.length
        ? 'highlightDelete'
        : ''
    return (
      <span key={key} {...other} className={`tag ${highlight}`}>
        {getTagDisplayValue(tag)}
        {!disabled && (
          <a
            className={classNameRemove}
            onClick={(e) => removeLanguageWithIconTag(key)}
          >
            {' '}
            &times;
          </a>
        )}
      </span>
    )
  }
}

Header.defaultProps = {
  selectedLanguages: false,
  languagesList: true,
  querySearch: true,
  onDeleteLanguage: true,
  onQueryChange: true,
}

export default LanguageSelectorSearch
