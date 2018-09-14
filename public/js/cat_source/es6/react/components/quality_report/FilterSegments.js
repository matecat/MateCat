
class FilterSegments extends React.Component {

    constructor(props) {
        super(props);

        this.state = this.defaultState();
    }

    defaultState() {
        return {
            filter: {
                status: ""
            },
            filtering: false,
            filteredCount: 0
        }
    }

    filterSelectChanged(value) {

        this.setState({
            filter: {
                status: value
            }
        });

        this.props.applyFilter(this.state.filter);

    }

    resetStatusFilter() {
        $(this.statusDropdown).dropdown('restore defaults');
        this.setState({
            filter: {
                status: ""
            }
        });
        setTimeout(()=> {
            this.props.applyFilter(this.state.filter)
        });
    }

    initDropDown() {
        let self = this;
        $(this.statusDropdown).dropdown({
            onChange: function(value, text, $selectedItem) {
                if (value !== "") {
                    self.filterSelectChanged(value);
                }
            }
        });
        this.dropdownInitialized = true;
    }

    componentDidMount() {
        setTimeout(this.initDropDown.bind(this), 100);
    }

    componentDidUpdate() {
        if (!this.dropdownInitialized) {
            this.initDropDown();
        }
    }

    render () {
        let optionsStatus = config.searchable_statuses.map(function (item, index) {
            return <div className="item" key={index} data-value={item.value}>
                <div  className={"ui "+ item.label.toLowerCase() +"-color empty circular label"} />
                {item.label}
            </div>;
        });
        let statusFilterClass = (this.state.filter.status !== "") ? "filtered" : "not-filtered";
        return <div className="qr-filter-list">Filters by
            <div className="filter-dropdown left-10">
                <div className={"filter-status " + statusFilterClass}>
                    <div className="ui top left pointing dropdown basic tiny button right-0" ref={(dropdown)=>this.statusDropdown=dropdown}>
                        <div className="text">
                            <div>Segment Status</div>
                        </div>
                        <div className="ui cancel label"><i className="icon-cancel3" onClick={this.resetStatusFilter.bind(this)}/></div>
                        <div className="menu">
                            {optionsStatus}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    }
}

export default FilterSegments ;