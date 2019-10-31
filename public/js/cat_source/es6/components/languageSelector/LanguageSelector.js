class LanguageSelector extends React.Component {
	constructor(props) {
		super(props);
		this.state = {
			selectedLanguages: null
		};
	}

	componentDidMount() {
		const {selectedLanguagesFromDropdown} = this.props;
		console.log(selectedLanguagesFromDropdown);
		this.setState({
			selectedLanguages: selectedLanguagesFromDropdown
		})

	}

	componentWillUnmount() {
	}

	componentDidUpdate() {

	}

	render() {
		const {selectedLanguages} = this.state;
		return <div>
				<h1>{selectedLanguages}</h1>
				<button onClick={this.onClose}>close</button>
				<button onClick={this.onConfirm}>confirm</button>
			</div>
	}

	onClose = () => {
		this.props.onClose();
	};
	
	onConfirm = () => {
		const {selectedLanguages} = this.state;
		this.props.onConfirm(selectedLanguages);
	}
}

Header.defaultProps = {
	selectedLanguagesFromDropdown: false,
	onClose: true,
	onConfirm: true
};

export default LanguageSelector;
