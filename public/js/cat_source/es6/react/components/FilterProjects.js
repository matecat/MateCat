class FilterProjects extends React.Component {
    constructor (props) {
        super(props);
    }

    componentDidMount () {
        $(this.select).material_select();
    }

    onChangeFunction() {
        this.props.filterFunction(this.select.value)
    }

    render () {
        return  <form>
            <div className="input-field col s12">
                <select className="browser-default"
                        defaultValue="active"
                        ref={(select) => this.select = select}
                        onChange={this.onChangeFunction.bind(this)}
                >
                    <option value="active">Active Projects</option>
                    <option value="archived">Completed Projects</option>
                    <option value="cancelled">Cancelled Projects</option>
                </select>
            </div>
        </form>
    }
}

export default FilterProjects ;