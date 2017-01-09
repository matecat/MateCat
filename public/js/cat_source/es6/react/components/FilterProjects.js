class FilterProjects extends React.Component {
    constructor (props) {
        super(props);
    }

    componentDidMount () {
        $(this.select).material_select(this.onChangeFunction.bind(this));
    }

    onChangeFunction() {
        this.props.filterFunction(this.select.value)
    }

    render () {
        return <div className="row">
                        {/*<div className="col s9">*/}
                            {/*<h4>Project List</h4>*/}
                        {/*</div>*/}
                        <form>
                        <div className="input-field">
                            <select defaultValue="active"
                                    ref={(select) => this.select = select}>
                                    <option value="active">Active Projects</option>
                                    <option value="archived">Archived Projects</option>
                                    <option value="cancelled">Cancelled Projects</option>

                            </select>
                        </div>
                        </form>
                    </div>



    }
}

export default FilterProjects ;