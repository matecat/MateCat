class LanguageSelectorList extends React.Component {
	constructor(props) {
		super(props);
	}

	componentDidMount() {

	}

	componentWillUnmount() {
	}

	componentDidUpdate() {

	}

	render() {
		return <div>
				<p>list</p>
			</div>
	}

}

Header.defaultProps = {
	selectedLanguages: false,
	languagesList: true,
	onSelect: true,
};

export default LanguageSelectorList;
