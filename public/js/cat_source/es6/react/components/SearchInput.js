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
                <div className="col l4 offset-l4 s4 offset-s4">

                    <div className="row">
                        <div className="input-field">
                            <i className="icon-search prefix"/>
                            <input id="icon_prefix" type="text" className="valid"
                                   placeholder="Search by project name"
                                   ref={(input) => this.textInput = input}
                                   onChange={this.filterByName.bind(this)}/>
                            <i className="prefix close-x"
                            onClick={this.closeSearch.bind(this)}/>
                        </div>
                    </div>
                </div>


        );
    }
}

export default SearchInput ;