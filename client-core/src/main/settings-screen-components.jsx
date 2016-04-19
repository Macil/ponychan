import cx from 'classnames';
import Immutable from 'immutable';
import React from 'react';

import settings from './settings';

const isModPage = (document.location.pathname == SITE_DATA.siteroot+'mod.php');
const isSettingsPage = (document.location.pathname == SITE_DATA.siteroot+'settings.html');

class InvalidItem extends React.Component {
	render() {
		const {value, setting} = this.props;
		const name = setting.get('name');
		const type = setting.get('type');
		return (
			<div>{name}, unsupported type: {type}</div>
		);
	}

	shouldComponentUpdate(prevProps) {
		return prevProps.value !== this.props.value ||
			prevProps.setting !== this.props.setting ||
			prevProps.disabled !== this.props.disabled;
	}
}

class SelectItem extends React.Component {
	render() {
		const {value, setting, disabled} = this.props;
		const name = setting.get('name');
		const desc = setting.get('description');
		const options = setting.get('selectOptions').map(item => {
			const itemValue = item.get('value');
			const displayName = item.get('displayName');
			return (
				<option key={itemValue} value={itemValue}>
					{displayName}
				</option>
			);
		}).toArray();
		const onChange = event => {
			settings.setSetting(name, event.target.value, true);
		};
		return (
			<div>
				<select disabled={disabled} onChange={onChange} value={value}>
					{options}
				</select>
				{' '}
				<span>{desc}</span>
			</div>
		);
	}

	shouldComponentUpdate(prevProps) {
		return prevProps.value !== this.props.value ||
			prevProps.setting !== this.props.setting ||
			prevProps.disabled !== this.props.disabled;
	}
}

class CheckboxItem extends React.Component {
	render() {
		const {value, setting, disabled} = this.props;
		const name = setting.get('name');
		const desc = setting.get('description');
		const toggle = event => {
			settings.setSetting(name, event.target.checked, true);
		};
		return (
			<label>
				<input type="checkbox" ref="input"
					disabled={disabled}
					checked={value} onChange={toggle} />
				<span className="setting_description">{desc}</span>
			</label>
		);
	}

	shouldComponentUpdate(prevProps) {
		return prevProps.value !== this.props.value ||
			prevProps.setting !== this.props.setting ||
			prevProps.disabled !== this.props.disabled;
	}
}

class SettingSaveButton extends React.Component {
	render() {
		const {valid, onSave, onUndo, disabled} = this.props;
		return (
			<span>
				<button type="button" disabled={!valid || disabled} onClick={onSave}>Save</button>
				<button type="button" disabled={disabled} onClick={onUndo}>Undo</button>
			</span>
		);
	}

	shouldComponentUpdate(prevProps) {
		return prevProps.value !== this.props.value ||
			prevProps.setting !== this.props.setting ||
			prevProps.disabled !== this.props.disabled;
	}
}

class TextAreaItem extends React.Component {
	constructor(props) {
		super(props);
		this.state = {value: props.value};
	}
	componentWillReceiveProps(nextProps) {
		this.setState({value: nextProps.value});
	}
	reset() {
		this.componentWillReceiveProps(this.props);
	}
	render() {
		const {setting, disabled} = this.props;
		const name = setting.get('name');
		const desc = setting.get('description');
		let valid = false;
		try {
			settings.checkSettingValue(name, this.state.value);
			valid = true;
		} catch(e) {}

		const onChange = event => {
			const value = this.refs.input.value;
			this.setState({value});
		};
		const onSave = event => {
			settings.setSetting(name, this.state.value, true);
		};
		return (
			<div>
				<div className="setting_description">{desc}</div>
				<textarea ref="input"
					disabled={disabled}
					value={this.state.value} onChange={onChange} />
				<div style={{visibility:this.props.value===this.state.value?'hidden':'visible'}}>
					<SettingSaveButton
						disabled={disabled} valid={valid}
						onSave={onSave} onUndo={()=>this.reset()} />
				</div>
			</div>
		);
	}

	shouldComponentUpdate(prevProps, prevState) {
		return prevProps.value !== this.props.value ||
			prevProps.setting !== this.props.setting ||
			prevProps.disabled !== this.props.disabled ||
			prevState.value !== this.state.value;
	}
}

class NumberItem extends React.Component {
	constructor(props) {
		super(props);
		this.state = {value: props.value};
	}
	componentWillReceiveProps(nextProps) {
		this.setState({value: nextProps.value});
	}
	reset() {
		this.componentWillReceiveProps(this.props);
	}
	render() {
		const {setting, disabled} = this.props;
		const name = setting.get('name');
		const desc = setting.get('description');
		let valid = false;
		try {
			settings.checkSettingValue(name, this.state.value);
			valid = true;
		} catch(e) {}

		const onChange = event => {
			const value = parseInt(this.refs.input.value);
			this.setState({value});
		};
		const onSave = event => {
			settings.setSetting(name, this.state.value, true);
		};
		return (
			<div>
				<div className="setting_description">{desc}</div>
				<input type="number" ref="input"
					disabled={disabled}
					value={this.state.value} onChange={onChange} />
				<span style={{visibility:this.props.value===this.state.value?'hidden':'visible'}}>
					<SettingSaveButton
						disabled={disabled} valid={valid}
						onSave={onSave} onUndo={()=>this.reset()} />
				</span>
			</div>
		);
	}
	shouldComponentUpdate(prevProps, prevState) {
		return prevProps.value !== this.props.value ||
			prevProps.setting !== this.props.setting ||
			prevProps.disabled !== this.props.disabled ||
			prevState.value !== this.state.value;
	}
}

class SettingItem extends React.Component {
	render() {
		const {setting, value} = this.props;
		const name = setting.get('name');
		const disableMessage = setting.get('disableMessage');
		const disabled = !!disableMessage;

		const moredetails = setting.get('moredetails');
		if (setting.get('moredetails_rawhtml') || (moredetails && typeof moredetails !== 'string')) {
			console.log('bad setting', name);
		}
		const moredetailsComponent = typeof moredetails !== 'string' ? null :
			<div>{moredetails}</div>;

		const testButton = setting.get('testButton');
		const testButtonFn = event => {
			event.preventDefault();
			testButton.get('fn')();
		};
		const testButtonComponent = !testButton ? null :
			<div><button type="button" disabled={disabled} onClick={testButtonFn}>
				{testButton.get('label')}
			</button></div>;

		const disabledMessageComponent = !disabled ? null :
			<div className="disable_message">{disableMessage}</div>;

		const extraComponent = !(
				moredetailsComponent || testButtonComponent || disabledMessageComponent
		) ? null :
			<div className="setting_more_details">
				{moredetailsComponent}
				{testButtonComponent}
				{disabledMessageComponent}
			</div>;

		const ITEM_TYPES = {
			bool: CheckboxItem,
			select: SelectItem,
			number: NumberItem,
			textarea: TextAreaItem
		};
		const Tag = ITEM_TYPES[setting.get('type')] || InvalidItem;

		const classes = cx('setting_item', {disabled});

		return (
			<div className={classes}>
				<Tag {...this.props} disabled={disabled} />
				{extraComponent}
			</div>
		);
	}
	shouldComponentUpdate(prevProps) {
		return prevProps.value !== this.props.value ||
			prevProps.setting !== this.props.setting;
	}
}

class SettingsSection extends React.Component {
	render() {
		const {metadata, values, section} = this.props;
		const items = section.get('settings')
			.map(name => metadata.get(name))
			.filter(setting => !setting.get('hidden'))
			.map(setting => {
				const name = setting.get('name');
				const userValue = values.get(name);
				const value = userValue != null ? userValue : setting.get('defval');
				return (
					<SettingItem key={name} setting={setting} value={value} />
				);
			}).toArray();
		return (
			<section>
				<h2>{section.get('displayName')}</h2>
				{items}
			</section>
		);
	}
	shouldComponentUpdate(prevProps) {
		return prevProps.metadata !== this.props.metadata ||
			prevProps.values !== this.props.values ||
			prevProps.section !== this.props.section;
	}
}

class SettingsCloseButton extends React.Component {
	render() {
		return (
			<button
				type="button"
				aria-label="Close"
				onClick={this.props.onClick}
				className="settings-close-button">
				X
			</button>
		);
	}
	shouldComponentUpdate(prevProps) {
		return prevProps.onClick !== this.props.onClick;
	}
}

export class SettingsWindow extends React.Component {
  render() {
		const {closeWindow, metadata, values, sections} = this.props;
		const sectionNodes = sections
			.filter(section => isModPage || !section.get('modOnly'))
			.map(section =>
				<SettingsSection
					metadata={metadata}
					values={values}
					section={section}
					key={section.get('name')}
					/>
			).toArray();
		const closeButton = isSettingsPage ? null :
			<SettingsCloseButton onClick={closeWindow}/>;
    return (
			<div>
				{closeButton}
	      <h1>Board Settings</h1>
				<hr/>
				{sectionNodes}
			</div>
    );
  }
	shouldComponentUpdate(prevProps) {
		return prevProps.closeWindow !== this.props.closeWindow ||
			prevProps.metadata !== this.props.metadata ||
			prevProps.values !== this.props.values ||
			prevProps.sections !== this.props.sections;
	}
}
