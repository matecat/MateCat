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

        return (
                <form className="col s4">

                    <div className="row">
                        <div className="input-field">
                            <i className="material-icons prefix">search</i>
                            <input id="icon_prefix" type="text" className="valid"
                                   ref={(input) => this.textInput = input}
                                   onChange={this.filterByName.bind(this)}/>
                            <i className="material-icons"
                               onClick={this.closeSearch.bind(this)}
                            >close</i>
                        </div>
                    </div>
                </form>


        );
    }
}

export default SearchInput ;