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
                <form className="col l4 offset-l4 s4 offset-s4">

                    <div className="row">
                        <div className="input-field">
                            <i className="icon-search prefix"></i>
                            <input id="icon_prefix" type="text" className="valid"
                                   placeholder="Search by project name"
                                   ref={(input) => this.textInput = input}
                                   onChange={this.filterByName.bind(this)}/>
                            <i className="prefix close-x"
                            onClick={this.closeSearch.bind(this)}></i>
                        </div>
                    </div>
                </form>


        );
    }
}

export default SearchInput ;