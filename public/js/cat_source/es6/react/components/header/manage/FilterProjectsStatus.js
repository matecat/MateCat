import IconFilter from "../../icons/IconFilter";
import IconTick from "../../icons/IconTick";

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
			<IconFilter width={24} height={24} color={'#002b5c'}/>
			<div className="text">Active</div>
			<div className="menu">
				{status.map((e, i) => <div key={i} className="item" data-value={e}>{e} {e === this.currentFilter ?
					<IconTick width={14} height={14} color={'#ffffff'}/> : null}</div>)}
			</div>
		</div>;
	}
}

export default FilterProjects;
