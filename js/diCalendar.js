/*
    // dimaninc js calendar class

    * 2012/11/15
        * some additions, stupid bugs fixed
        * opera bug fixed
        * parsing month-strings added

    * 2011/05/31
        * innerHTML/value bug fixed
        * onsetdate event added

    * 2011/05/24
        * multi-dates support added

    * 2011/01/25
        * range date selection added (2 inputs, 1 calendar for both)

    * 2011/01/24
        * null date selection added, clear() method added

    * 2011/01/19
        * rewritten for snowsh

  --[ notes ]-----------------------------------------------------------------------------

  * if cfg.date2 set, then calendar works with date range
    if not, the only one date gets picked

  * if cfg.date1 and cfg.date2 could have be an id of an object or object
    cfg.date1 and cfg.date2 could be only ids of objects, if separate inputs for fields used

  * separate inputs should have [dd] [dm] [dy] [th] [tm] endings for its ids
    (day, month, year, hour, minute respectively)

*/

function diCalendar(cfg)
{
  var self = this;

  this.set_config = function(cfg)
  {
    for (var i in cfg)
    {
      this.cfg[i] = cfg[i];
    }
  };

  this.get_prev_link_html = function()
  {
    var class_prefix = this.cfg.able_to_go_to_past ? '' : 'in';
    var html = '';

    if (this.cfg.prev_year_link_html)
      html += '<p id="'+this.id+'_prev_year" class="prev-'+class_prefix+'active" onclick="'+this.instance_name+'.go_to_prev_year();">'+this.cfg.prev_year_link_html+'</p>';

    if (this.cfg.prev_link_html)
      html += '<p id="'+this.id+'_prev" class="prev-'+class_prefix+'active" onclick="'+this.instance_name+'.go_to_prev_month();">'+this.cfg.prev_link_html+'</p>';

    return html;
  };

  this.get_next_link_html = function()
  {
    var html = '';

    if (this.cfg.next_link_html)
      html += '<p id="'+this.id+'_next" class="next-active" onclick="'+this.instance_name+'.go_to_next_month();">'+this.cfg.next_link_html+'</p>';

    if (this.cfg.next_year_link_html)
      html += '<p id="'+this.id+'_next_year" class="next-active" onclick="'+this.instance_name+'.go_to_next_year();">'+this.cfg.next_year_link_html+'</p>';

    return html;
  };

  this.get_close_link_html = function()
  {
    return this.cfg.close_link_html ? '<p id="'+this.id+'_close" class="dic-close" onclick="'+this.instance_name+'.hide();">'+this.cfg.close_link_html+'</p>' : '';
  };

  this.get_clear_link_html = function()
  {
    return this.cfg.clear_link_html ? '<p id="'+this.id+'_clear" class="dic-clear" onclick="'+this.instance_name+'.clear();">'+this.cfg.clear_link_html+'</p>' : '';
  };

  this.get_day_onclick = function(date)
  {
    return this.range_select
      ? this.instance_name+'.prompt_for_idx_and_set_date(\''+date+'\');'
      : this.instance_name+'.set_date(\''+date+'\');';
  };

  this.get_prompt_for_idx_div = function()
  {
    return '<div id="'+this.id+'_prompt_for_idx" class="dicalendar-prompt-for-idx"></div>';
  };

  this.prompt_for_idx_and_set_date = function(date)
  {
    var e = _ge(this.id+'_prompt_for_idx');

    if (e)
    {
      var date_ar = typeof date == 'string' && date ? date.split(/[\/\.:\x20]+/) : [];
      var d = date_ar[0] ? lead0(date_ar[0]*1) : 0;
      var m = date_ar[1] ? lead0(date_ar[1]*1) : 0;

      e.innerHTML =
        '<div onclick="'+this.instance_name+'.set_date(\''+date+'\', 1);">'+this.cfg.date1_select_str.replace('%d', d).replace('%m', m)+'</div>'+
        '<div onclick="'+this.instance_name+'.set_date(\''+date+'\', 2);">'+this.cfg.date2_select_str.replace('%d', d).replace('%m', m)+'</div>';

      var td_id = this.id+'_'+get_big_yday(this.str_to_obj(date));
      var td = _ge(td_id);

      e.style.display = 'block';
      e.style.left = (_get_left(td) + Math.round(td.offsetWidth / 2))+'px';
      e.style.top = (_get_top(td) + Math.round(td.offsetHeight / 2))+'px';
    }
  };

  this.clear = function()
  {
    this.date1 = false;
    this.date2 = false;

    this.prepare_dates();

    this.set_str_date_to_input(1, false);
    this.set_str_date_to_input(2, false);

    this.print();
  };

  this.get_str_date_from_input = function(e_obj)
  {
    var s = false;

    if (e_obj.e)
    {
      if (typeof e_obj.e.value != 'undefined') s = e_obj.e.value;
      else if (typeof e_obj.e.innerHTML != 'undefined') s = e_obj.e.innerHTML;

      if (s)
      {
        var dt = this.str_to_obj(s);
        s = this.get_date_str(dt, 'calendar');
      }
    }
    else
    {
      s = e_obj.dd.value+'.'+e_obj.dm.value+'.'+e_obj.dy.value;
      if (e_obj.th && e_obj.tm)
        s += ' '+e_obj.th.value+':'+e_obj.tm.value;
    }

    return s;
  };

  this.set_str_date_to_input = function(idx, date)
  {
    var dt = this.str_to_obj(date);

    var e = _ge(this.id+'_timestamp'+idx);
    if (e) e.value = get_time(dt);

    if (this.e_ar[idx])
    {
      if (this.e_ar[idx].e)
      {
        var s = date ? this.get_date_str(date) : '';

        if (typeof this.e_ar[idx].e.value != 'undefined') this.e_ar[idx].e.value = s;
        else if (typeof this.e_ar[idx].e.innerHTML != 'undefined') this.e_ar[idx].e.innerHTML = s;
      }
      else if (this.e_ar[idx].dd)
      {
        if (typeof dt == 'object')
        {
          this.e_ar[idx].dd.value = lead0(dt.getDate());
          this.e_ar[idx].dm.value = lead0(dt.getMonth() + 1);
          this.e_ar[idx].dy.value = lead0(dt.getFullYear());

          if (this.e_ar[idx].th && this.e_ar[idx].tm)
          {
            this.e_ar[idx].th.value = lead0(dt.getHours());
            this.e_ar[idx].tm.value = lead0(dt.getMinutes());
          }
        }
        else
        {
          this.e_ar[idx].dd.value = '';
          this.e_ar[idx].dm.value = '';
          this.e_ar[idx].dy.value = '';

          if (this.e_ar[idx].th && this.e_ar[idx].tm)
          {
            this.e_ar[idx].th.value = '';
            this.e_ar[idx].tm.value = '';
          }
        }
      }
    }
  };

  this.get_e_ar = function(date)
  {
    var ar = false;

    if (typeof date == 'string')
    {
      ar = {
        e: _ge(date),
        dd: _ge(date+'[dd]'),
        dm: _ge(date+'[dm]'),
        dy: _ge(date+'[dy]'),
        th: _ge(date+'[th]'),
        tm: _ge(date+'[tm]')
      };

      if (!ar.e && !ar.dd)
      {
        ar = {
          e: _ge(date),
          dd: _ge(date+'_d'),
          dm: _ge(date+'_m'),
          dy: _ge(date+'_y'),
          th: _ge(date+'_th'),
          tm: _ge(date+'_tm')
        }
      }

      if (ar.th) eval('_add_event(ar.th, \'keyup\', function() {'+this.instance_name+'.init();});');
      if (ar.tm) eval('_add_event(ar.tm, \'keyup\', function() {'+this.instance_name+'.init();});');
    }
    else if (typeof date == 'object')
    {
      ar = {
        e: date,
        dd: false,
        dm: false,
        dy: false,
        th: false,
        tm: false
      }
    }
    else alert('wrong id/obj passed: '+date);

    if (ar && ar.e) eval('_add_event(ar.e, \'keyup\', function() {'+this.instance_name+'.init();});');

    return ar;
  };

  this.go_to_prev_month = function()
  {
    if (!this.cfg.able_to_go_to_past && this.showing_dt <= this.today_dt)
      return false;

    var a = {m: this.showing_dt.getMonth() + 1, y: this.showing_dt.getFullYear()};
    a = this.get_prev_m_y(a.m, a.y);

    this.showing_dt = new Date(a.y, a.m - 1, 1, 12, 0, 0, 0);

    this.print();
    this.update_prev_next_buttons();
  };

  this.go_to_next_month = function()
  {
    var a = {m: this.showing_dt.getMonth() + 1, y: this.showing_dt.getFullYear()};
    a = this.get_next_m_y(a.m, a.y);

    this.showing_dt = new Date(a.y, a.m - 1, 1, 12, 0, 0, 0);

    this.print();
    this.update_prev_next_buttons();
  };

  this.go_to_prev_year = function()
  {
    if (!this.cfg.able_to_go_to_past && this.showing_dt <= this.today_dt)
      return false;

    this.showing_dt = new Date(this.showing_dt.getFullYear() - 1, this.showing_dt.getMonth(), 1, 12, 0, 0, 0);

    this.print();
    this.update_prev_next_buttons();
  };

  this.go_to_next_year = function()
  {
    this.showing_dt = new Date(this.showing_dt.getFullYear() + 1, this.showing_dt.getMonth(), 1, 12, 0, 0, 0);

    this.print();
    this.update_prev_next_buttons();
  };

  this.update_prev_next_buttons = function()
  {
    var e1 = _ge(this.id+'_prev');
    //var e2 = _ge(this.id+'_next');

    if (e1 && !this.cfg.able_to_go_to_past)
      e1.className = this.showing_dt <= this.today_dt ? 'prev-inactive' : 'prev-active';
  };

  this.has_time_fields = function(idx)
  {
    return this.e_ar[idx].th && this.e_ar[idx].tm ? true : false;
  };

  this.set_date = function(date, idx)
  {
      var e;

    if (typeof idx == 'undefined')
      idx = 1;

    if (this.range_select)
    {
      var d = get_time(this.str_to_obj(date));
      var d1 = get_time(this.str_to_obj(this.date1));
      var d2 = get_time(this.str_to_obj(this.date2));

      /*
      // finding out, which date (opening or closing) has been set
      if (d < d1) idx = 1;
      else if (d > d2) idx = 2;
      else if (d - d1 < d2 - d) idx = 1;
      else idx = 2;
      //
      */

      if (this.has_time_fields(idx))
        date += ' '+this.e_ar[idx].th.value+':'+this.e_ar[idx].tm.value;

      if (idx == 1) this.date1 = date;
      else this.date2 = date;

      this.last_edited_idx = idx;

      e = _ge(this.id+'_prompt_for_idx');
      if (e)
      {
        e.style.display = 'none';
      }
    }
    else if (this.cfg.mode == 'multi') // few dates at once
    {
      idx = 1;

      if (this.has_time_fields(idx))
        date += ' '+this.e_ar[idx].th.value+':'+this.e_ar[idx].tm.value;

      this.date1 = date;
      this.date2 = date;

      var dt = this.str_to_obj(date);

      this.dates_ar.push(dt);
      this.yday_dates_ar.push(get_big_yday(dt));

      e = _ge(this.cfg.dates_container);
      if (e)
      {
        if (trim(e.innerHTML))
          e.innerHTML += ', ';
        else
          e.innerHTML = '';

        e.innerHTML += lead0(dt.getDate())+'.'+lead0(dt.getMonth() + 1)+'.'+lead0(dt.getFullYear())+' '+lead0(dt.getHours())+':'+lead0(dt.getMinutes())+
          '<input type=hidden name="'+this.cfg.date1+'_ar[]" value="'+Math.round(dt.getTime() / 1000)+'">';

        this.init_position();
      }
    }
    else
    {
      idx = 1;

      if (this.has_time_fields(idx))
        date += ' '+this.e_ar[idx].th.value+':'+this.e_ar[idx].tm.value;

      this.date1 = date;
      this.date2 = date;
    }

    this.prepare_dates();

    this.set_str_date_to_input(idx, date);

    if (!this.range_select && this.cfg.mode != 'multi')
      this.hide();

    this.print();

    if (typeof this.cfg.onsetdate == 'function')
      this.cfg.onsetdate(this);
  };

  this.str_to_obj = function(str)
  {
    var date_ar = typeof str == 'string' && str ? str.split(/[\/\.:\x20]+/) : [];

    if (date_ar.length < 5)
    {
      date_ar[3] = 12;
      date_ar[4] = 0;
    }

    if (date_ar[1]*1 == 0 || isNaN(date_ar[1]*1))
    {
      date_ar[1] = ar_indexOf(date_ar[1], dicalendar_lng_ar[this.cfg.language].month_titles);
      if (date_ar[1] == -1)
        date_ar[1] = ar_indexOf(date_ar[1], dicalendar_lng_ar[this.cfg.language].nominative_month_titles);
    }

    return date_ar[0]*1 && date_ar[1]*1 && date_ar[2]*1 && date_ar[2].length == 4
      ? new Date(date_ar[2]*1, date_ar[1]*1 - 1, date_ar[0]*1, date_ar[3]*1, date_ar[4]*1, 0)
      : false;
  };

  this.prepare_dates = function()
  {
      var i;

    this.dt1 = this.str_to_obj(this.date1);
    this.dt2 = this.str_to_obj(this.date2);

    if (!this.dt1 || !this.dt2)
    {
      this.showing_dt = new Date();

      return false;
    }

    this.yday1 = get_yday(this.dt1);
    this.yday2 = get_yday(this.dt2);

    var old_dt = this.showing_dt;

    if (!this.range_select || this.last_edited_idx == 1)
    {
      this.showing_dt = new Date(this.dt1);

      if (!this.range_select && this.months_to_show > 2)
      {
        for (i = 0; i < Math.ceil(this.months_to_show / 2) - 1; i++)
          this.showing_dt = this.get_prev_m_y_obj(this.showing_dt);
      }
    }
    else
    {
      this.showing_dt = new Date(this.dt2);

      for (i = 0; i < this.months_to_show - 1; i++)
        this.showing_dt = this.get_prev_m_y_obj(this.showing_dt);
    }

    if (
        this.range_select &&
        old_dt &&
        (
         Math.abs(get_big_yday(old_dt) - get_big_yday(this.showing_dt)) < 30 ||
         get_big_yday(this.showing_dt) < get_big_yday(this.today_dt)
        )
       )
      this.showing_dt = old_dt;
  };

  this.print = function()
  {
    var a = {m: this.showing_dt.getMonth() + 1, y: this.showing_dt.getFullYear()};
    var html = '';
    var class_name;

    html += '<input type="hidden" id="'+this.id+'_timestamp1" name="'+this.id+'_timestamp1" value="'+get_time(this.dt1)+'" /><input type="hidden" id="'+this.id+'_timestamp2" name="'+this.id+'_timestamp2" value="'+get_time(this.dt2)+'" />';
    html += this.get_prev_link_html();

    for (var i = 1; i <= this.months_to_show; i++)
    {
      if (i == 1) class_name = 'first_month';
      else if (i == this.months_to_show) class_name = 'last_month';
      else class_name = 'center_month';

      html += '<div class="'+class_name+'">'+this.get_month_html(a.m, a.y)+'</div>';

      a = this.get_next_m_y(a.m, a.y);
    }

    html += this.get_next_link_html();
    html += this.get_close_link_html();

    html += this.get_clear_link_html();

    if (this.range_select)
    {
      html += this.get_prompt_for_idx_div();
    }

    this.e.innerHTML = html;

    if (this.range_select)
    {
      var e = _ge(this.id+'_prompt_for_idx');

      if (e)
      {
        // moving the div off the calendar container for its proper positioning
        document.body.appendChild(e);
      }
    }
  };

  this.get_month_html = function(m, y)
  {
    var is_leap = isleapyear(y);

    var html = '';

    html += '<b>'+this.get_month_title(m)+' '+y+'</b>';
    html += '<table class="dimonth">';

    if (this.cfg.show_weekday_titles)
      html += this.print_head_weekdays();

    var m_start = new Date(y, m - 1, 1, 12, 0, 0, 0);
    var m_finish = new Date(y, m - 1, days_in_mon_ar[is_leap][m - 1], 12, 0, 0, 0);

    var wd_of_m_start = get_wd(m_start);
    var wd_of_m_finish = get_wd(m_finish);

    var days_to_show = Math.round((m_finish.getTime() - m_start.getTime()) / 86400000);
    days_to_show += wd_of_m_start - 1;
    days_to_show += 7 - wd_of_m_finish;
    var weeks_to_show = Math.ceil(days_to_show / 7);

    var d = 0;
    var yday = get_yday(m_start) - 1;
    var td, class_attr, day_id_attr;

    var big_yday;
    //var big_yday1 = get_big_yday(this.dt1.getFullYear(), this.yday1);
    //var big_yday2 = get_big_yday(this.dt2.getFullYear(), this.yday2);
    //var big_today_yday = get_big_yday(this.today_dt.getFullYear(), this.today_yday);
    var big_yday1 = get_big_yday(this.dt1);
    var big_yday2 = get_big_yday(this.dt2);
    var big_today_yday = get_big_yday(this.today_dt);

    for (var i = 1; i <= weeks_to_show; i++)
    {
      html += '<tr>';

      for (var j = 1; j <= 7; j++)
      {
        var in_past = false;
        var is_empty = false;

        if ((i > 1 && i < weeks_to_show) || (i == 1 && j >= wd_of_m_start) || (i == weeks_to_show && j <= wd_of_m_finish))
        {
          d++;
          yday++;
          big_yday = get_big_yday(y, yday);
          td = lead0(d);
        }
        else
        {
          td = '&nbsp;';
          big_yday = 0;
        }

        var is_selected =
          (big_yday1 && big_yday2 && big_yday >= big_yday1 && big_yday <= big_yday2) ||
          (in_array(big_yday, this.yday_dates_ar))
          ? true : false;

        if (j == 6 || j == 7)
        {
          if (big_yday < big_today_yday)
          {
            if (is_selected)
              class_attr = ' class="selected_past_weekend"';
            else
              class_attr = ' class="past_weekend"';

            in_past = true;
          }
          else if (is_selected)
            class_attr = ' class="selected_weekend"';
          else
            class_attr = ' class="weekend"';
        }
        else
        {
          if (big_yday < big_today_yday)
          {
            if (is_selected)
              class_attr = ' class="selected_past"';
            else
              class_attr = ' class="past"';
            in_past = true;
          }
          else if (is_selected)
          {
            class_attr = ' class="selected"';
          }
          else
          {
            class_attr = '';
          }
        }

        if (td == '&nbsp;')
        {
          class_attr = ' class="empty"';
          is_empty = true;
        }

        day_id_attr = big_yday ? ' id="'+this.id+'_'+big_yday+'"' : '';
        day_id_attr += is_empty ? '' : ' onclick="'+this.get_day_onclick(lead0(d)+'.'+lead0(m)+'.'+y)+'"';

        html += '<td'+class_attr+day_id_attr+'>'+td+'</td>';
      }

      html += '</tr>';
    }

    html += '</table>';

    return html;
  };

  this.print_head_weekdays = function()
  {
    var html = '';

    for (i = 1; i <= 7; i++)
    {
      var class_attr = i == 6 || i == 7 ? ' class="weekend"' : '';

      html += '<td'+class_attr+'>'+this.get_wd_title(i)+'</td>';
    }

    return '<tr class="head">'+html+'</tr>';
  };

  this.get_month_title = function(m)
  {
    return dicalendar_lng_ar[this.cfg.language].nominative_month_titles[m];
  };

  this.get_date_str = function(date, output_type)
  {
      var d, m, y;

    if (typeof date == 'undefined') date = this.showing_dt;
    if (typeof output_type == 'undefined') output_type = 'input';

    if (typeof date == 'object')
    {
      d = date.getDate();
      m = date.getMonth() + 1;
      y = date.getFullYear();
    }
    else
    {
      var date_ar = date.split(/\./);
      d = date_ar[0]*1;
      m = date_ar[1]*1;
      y = date_ar[2]*1;
    }

    if (output_type == 'input')
      return d+' '+dicalendar_lng_ar[this.cfg.language].month_titles[m]+' '+y;
    else if (output_type == 'uri')
      return y+'-'+lead0(m)+'-'+lead0(d);
    else if (output_type == 'calendar')
      return lead0(d)+'.'+lead0(m)+'.'+y;
    else
      return 'unknown output_type='+output_type;
  };

  this.get_wd_title = function(wd)
  {
    if (typeof dicalendar_lng_ar[this.cfg.language].wd_titles[wd - 1] == 'undefined')
      wd = wd % dicalendar_lng_ar[this.cfg.language].wd_titles.length;

    return dicalendar_lng_ar[this.cfg.language].wd_titles[wd - 1];
  };

  this.get_prev_m_y = function(m, y)
  {
    if (--m < 1)
    {
      m = 12;
      y--;
    }

    return {m: m, y: y};
  };

  this.get_next_m_y = function(m, y)
  {
    if (++m > 12)
    {
      m = 1;
      y++;
    }

    return {m: m, y: y};
  };

  this.get_prev_m_y_obj = function(obj)
  {
    var m = obj.getMonth() + 1;
    var y = obj.getFullYear();

    if (--m < 1)
    {
      m = 12;
      y--;
    }

    obj.setMonth(m - 1);
    obj.setFullYear(y);

    return obj;
  };

  this.get_next_m_y_obj = function(obj)
  {
    var m = obj.getMonth() + 1;
    var y = obj.getFullYear();

    if (++m > 12)
    {
      m = 1;
      y++;
    }

    obj.setMonth(m - 1);
    obj.setFullYear(y);

    return obj;
  };

  this.show = function()
  {
    if (this.state)
      return false;

    this.init();
    this.init_position();

    this.e.style.display = 'block';
    this.state = true;

    if (typeof dip != 'undefined' && !this.cfg.no_gray) dip.show_bg();

    return false;
  };

  this.hide = function()
  {
    if (!this.state)
      return false;

    this.e.style.display = 'none';
    if (typeof dip != 'undefined' && !this.cfg.no_gray) dip.hide_bg();
    this.state = false;

    return false;
  };

  this.toggle = function()
  {
    if (this.state)
      this.hide();
    else
      this.show();
  };

  this.init = function()
  {
    this.date1 = this.get_str_date_from_input(this.e_ar[1]);
    if (this.e_ar[2])
    {
      this.date2 = this.get_str_date_from_input(this.e_ar[2]);
      this.range_select = true;
    }
    else
    {
      this.date2 = this.date1;
      this.range_select = false;
    }

    this.today_yday = get_yday(this.today_dt);

    // if mode is multi
    this.yday_dates_ar = [];
    for (var i = 0; i < this.dates_ar.length; i++)
    {
      this.yday_dates_ar.push(get_big_yday(this.dates_ar[i]));
    }
    //

    this.prepare_dates();
    this.print();
    this.setupBgClick();

    return this;
  };

  this.add_events_to_inputs = function()
  {
    var events_ar = ['click', 'focus', 'keyup', 'change'];
    var method = 'show';

    for (var idx = 1; idx <= 2; idx++) if (this.cfg.add_events_to_date[idx] && this.cfg.add_events_to_date[idx].length)
    {
      var events_ar2 = in_array('all', this.cfg.add_events_to_date[idx]) ? events_ar : this.cfg.add_events_to_date[idx];

      for (var i in events_ar2)
      {
        if (this.e_ar[idx].e)
        {
          eval('_add_event(this.e_ar[idx].e, events_ar[i], function(){'+this.instance_name+'.'+method+'();});');
        }
        else if (this.e_ar[idx].dd)
        {
          eval('_add_event(this.e_ar[idx].dd, events_ar[i], function(){'+this.instance_name+'.'+method+'();});');
          eval('_add_event(this.e_ar[idx].dm, events_ar[i], function(){'+this.instance_name+'.'+method+'();});');
          eval('_add_event(this.e_ar[idx].dy, events_ar[i], function(){'+this.instance_name+'.'+method+'();});');

          if (this.e_ar[idx].th && this.e_ar[idx].tm)
          {
            eval('_add_event(this.e_ar[idx].th, events_ar[i], function(){'+this.instance_name+'.'+method+'();});');
            eval('_add_event(this.e_ar[idx].tm, events_ar[i], function(){'+this.instance_name+'.'+method+'();});');
          }
        }
      }
    }

    return this;
  };

    this.add_events_to_button = function () {
        if (!this.cfg.uid) {
            return this;
        }

        var $btn = $('[data-calendar-uid="{0}"]'.format(this.cfg.uid));

        $btn.on('click', function() {
            self.toggle();
        });

        return this;
    };

  this.init_position = function()
  {
    if (this.cfg.no_positioning) return this;

    var x, y, pos;
    var $stick = $(this.stick_to_e);

    switch (this.cfg.position_base)
    {
      case 'parent':
        pos = $stick.position();
        x = pos.left;
        y = pos.top + $stick.outerHeight() + 2;
        break;

      case 'document':
        x = _get_left(this.stick_to_e);
        y = _get_top(this.stick_to_e) + this.stick_to_e.offsetHeight;
        break;
    }

    this.e.style.left = x + 'px';
    this.e.style.top = y + 'px';

    return this;
  };

  this.setupBgClick = function() {
    if (!this.cfg.no_gray) {
      dip.checkBg().onBg('click.dicalendar', function() {
        self.hide();
      });
    }

    return this;
  };

  // initiating
  this.instance_name = cfg.instance_name;
  this.idx = ++dicalendar_counter;
  this.id = 'dicalendar['+this.idx+']';
  this.state = false;
  this.months_to_show = cfg.months_to_show;

  dicalendar_instances_ar.push(this.instance_name);

  this.e_ar = typeof cfg.date2 != 'undefined' && cfg.date2
    ? {1: this.get_e_ar(cfg.date1), 2: this.get_e_ar(cfg.date2)}
    : {1: this.get_e_ar(cfg.date1), 2: false};

  // creating a container for the calendar
  if (typeof cfg.stick_to == 'string')
  {
    this.stick_to_e = _ge(cfg.stick_to);

    if (!this.stick_to_e)
      this.stick_to_e = _ge(cfg.stick_to+'[dd]');

    if (!this.stick_to_e)
      this.stick_to_e = _ge(cfg.stick_to+'_d');
  }
  else if (typeof cfg.stick_to == 'object')
    this.stick_to_e = cfg.stick_to;
  else
    this.stick_to_e = this.e_ar[1].e ? this.e_ar[1].e : this.e_ar[1].dd;

  if (!this.stick_to_e)
    console.log('error! "stick to" element not found');

  this.e = document.createElement('DIV');
  this.e.id = this.id;
  this.e.className = 'dicalendar';
  //

  this.today_dt = new Date();
  this.showing_dt = false;
  this.last_edited_idx = 1;

  this.dates_ar = [];

  this.cfg = {};

  this.set_config({
      instance_name: null,
      uid: null,
    add_events_to_date: {1: [], 2: []},
    able_to_go_to_past: true,
    show_weekday_titles: true,
    stick_to: false, // id or object; if false, then sticking to date1 element
    no_positioning: false,
    position_base: 'document',
    no_gray: false,
    language: 'rus',
    mode: 'single', // single - one date at once, multi - few dates at once
    onsetdate: false,
    clear_link_html: '', // if not empty - showing the 'clear' button, which sets the date to null
    prev_link_html: '&laquo;', //
    next_link_html: '&raquo;', //
    close_link_html: '&nbsp;' //
  });

  this.set_config(cfg);

  this.set_config({
    date1_select_str: dicalendar_lng_ar[this.cfg.language].date1_select_str, // only for range selection:
    date2_select_str: dicalendar_lng_ar[this.cfg.language].date2_select_str // these messages will pop up when user clicks on a date
  });

  this.init_position();

  this.stick_to_e.parentNode.insertBefore(this.e, this.stick_to_e);

  this.init();
  this.add_events_to_inputs().add_events_to_button();
  //
}

var dicalendar_counter = 0;
var dicalendar_instances_ar = [];

var days_in_mon_ar = [];
days_in_mon_ar[false] = [31,28,31,30,31,30,31,31,30,31,30,31];
days_in_mon_ar[true] = [31,29,31,30,31,30,31,31,30,31,30,31];

var dicalendar_lng_ar = {
  eng: {
    month_titles: ['','jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'],
    nominative_month_titles: ['','January','February','March','April','May','June','July','August','September','October','November','December'],
    wd_titles: ['mo','tu','we','th','fr','sa','su'],
    date1_select_str: '%d.%m is a beginning date',
    date2_select_str: '%d.%m is a finishing date'
  },
  rus: {
    month_titles: ['','января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'],
    nominative_month_titles: ['','Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'],
    wd_titles: ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'],
    date1_select_str: '%d.%m - начальная дата',
    date2_select_str: '%d.%m - конечная дата'
  }
};
