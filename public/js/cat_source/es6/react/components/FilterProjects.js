class FilterProjects extends React.Component {
    constructor (props) {
        super(props);

        this.onChangeFunction = this.onChangeFunction.bind(this);
    }

    componentDidMount () {
        let self = this;

        $(this.dropdown).dropdown({
            onChange: function() {
                self.onChangeFunction();
            }
        });
        this.currentFilter = 'active';
        $(this.dropdown).dropdown('set selected', 'active');
    }

    onChangeFunction() {
        if (this.currentFilter !== $(this.dropdown).dropdown('get value')) {
            this.props.filterFunction($(this.dropdown).dropdown('get value'));
            this.currentFilter = $(this.dropdown).dropdown('get value');
        }
    }

    componentDidUpdate() {
        this.currentFilter = 'active';
        $(this.dropdown).dropdown('set selected', 'active');
    }

    render () {
        return <div className="ui top left pointing dropdown" title="Status Filter" ref={(dropdown) => this.dropdown = dropdown}>
                    <i className="icon-filter icon" />
                    <div className="text">Active</div>
                    <div className="menu">
                        <div className="item" data-value="active">Active</div>
                        <div className="item" data-value="archived">Archived</div>
                        <div className="item" data-value="cancelled">Cancelled</div>
                    </div>
                </div> ;
    }
}

export default FilterProjects ;