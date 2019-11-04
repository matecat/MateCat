import TagsInput from 'react-tagsinput'


class LanguageSelectorSearch extends React.Component {
	constructor(props) {
		super(props);
		this.state = {
			query: '',
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
		this.setState({tags})
	}

	handleChangeInput = (query) => {
		this.setState({query})
	}


	render() {
		const {query, tags} = this.state;
		return <div>
			<TagsInput
				inputValue={query}
				onChangeInput={this.handleChangeInput}
				value={tags}
				onChange={this.handleChange}/>
			<p>search: {query}</p>
		</div>
	}

}

Header.defaultProps = {
	selectedLanguages: false,
	languagesList: true,
	onSearch: true,
};

export default LanguageSelectorSearch;
