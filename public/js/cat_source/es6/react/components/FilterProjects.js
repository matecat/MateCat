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
        return<section className="heading-list-project">
                <div className="container">
                    <div className="row">
                        <div className="col s9">
                            <h4>Project List</h4>
                        </div>
                        <form>
                        <div className="input-field col s3 right">
                            <select defaultValue="active"
                                    ref={(select) => this.select = select}>
                                    <option value="active">Active</option>
                                    <option value="archived">Archived</option>
                                    <option value="cancelled">Cancelled</option>

                            </select>
                        </div>
                        </form>
                    </div>
                </div>
            </section>


    }
}

export default FilterProjects ;