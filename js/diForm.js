/*

	2014-10-20 renovation

*/

function diForm(form_name, dont_attach_form_submit)
{
	var self = this;

	dont_attach_form_submit = dont_attach_form_submit || false;

	this.classes_ar = {
		empty: '', 				// no class
		ok: 'green',			// everything is ok
		warning: 'blue_dust',	// warning
		error: 'red'			// error
	};

	this.inputs = {};
	this.hints = {};
	this.containers = {};
	this.holds = {};
	this.error_flags = {};
	this.handlers = {};
	this.properties = {};
	this.submit_obj = {
		id: false,
		error_msg: ''
	};
	this.hold_hints = false;
	this.form_name = form_name;
	this.change_bg_colors = true;
	this.submitting = false;
	this.form = null;
	this.submit2 = null;

	// ['none','display','visibility']
	// if none - only message is put inside error container
	// if display - the style.display is set to 'block'/'none'
	// if visibility - the style.visibility is set to 'visible'/'hidden'
	this.error_display_mode = 'none';

	// if polite_mode is set to true, errors don't raise on first click of the input
	this.polite_mode = true;

	if (typeof this.form_name == 'string')
	{
    	this.form = _ge(this.form_name);

		if (!this.form)
			this.form = document.forms[this.form_name];
	}
	else if (typeof this.form_name == 'object')
	{
		this.form = this.form_name;
		this.form_name = this.form.name;
	}

	if (!dont_attach_form_submit && this.form)
	{
		$(this.form).submit(function() {
			return self.onSubmit() && self.onSubmit2();
		});
	}

	this.setInputClass = function(id, which)
	{
		$(this.inputs[id]).attr('class', '').addClass(this.classes_ar[which]);
	}

	this.setContainerClass = function(id, which)
	{
		$(this.containers[id]).attr('class', '').addClass(this.classes_ar[which]);
	}

	this.get_focused_id = function()
	{
		if (document.activeElement)
			return document.activeElement.id || document.activeElement.name;
	}

	this.set_error_display_mode = function(mode)
	{
		this.error_display_mode = mode;
	}

	this.set_submit2 = function(callback)
	{
		this.submit2 = callback;
	}

	this.setInput = function(id, handler, hold)
	{
		this.inputs[id] = _ge(id);
		this.hints[id] = _ge(id+'_hint');
		this.handlers[id] = handler;
		this.holds[id] = hold;
		this.error_flags[id] = 0;
		this.properties[id] = {min_len: 0, max_len: 0, check_on_submit: true, necessary: false};
	}

	this.setInputsAr = function(ar)
	{
    	var i, r, id;

		for (var i = 0; i < ar.length; i++)
		{
			r = ar[i];
    		id = typeof r.id != 'undefined' ? r.id : r.name;

    		this.inputs[id] = this.form ? this.form[r.name] : _ge(id);

			this.hints[id] = _ge(id+'_hint');
			this.handlers[id] = r.handler;
			this.holds[id] = typeof r.hold != 'undefined' ? r.hold : false;
			this.error_flags[id] = 0;
			this.properties[id] = {min_len: 0, max_len: 0, check_on_submit: true, necessary: false};

			if (typeof r.properties != 'undefined')
			{
				for (var j in r.properties)
				{
					this.properties[id][j] = r.properties[j];
				}
    		}

			if (typeof this.inputs[id].id == 'undefined')
    			this.inputs[id] = false;

			if (this.inputs[id])
			{
				$(this.inputs[id]).on('blur.diform focus.diform click.diform keyup.diform change.diform', function() {
					self.check(id);
				});
			}
		}
    }

	this.setInputProperty = function(id, key, value)
	{
		this.properties[id][key] = value;
    }

   	this.setSubmit = function(id, error_msg)
	{
		this.submit_obj.id = id;
		this.submit_obj.error_msg = error_msg;
		this.inputs[id] = _ge(id+'_btn');
		this.hints[id] = _ge(id+'_hint');
		this.containers[id] = _ge(id+'_container');
    }

	this.holdHints = function(to_hold)
	{
		this.hold_hints = to_hold;
	}

	this.set_hint_display = function(id, status)
    {
		if (this.hints[id])
		{
    		switch (this.error_display_mode)
			{
				case 'display':
					this.hints[id].style.display = status ? 'block' : 'none';
					break;

				case 'visibility':
					this.hints[id].style.visibility = status ? 'visible' : 'hidden';
					break;

				case 'none':
				default:
					break;
			}
    	}
	}

	this.is_error = function(id)
	{
		return typeof this.error_flags[id] != 'undefined' && this.error_flags[id];
	}

	// raise error
   	this.showError = function(id, hint, color_idx)
	{
		var id2 = false;

		if (this.hints[id])
			id2 = id;
		else if (id.substr(0,4) == 'dob_')
			id2 = 'dob_d';

		var show_error = this.submitting || !this.polite_mode || (this.polite_mode && this.get_focused_id() != id && !this.inputs[id].value);

    	if (show_error)
		{
			if (this.polite_mode && color_idx == 'error')
    			color_idx = 'warning';

			if (this.change_bg_colors)
			{
				if (this.inputs[id])
					this.setInputClass(id, color_idx);
			}
			if (id2)
			{
				//if (color_idx == 'warning')
    			//	hint = '<span class="red">Внимание!</span> '+hint;

				this.hints[id2].innerHTML = hint;
				//this.hints[id2].className = this.classes[color_idx];
			}

			this.set_hint_display(id, true);
    	}

		this.error_flags[id] = 1;
	}

	// hide error
	this.hideError = function(id, hint, color_idx)
    {
		hint = hint || '';
		color_idx = color_idx || 'empty';

		var id2 = false;
		if (this.hints[id]) id2 = id;
		else if (id.substr(0,4) == 'dob_') id2 = 'dob_d';

    	if (this.change_bg_colors)
    	{
			if (this.inputs[id])
				this.setInputClass(id, color_idx);
		}

		if (id2)
    	{
			this.hints[id2].innerHTML = hint;
			//this.hints[id2].className = this.classes[color_idx];
		}

		this.set_hint_display(id, false);

		this.error_flags[id] = 0;
	}

	// run checker by handler
	this.check = function(id)
	{
		if (this.hold_hints && this.holds[id]) return false;

		if (typeof this.handlers[id] != 'undefined')
    		this.handlers[id](this, id);

		if (this.submit_obj.id && this.hints[this.submit_obj.id] && id != 'tags')
		{
			this.hints[this.submit_obj.id].innerHTML = '';

			//!!!//

			if (this.containers[this.submit_obj.id])
				this.setContainerClass(this.submit_obj.id, 'ok');
    	}
	}

	this.clear = function()
	{
		for (var i in this.inputs)
		{
			if (i == this.submit_obj.id) continue;

			this.hideError(i, '', 'ok');
		}

		if (this.submit_obj.id && this.hints[this.submit_obj.id])
		{
			this.hints[this.submit_obj.id].innerHTML = '';

			//!!!//

			if (this.containers[this.submit_obj.id])
				this.setContainerClass(this.submit_obj.id, 'ok');
		}
	}

    // did any errors happen
	this.errorsHappened = function()
	{
		var r = false;

    	for (var i in this.error_flags)
		{
			if (this.error_flags[i])
			{
				r = true;
				break;
    		}
		}

		return r;
	}

	// on submit
	this.onSubmit = function()
    {
		var r = true;
		this.submitting = true;

		for (var i in this.error_flags)
		{
			if (this.properties[i] && this.properties[i].check_on_submit)
				this.check(i);

    		if (this.error_flags[i])
			{
				r = false;
			}
		}

		this.toggleSubmitContainer(r);

		this.submitting = false;
		return r;
	}

	this.onSubmit2 = function()
	{
		if (this.submit2)
			return this.submit2();

		return true;
	}

	this.toggleSubmitContainer = function(r)
	{
    	if (this.submit_obj.id && this.hints[this.submit_obj.id])
		{
			if (r)
			{
				this.hints[this.submit_obj.id].innerHTML = '';
				//!!!//
				if (this.containers[this.submit_obj.id])
					this.setContainerClass(this.submit_obj.id, 'ok');
    		}
			else
			{
    			this.hints[this.submit_obj.id].innerHTML = this.submit_obj.error_msg;
				//!!!//
				if (this.containers[this.submit_obj.id])
					this.setContainerClass(this.submit_obj.id, 'error');
			}
		}
	}

	this.lockSubmit = function(flag)
	{
		if (typeof flag == 'undefined') flag = true;

		this.inputs[this.submit_obj.id].disabled = !!flag;
	}
}

// --[ standard handle functions ]---------------------------------------------------------------

// form - diForm object
// id - id of the input to get checked
function __check_login(form, id)
{
	var e = form.inputs[id];
	var p = form.properties[id];

	if (e.value.length && !check_correct_latin_symbols(e.value))
		form.showError(id, 'Используй только латинские буквы, цифры или символы &laquo;_&raquo;, &laquo;-&raquo;, &laquo;.&raquo;', 'error');
	else if (e.value.length < 3)
		form.showError(id, 'Мин.длина - 3 символа', 'warning');
	else if (e.value.length > 15)
		form.showError(id, 'Макс.длина - 15 символов', 'warning');
	else
		form.hideError(id); //, 'Логин принят', 'ok'
}

function __check_password(form, id)
{
	if (id.substr(id.length - 1) == '2')
	{
		var id2 = id;
		id = id.substr(0, id.length - 1);
	}
	else
	{
		var id2 = id+'2';
	}

	var e = form.inputs[id];
	var e2 = form.inputs[id2];
	var p = form.properties[id];

	if (p.necessary && e.value.length < 6)
		form.showError(id, 'Мин.длина - 6 символов', 'warning');
	else
	{
		form.hideError(id, '', 'ok');

		if (e.value != e2.value && e.value.substr(0, e2.value.length) == e2.value)
			form.hideError(id2, '', 'warning');
		else if (p.necessary && !e2.value.length)
			form.hideError(id2, '', 'empty');
		else if (e.value.length && e2.value.length && e.value == e2.value)
		{
			form.hideError(id, '', 'ok');
			form.hideError(id2, '', 'ok');
		}
		else if (e.value.length && e2.value.length && e.value != e2.value)
			form.showError(id2, 'Введенные пароли не совпадают', 'error');
	}
}

function __check_string(form, id)
{
	var e = form.inputs[id];
	var p = form.properties[id];

	var v = trim(e.value);
	//alert(id+' '+e.value);

	if (p.min_len && v.length < p.min_len)
		form.showError(id, 'Минимальная длина - '+p.min_len+' символ(а)', 'warning');
	else if (p.necessary && !p.min_len && !v.length)
		form.showError(id, 'Это поле обязательно для заполнения', 'warning'); //'Введи '+fields_ar[id]
	else if (p.max_len && v.length > p.max_len)
		form.showError(id, 'Максимальная длина - '+p.max_len+' символ(ов)', 'error');
	else
		form.hideError(id);
}

function __check_file(form, id)
{
	var e = form.inputs[id];
	var p = form.properties[id];

	if (p.necessary && e.value.length == 0)
		form.showError(id, 'Выбери файл для загрузки', 'warning');
	else
		form.hideError(id);
}

function __check_checkbox(form, id)
{
	var e = form.inputs[id];
	var p = form.properties[id];

	if (p.necessary && !e.checked)
		form.showError(id, 'Это поле обязательно для заполнения', 'warning');
	else
		form.hideError(id);
}

function __check_agreement(form, id)
{
	var e = form.inputs[id];
	var p = form.properties[id];

	if (p.necessary && !e.checked)
		form.showError(id, 'Для участия в конкурсе необходимо принять условия Пользовательского соглашения', 'warning');
	else
		form.hideError(id);
}

function __check_int(form, id)
{
	return __check_number(form, id);
}

function __check_number(form, id)
{
	var e = form.inputs[id];
	var p = form.properties[id];

	if (e.value.length && !check_correct_digits(e.value))
		form.showError(id, 'Требуется ввести цифровое значение', 'error');
	else if (p.necessary && e.value.length == 0)
		form.showError(id, 'Это поле обязательно для заполнения', 'warning');
	else if (p.max_len && e.value.length > p.max_len)
		form.showError(id, 'Максимальная длина - '+p.max_len+' символов', 'error');
	else
		form.hideError(id);

	return !form.is_error(id);
}

function __check_email(form, id)
{
	var id2 = id.substr(id.length - 1) == '2' ? id.substr(0, id.length - 1) : id+'2';
	var e = form.inputs[id];
	var e2 = form.inputs[id2];
	var p = form.properties[id];

	var necessary = typeof p.necessary == 'function' ? p.necessary() : p.necessary;

	if (necessary && e.value.length == 0)
		form.showError(id, 'Введите E-mail', 'warning');
	else if (e.value.length > 60)
		form.showError(id, 'Макс.длина - 60 символов', 'error');
	else if (e.value.length && !check_correct_email(e.value))
		form.showError(id, 'Введите корректный E-mail', 'warning');
	else
	{
		form.hideError(id);

		if (e2)
		{
			if (e.value != e2.value && e.value.substr(0, e2.value.length) == e2.value)
				form.hideError(id2, '', 'warning');
			else if (!e2.value.length)
				form.hideError(id2, '', 'empty');
			else if (e.value.length && e2.value.length && e.value == e2.value)
			{
				form.hideError(id, '', 'ok');
				form.hideError(id2, '', 'ok');
			}
			else //if (e.value.length && e2.value.length && e.value != e2.value)
				form.showError(id2, 'Введенные E-mail не совпадают', 'error');
		}
	}
}

function __check_email_or_phone(form, id)
{
	var id2 = id.substr(id.length - 1) == '2' ? id.substr(0, id.length - 1) : id+'2';
	var e = form.inputs[id];
	var e2 = form.inputs[id2];
	var e_phone = form.inputs['phone'];
	var p = form.properties[id];

	if (p.necessary && e.value.length == 0 && e_phone.value.length == 0)
		form.showError(id, 'Это поле обязательно для заполнения', 'warning');
	else if (e.value.length > 60)
		form.showError(id, 'Максимальная длина - 60 символов', 'error');
	else if (e.value.length && !check_correct_email(e.value))
		form.showError(id, 'Введи корректный E-mail', 'warning');
	else
	{
		form.hideError(id);

		if (e2)
		{
			if (e.value != e2.value && e.value.substr(0, e2.value.length) == e2.value)
				form.hideError(id2, '', 'warning');
			else if (!e2.value.length)
				form.hideError(id2, '', 'empty');
			else if (e.value.length && e2.value.length && e.value == e2.value)
			{
				form.hideError(id, '', 'ok');
				form.hideError(id2, '', 'ok');
			}
			else //if (e.value.length && e2.value.length && e.value != e2.value)
				form.showError(id2, 'Введенные E-mail не совпадают', 'error');
		}
	}
}

function __check_phone_or_email(form, id)
{
	var e = form.inputs[id];

	__check_string(form, id);

	form.properties['email'].necessary = e.value.length > 0 ? false : true;
	__check_email_or_phone(form, 'email');
}

function __check_url(form, id)
{
	var e = form.inputs[id];
	var p = form.properties[id];

	if (p.necessary && e.value.length == 0)
		form.showError(id, 'Это поле обязательно для заполнения', 'warning');
	else if (p.max_len && e.value.length > p.max_len)
		form.showError(id, 'Максимальная длина - '+p.max_len+' символов', 'error');
	else if (e.value != "http://" && e.value != "" && !/^(http[s]?:\/\/)?(www\.)?[0-9a-zA-Z]([-._]?[0-9a-zA-Z])*[.]{1}[a-zA-Z]{2,4}(\/.*)?$/.test(e.value))
		form.showError(id, 'Введи корректную ссылку', 'warning');
	else
		form.hideError(id);
}

function __check_random_code(form, id)
{
	var e = form.inputs[id];
	var hash = _ge(id+'_hash');
	var cur_value = hex_md5(e.value);

	if (e.value.length != 4)
		form.showError(id, 'Длина кода - 4 символа', 'warning');
	else if (hash && hash.value != cur_value)
		form.showError(id, 'Введен неверный код', 'error');
	else
		form.hideError(id);
}

function __check_random_code2(form, id)
{
	var e = form.inputs[id];
	var cur_value = e.value;

	if (e.value.length != 32)
		form.showError(id, 'Пройди проверку', 'warning');
	else
		form.hideError(id);
}

var hobby_err_s = 'Выбери по крайней мере одно увлечение';

function __check_tags(form, id)
{
	var f = _ge('registration_form');
	var at_least_1 = false;

	for (var i = 0; i < f.elements.length; i++)
	{
		var e = f.elements[i];

		if (e.name && e.name.substr(0,6) == 'tags[]')
		{
			if (e.checked)
			{
				at_least_1 = true;
				break;
			}
		}
	}

	if (!at_least_1)
	{
		form.showError(id, hobby_err_s, 'error');

		//_ge('tags_table_td').style.backgroundColor = '#f66';
		//_ge('tags_table__').style.backgroundColor = '#f66';

		form.hints[form.submit_obj.id].innerHTML = form.submit_obj.error_msg;

		if (form.containers[form.submit_obj.id])
			form.setContainerClass(form.submit_obj.id, 'error');

		form.inputs[form.submit_obj.id].disabled = true;
	}
	else
	{
		form.hideError(id, '', 'ok');

		//_ge('tags_table_td').style.backgroundColor = '#FAD78A';
		//_ge('tags_table__').style.backgroundColor = '#FAD78A';

		form.hints[form.submit_obj.id].innerHTML = '';

		if (form.containers[form.submit_obj.id])
			form.setContainerClass(form.submit_obj.id, 'ok');

		form.inputs[form.submit_obj.id].disabled = false;
	}
}

function __check_tags2(form, id)
{
	var f = _ge('setup_tags_form');
	var at_least_1 = false;

	for (var i = 0; i < f.elements.length; i++)
	{
		var e = f.elements[i];

		if (e.name.substr(0,6) == 'tags[]')
		{
			if (e.checked)
			{
				at_least_1 = true;
				break;
			}
		}
	}

	if (!at_least_1)
	{
		form.showError(id, hobby_err_s, 'error');

		form.hints[form.submit_obj.id].innerHTML = form.submit_obj.error_msg;
		if (form.containers[form.submit_obj.id])
			form.setContainerClass(form.submit_obj.id, 'error');
		form.inputs[form.submit_obj.id].disabled = true;
	}
	else
	{
		form.hideError(id, '', 'ok');

		form.hints[form.submit_obj.id].innerHTML = '';
		if (form.containers[form.submit_obj.id])
			form.setContainerClass(form.submit_obj.id, 'ok');
		form.inputs[form.submit_obj.id].disabled = false;
	}
}

function __check_select(form, id)
{
	var e = form.inputs[id];
	var p = form.properties[id];

	var msg = id == 'sex' ? 'Выбери пол' : 'Это поле обязательно для заполнения';

	if (p.necessary && !e.value)
		form.showError(id, msg, 'warning');
	else
		form.hideError(id);
}

function __check_dob_select(form, id)
{
	var id2 = (id && id.substr(0,4) == 'dob_') ? 'dob' : id;

	__check_dob_selects2(form, id2);
	return;
}

function __check_dob_select2(form, id)
{
	var e = form.inputs[id];
	var p = form.properties[id];

	//alert(p.necessary+' '+e.value);

	if (p.necessary && e.value*1 == 0)
	{
		form.showError(id, 'Введи дату рождения', 'warning');

		return false;
	}
	else
	{
		form.hideError(id);

		return true;
	}
}

function __check_dob_selects(form, id)
{
	if (id == 'dob')
	{
		var r1 = __check_dob_select2(form, 'dob_d');
		var r2 = __check_dob_select2(form, 'dob_m');
		var r3 = __check_dob_select2(form, 'dob_y');

		if (!r1 || !r2 || !r3)
			form.showError(id+'_d', 'Введи дату рождения', 'warning');
		else
			form.hideError(id+'_d');
	}
}

function __check_int2(form, id)
{
	var e = form.inputs[id];
	var p = form.properties[id];

	if (!check_correct_digits(e.value))
		form.showError(id, 'Введи свой возраст', 'error');
	else if (e.value > 80 || e.value <= 0)
		form.showError(id, 'Староват ты для ребзика', 'error');
	else
		form.hideError(id);

	return !form.is_error(id);
}

function print_age_numeral(form, id)
{
	var a_inp = form.inputs[id];
	var a_span = _ge(id+'_numeral_span');

	a_span.innerHTML = digit_case(a_inp.value*1, 'год', 'года', 'лет', true);
}

function __check_dob_selects2(form, id)
{
	if (id == 'dob' || id == 'entered_age')
	{
		var r1 = __check_dob_select2(form, 'dob_d');
		var r2 = __check_dob_select2(form, 'dob_m');
		var r3 = __check_dob_select2(form, 'dob_y');
		var a = __check_int2(form, 'entered_age');

		print_age_numeral(form, 'entered_age');

		if ((!r1 || !r2 || !r3) && !a)
		{
			form.showError(id+'_d', 'Введи дату рождения или возраст', 'warning');
			form.showError(id+'_m', 'Введи дату рождения или возраст', 'warning');
			form.showError(id+'_y', 'Введи дату рождения или возраст', 'warning');
			form.showError('entered_age', 'Введи дату рождения или возраст', 'warning');
		}
		else
		{
			form.hideError(id+'_d');
			form.hideError(id+'_m');
			form.hideError(id+'_y');
			form.hideError('entered_age');

			if (r3 && id == 'dob')
			{
				form.inputs['entered_age'].value = get_age(form.inputs['dob_d'].value ? form.inputs['dob_d'].value : 1, form.inputs['dob_m'].value ? form.inputs['dob_m'].value : 1, form.inputs['dob_y'].value);
			}
			else if (a && id == 'entered_age')
			{
				form.inputs['dob_y'].value = new Date().getFullYear() - form.inputs['entered_age'].value;

				if (get_age(form.inputs['dob_d'].value ? form.inputs['dob_d'].value : 1, form.inputs['dob_m'].value ? form.inputs['dob_m'].value : 1, form.inputs['dob_y'].value) < form.inputs['entered_age'].value)
					form.inputs['dob_y'].value = form.inputs['dob_y'].value - 1;
			}
		}
	}
}

function __check_city(form, id)
{
	var id2 = id+'2';

	var e = form.inputs[id];
	var e2 = form.inputs[id2];
	var p = form.properties[id];

	var msg = 'Это поле обязательно для заполнения';

	if (!e || !e2) return;

	if (e.value == -1)
	{
		e2.style.visibility = 'visible';

		if (!e2.value)
		{
			form.showError(id, 'Введи город', 'warning');
			form.showError(id2, 'Введи город', 'warning');
		}
		else
		{
			form.hideError(id);
			form.hideError(id2);
		}
	}
	else
	{
		e2.style.visibility = 'hidden';

		if (p.necessary && !e.value)
			form.showError(id, msg, 'warning');
		else
			form.hideError(id);
	}
}

function __check_city2(form, id, silent)
{
	var e = form.inputs[id];
	var p = form.properties[id];
	var silent = silent || false;

	if (!e) return;

	if (!silent)
		__check_string(form, id);

	var city = e.value.toLowerCase();

	if (in_array(city, ['москва','moscow','мск']))
	{
		$('#label-delivery-post,#label-payment-cod').hide();
		$('#label-delivery-courier,#label-payment-cash').show(); //.children('input[type=radio]').prop('checked', true)

		if ($('#delivery\\[post\\]').prop('checked'))
		{
			$('#delivery\\[post\\]').prop('checked', false);
			$('#delivery\\[courier\\]').prop('checked', true);
		}

		if ($('#payment_type\\[cod\\]').prop('checked'))
		{
			$('#payment_type\\[cod\\]').prop('checked', false);
			$('#payment_type\\[cash\\]').prop('checked', true);
		}
	}
	else
	{
		$('#label-delivery-courier,#label-payment-cash').hide();
		$('#label-delivery-post,#label-payment-cod').show();

		if ($('#delivery\\[courier\\]').prop('checked'))
		{
			$('#delivery\\[courier\\]').prop('checked', false);
			$('#delivery\\[post\\]').prop('checked', true);
		}

		if ($('#payment_type\\[cash\\]').prop('checked'))
		{
			$('#payment_type\\[cash\\]').prop('checked', false);
			$('#payment_type\\[cod\\]').prop('checked', true);
		}
	}
}

function __check_empty(form, id)
{
}

function __check_email4invite(form, id)
{
	var id2 = id+'2';
	var e = form.inputs[id];
	var e2 = form.inputs[id2];
	var p = form.properties[id];

	if (p.necessary && e.value.length == 0)
		form.showError(id, 'Это поле обязательно для заполнения', 'warning');
	else if (e.value.length > 60)
		form.showError(id, 'Максимальная длина - 60 символов', 'error');
	else if (e.value.length && !check_correct_email(e.value))
		form.showError(id, 'Введи корректный E-mail', 'warning');
	else
	{
		form.hideError(id);

		if (e2)
		{
			if (e.value != e2.value && e.value.substr(0, e2.value.length) == e2.value)
				form.hideError(id2, '', 'warning');
			else if (!e2.value.length)
				form.hideError(id2, '', 'empty');
			else if (e.value.length && e2.value.length && e.value == e2.value)
			{
				form.hideError(id, '', 'ok');
				form.hideError(id2, '', 'ok');
			}
			else //if (e.value.length && e2.value.length && e.value != e2.value)
				form.showError(id2, 'Введенные E-mail не совпадают', 'error');
		}
	}
}

function __check_radio(form, id)
{
	var ar = _ge_inputs('radio', form.form, id+'[', true);

	if (ar.length == 0)
		form.showError(id, 'Выберите один из вариантов', 'error');
	else
		form.hideError(id, '', 'ok');
}

function __check_payment_type(form, id)
{
	__check_radio(form, id);

	$('#email-necessary').toggle($('input[name="payment_type"][value="robo"]').prop('checked'));
}

function __check_delivery(form, id)
{
	__check_radio(form, id);

	var er = _ge(id+'[post]');

	if (typeof min_price_for_shipment != 'undefined' && min_price_for_shipment && er.checked)
	{
		var e = _ge('cart[total_cost]');
		var cost = e.innerHTML.replace(/[^\d]/, '') * 1;

		if (cost < min_price_for_shipment)
			form.showError(id, 'Мин.сумма заказа для доставки почтой &ndash; '+min_price_for_shipment+'р.', 'error');
		else
			form.hideError(id, '', 'ok');
	}
}

function __check_region(form, id)
{
	var e = form.inputs[id];
	var p = form.properties[id];

	__check_number(form, id);
	check_metro_district(e);
}

function __check_time(form, id)
{
	//var id1 = id.substr(id.length - 1, 1) == '2' ? id.substr(0, id.length - 1)+'1' : id;
	//var id2 = id1.substr(0, id1.length - 1)+'2';

	var e = form.inputs[id];
	var p = form.properties[id];

	if (p.min_len && e.value.length < p.min_len)
		form.showError(id, 'Мин. длина - '+p.min_len+' символ(а)', 'warning');
	else if (p.necessary && !p.min_len && !e.value.length == 0)
		form.showError(id, 'Введите время', 'warning'); //'Введите '+fields_ar[id]
	else if (p.max_len && e.value.length > p.max_len)
		form.showError(id, 'Макс. длина - '+p.max_len+' символ(ов)', 'error');
	else
		form.hideError(id);
}
