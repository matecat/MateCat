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

    this.tagsInput.focus()
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

  handleChange = () => {
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
        autofocus={true}
        ref={(tagsInput) => (this.tagsInput = tagsInput)}
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
      classNameRemove,
      getTagDisplayValue,
      onRemove,
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
            onClick={() => removeLanguageWithIconTag(key)}
          >
            {' '}
            &times;
          </a>
        )}
      </span>
    )
  }
}

LanguageSelectorSearch.defaultProps = {
  selectedLanguages: false,
  languagesList: true,
  querySearch: true,
  onDeleteLanguage: true,
  onQueryChange: true,
}

export default LanguageSelectorSearch
