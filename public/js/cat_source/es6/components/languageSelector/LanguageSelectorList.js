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
		const languages = this.getLanguagesInColumns();
		return <div className="languages-columns">
			{languages.map((languagesColumn, key) => {
				return (
					<ul key={key} className={'dropdown__list'}>
						{languagesColumn.map(e=><li>{e.name}</li>)}
					</ul>
				);
			})}
			</div>
	}

	getFilteredLanguages = () =>{
		const {languagesList,querySearch} = this.props;
		const filteredLanguages = languagesList.filter(e=>e.name.toLowerCase().indexOf(querySearch.toLowerCase()) > -1);
		return filteredLanguages
	}

	getLanguagesInColumns = () => {
		const {getFilteredLanguages} = this;
		const { languagesList } = this.props;
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


export const buildRangeArray = items => Array.apply(null, { length: items }).map(Number.call, Number);
