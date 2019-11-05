import LanguageSelectorList from "./LanguageSelectorList";
import LanguageSelectorSearch from "./LanguageSelectorSearch";

class LanguageSelector extends React.Component {
	constructor(props) {
		super(props);
		this.state = {
			selectedLanguages: null,
			initialLanguages: null,
			querySearch: ''
		};
	}

	componentDidMount() {
		const {selectedLanguagesFromDropdown} = this.props;
		this.setState({
			selectedLanguages: selectedLanguagesFromDropdown,
			initialLanguages: selectedLanguagesFromDropdown
		})

	}

	componentWillUnmount() {
	}

	componentDidUpdate() {

	}

	render() {
		const {onQueryChange, onToggleLanguage, onConfirm} = this;
		const {languagesList, onClose} = this.props;
		const {selectedLanguages, querySearch} = this.state;
		return <div>
			<h1>{selectedLanguages}: {querySearch}</h1>
			<LanguageSelectorSearch languagesList={languagesList} selectedLanguages={selectedLanguages}
									querySearch={querySearch}
									onDeleteLanguage={onToggleLanguage}
									onQueryChange={onQueryChange}/>
			<LanguageSelectorList languagesList={languagesList} selectedLanguages={selectedLanguages}
								  querySearch={querySearch}
								  onToggleLanguage={onToggleLanguage}/>
			<button onClick={onClose}>close</button>
			<button onClick={onConfirm}>confirm</button>
		</div>
	}

	onConfirm = () => {
		//confirm must have 1 language selected
		const {selectedLanguages} = this.state;
		const {languagesList} = this.props;
		const mappedSelectedLanguages = selectedLanguages.map(e => {
			return languagesList.filter(i => i.code === e)[0]
		});
		this.props.onConfirm(mappedSelectedLanguages);
	};

	onQueryChange = (querySearch) => {
		this.setState({querySearch})
	};

	onToggleLanguage = (language) => {
		//when add a language, restore query search.
	}
}

Header.defaultProps = {
	selectedLanguagesFromDropdown: false,
	languagesList: true,
	onClose: true,
	onConfirm: true
};

export default LanguageSelector;
