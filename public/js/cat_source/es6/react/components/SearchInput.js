class SearchInput extends React.Component {
    constructor (props) {
        super(props);
    }

    filterByName() {
        if($(this.textInput).val().length) {
            $(this.closeIcon).show()
        } else {
            $(this.closeIcon).hide();
        }
        if (!this.performingSearch) {
            this.props.onChange($(this.textInput).val());
        }

    }

    closeSearch() {
        $(this.textInput).val('');
        this.props.closeSearchCallback();
        $(this.closeIcon).hide();
    }


    render () {
        return (<div className="row">
                    <div className="input-field">
                        <i className="icon-search prefix"/>
                        <input id="icon_prefix" type="text" className="valid"
                               placeholder="Search by project name"
                               ref={(input) => this.textInput = input}
                               onChange={this.filterByName.bind(this)}/>
                        <i className="prefix close-x" style={{display: 'none'}}
                           ref={(closeIcon) => this.closeIcon = closeIcon}
                           onClick={this.closeSearch.bind(this)}/>
                    </div>
                </div>
        );
    }
}

export default SearchInput ;