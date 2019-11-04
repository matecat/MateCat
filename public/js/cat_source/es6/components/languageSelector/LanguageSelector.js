import LanguageSelectorList from "./LanguageSelectorList";
import LanguageSelectorSearch from "./LanguageSelectorSearch";

class LanguageSelector extends React.Component {
	constructor(props) {
		super(props);
		this.state = {
			selectedLanguages: null
		};
	}

	componentDidMount() {
		const {selectedLanguagesFromDropdown} = this.props;
		this.setState({
			selectedLanguages: selectedLanguagesFromDropdown
		})

	}

	componentWillUnmount() {
	}

	componentDidUpdate() {

	}

	render() {
		const {languagesList,onClose} = this.props;
		const {selectedLanguages} = this.state;
		return <div>
			<h1>{selectedLanguages}</h1>
			<LanguageSelectorSearch languagesList={languagesList} selectedLanguages={selectedLanguages}/>
			<LanguageSelectorList languagesList={languagesList} selectedLanguages={selectedLanguages}/>
			<button onClick={onClose}>close</button>
			<button onClick={this.onConfirm}>confirm</button>
		</div>
	}

	onConfirm = () => {
		const {selectedLanguages} = this.state;
		const {languagesList} = this.props;
		const mappedSelectedLanguages = selectedLanguages.map(e=>{
			return languagesList.filter(i=>i.code === e)[0]
		});
		this.props.onConfirm(mappedSelectedLanguages);
	}
}

Header.defaultProps = {
	selectedLanguagesFromDropdown: false,
	languagesList: true,
	onClose: true,
	onConfirm: true
};

export default LanguageSelector;
