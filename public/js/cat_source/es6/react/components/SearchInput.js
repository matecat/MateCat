class SearchInput extends React.Component {
    constructor (props) {
        super(props);
    }

    filterByName() {
        if (!this.performingSearch) {
            this.props.onChange($(this.textInput).val());
        }
    }

    closeSearch() {
        $(this.textInput).val('');
        this.props.closeSearchCallback()
    }


    render () {

        return (<form>
                        <div className="input-field">
                            <input id="search" type="search" required
                                   ref={(input) => this.textInput = input}
                                   onChange={this.filterByName.bind(this)}/>
                                <label htmlFor="search"><i className="material-icons">search</i></label>
                                <i className="material-icons"
                                    onClick={this.closeSearch.bind(this)}
                                >close</i>
                        </div>
                    </form>
        );
    }
}

export default SearchInput ;