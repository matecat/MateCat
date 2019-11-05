class LanguageSelectorList extends React.Component {
	constructor(props) {
		super(props);
		this.state = {
			position: 0
		}
	}

	componentDidMount() {

	}

	componentWillUnmount() {
	}

	componentDidUpdate(prevProps) {
		if (prevProps.querySearch !== this.props.querySearch) {
			this.setState({
				position: 0
			})
		}
	}

	render() {
		const languages = this.getLanguagesInColumns();
		const {querySearch} = this.props;
		const {position} = this.state;
		return <div className="languages-columns">
			{languages.map((languagesColumn, key) => {
				return (
					<ul key={key} className={'dropdown__list'}>
						{languagesColumn.map((e, key2) => {
							return <li key={`${key}${key2}`}
									   className={(querySearch && key2 === position) ? 'hover' : null}>{e.name}</li>
						})}
					</ul>
				);
			})}
		</div>
	}

	getFilteredLanguages = () => {
		const {languagesList, querySearch} = this.props;
		return languagesList.filter(e => e.name.toLowerCase().indexOf(querySearch.toLowerCase()) === 0);
	}

	getLanguagesInColumns = () => {
		const {getFilteredLanguages} = this;
		const {languagesList} = this.props;
		const languagesPerColumn = Math.ceil(languagesList.length / 4);
		const filteredLanguagesInColumns = chunk(getFilteredLanguages(), languagesPerColumn);

		if (filteredLanguagesInColumns.length >= 4) {
			return filteredLanguagesInColumns;
		} else {
			return filteredLanguagesInColumns.concat(buildRangeArray(4 - filteredLanguagesInColumns.length).map(function () {
				return [];
			}));
		}
	}

}

Header.defaultProps = {
	selectedLanguages: false,
	languagesList: true,
	onSelect: true,
	querySearch: true,
};

export default LanguageSelectorList;


export const chunk = (array, size) => {
	const firstChunk = array.slice(0, size);

	if (!firstChunk.length)
		return array;
	else
		return [firstChunk].concat(chunk(array.slice(size, array.length), size));
};


export const buildRangeArray = items => Array.apply(null, {length: items}).map(Number.call, Number);
