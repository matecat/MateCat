
class FilterSegments extends React.Component {

    constructor(props) {
        super(props);

        this.state = this.defaultState();
        // this.doSubmitFilter = this.doSubmitFilter.bind(this);

    }

    defaultState() {
        return {
            selectedStatus: '',
            filtering: false,
            filteredCount: 0
        }
    }

    filterSelectChanged(value) {

        this.setState({
            selectedStatus: value,
        });

        // setTimeout(this.doSubmitFilter, 100);

    }

    resetStatusFilter() {
        $(this.statusDropdown).dropdown('restore defaults');
    }

    initDropDown() {
        let self = this;
        $(this.statusDropdown).dropdown({
            onChange: function(value, text, $selectedItem) {
                self.filterSelectChanged(value);
            }
        });
    }

    componentDidMount() {
        this.initDropDown();
    }

    componentDidUpdate() {}

    render () {
        let optionsStatus = config.searchable_statuses.map(function (item, index) {
            return <div className="item" key={index} data-value={item.value}>
                <div  className={"ui "+ item.label.toLowerCase() +"-color empty circular label"} />
                {item.label}
            </div>;
        });
        let statusFilterClass = (this.state.selectedStatus !== "") ? "filtered" : "not-filtered";
        return <div className="">Filters by
            <div className="filter-dropdown">
                <div className={"filter-status " + statusFilterClass}>
                    <div className="ui top left pointing dropdown basic tiny button" ref={(dropdown)=>this.statusDropdown=dropdown}>
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