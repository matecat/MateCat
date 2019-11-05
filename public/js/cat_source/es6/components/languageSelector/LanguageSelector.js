import LanguageSelectorList from "./LanguageSelectorList";
import LanguageSelectorSearch from "./LanguageSelectorSearch";

class LanguageSelector extends React.Component {
	constructor(props) {
		super(props);
		this.state = {
			selectedLanguages: null,
			initialLanguages: null,

			isShowingModal: false,
			styleContainer:'',
			onCloseCallback: false
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
		const {languagesList,onClose, onConfirm} = this.props;
		const {selectedLanguages} = this.state;
		return <div id="language-modal-overlay">
					<div id="language-modal" className="language-modal">
						<div className="language-modal-content" >
							<div className="language-modal-header">
								{/*<span className="close-language-modal x-popup" />*/}
							</div>
							<div className="language-modal-body">
								<h1>{selectedLanguages}</h1>
								<LanguageSelectorSearch languagesList={languagesList} selectedLanguages={selectedLanguages}/>
								<LanguageSelectorList languagesList={languagesList} selectedLanguages={selectedLanguages}/>
								<button onClick={onClose}>close</button>
								<button onClick={onConfirm}>confirm</button>
							</div>

						</div>
					</div>
				</div>
	}

	onConfirm = () => {
		//confirm must have 1 language selected
		const {selectedLanguages} = this.state;
		const {languagesList} = this.props;
		const mappedSelectedLanguages = selectedLanguages.map(e=>{
			return languagesList.filter(i=>i.code === e)[0]
		});
		this.props.onConfirm(mappedSelectedLanguages);
	}

	onClose = (event) => {
		event.stopPropagation();
		this.setState({
			showingModal: false
		})
	}
}

Header.defaultProps = {
	selectedLanguagesFromDropdown: false,
	languagesList: true,
	onClose: true,
	onConfirm: true
};

export default LanguageSelector;
