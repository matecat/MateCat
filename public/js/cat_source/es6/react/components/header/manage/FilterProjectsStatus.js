import IconFilter from "../../icons/IconFilter";

class FilterProjects extends React.Component {
	constructor(props) {
		super(props);
		this.state = {
			status: ['active', 'archived', 'cancelled']
		}

		this.onChangeFunction = this.onChangeFunction.bind(this);
	}

	componentDidMount() {
		let self = this;

		$(this.dropdown).dropdown({
			onChange: function () {
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

	render = () => {
		const {status} = this.state;

		return <div className="ui top left pointing dropdown" title="Status Filter"
					ref={(dropdown) => this.dropdown = dropdown}>
			<IconFilter width={24} height={24}/>
			<div className="text">Active</div>
			<div className="menu">
				{status.map((e,i)=><div key={i} className="item" data-value={e}>{e} {e === this.currentFilter ? null : null}</div>)}
				{/*<div className="item" data-value="active">Active</div>
				<div className="item" data-value="archived">Archived</div>
				<div className="item" data-value="cancelled">Cancelled</div>*/}
			</div>
		</div>;
	}
}

export default FilterProjects;
