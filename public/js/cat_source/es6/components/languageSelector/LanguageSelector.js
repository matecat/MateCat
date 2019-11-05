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
		return <div id="matecat-modal-languages" className="matecat-modal" onClick={onClose}>
					<div className="matecat-modal-content" >

						<div className="matecat-modal-header">
							<span>{selectedLanguages}: {querySearch} </span>
							<span className="close-matecat-modal x-popup" onClick={onClose}/>
						</div>
						<div className="matecat-modal-subheader">
							<div >
								<span>From:</span>
							</div>
							<div>
								<span>To:</span>
								<LanguageSelectorSearch languagesList={languagesList} selectedLanguages={selectedLanguages}
														querySearch={querySearch}
														onDeleteLanguage={onToggleLanguage}
														onQueryChange={onQueryChange}/>
							</div>
						</div>
						<div className="matecat-modal-body">

							<LanguageSelectorList languagesList={languagesList} selectedLanguages={selectedLanguages}
												  querySearch={querySearch}
												  onToggleLanguage={onToggleLanguage}/>

						</div>

						<div className="matecat-modal-footer">
							<div className="ui one column grid right aligned">
								<div className="column">
									<button onClick={onClose}>close</button>
									<button onClick={onConfirm}>confirm</button>
								</div>
							</div>
						</div>

					</div>
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
