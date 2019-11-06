import TagsInput from 'react-tagsinput'


class LanguageSelectorSearch extends React.Component {
	constructor(props) {
		super(props);
		this.state = {
			tags: []
		}
	}

	componentDidMount() {

	}

	componentWillUnmount() {
	}

	componentDidUpdate() {

	}


	handleChange = (tags) => {
		console.log('entra');
		//this.setState({tags})
	}

	render() {
		const {onQueryChange,querySearch} = this.props;
		const {tags} = this.state;
		return <div>
			<TagsInput
				inputValue={querySearch}
				addKeys={[]}
				onChangeInput={onQueryChange}
				value={tags}
				onChange={this.handleChange}/>
			<p>search: {querySearch}</p>
		</div>
	}

}

Header.defaultProps = {
	selectedLanguages: false,
	languagesList: true,
	querySearch: true,
	onDeleteLanguage: true,
	onQueryChange: true,
};

export default LanguageSelectorSearch;
