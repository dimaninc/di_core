var LocalizationAdmin;

LocalizationAdmin = (function() {
  function LocalizationAdmin() {
    this.setupForm().setupList();
  }

  LocalizationAdmin.prototype.setupForm = function() {
    return this.setupAutoHeight();
  };

  LocalizationAdmin.prototype.setupList = function() {
    return this.setupExport();
  };

  LocalizationAdmin.prototype.setupAutoHeight = function() {
    setTimeout((function(_this) {
      return function() {
        return $('.diadminform-row').filter('[data-field$="value"]').find('textarea').autoHeight();
      };
    })(this), 100);
    return this;
  };

  LocalizationAdmin.prototype.setupExport = function() {
    $('.filter-block [name="export"]').on('click', function() {
      var $cb, $out, $t, lines, names, rawLines, text;
      $t = $('.dinicetable');
      $cb = $t.find('tr td.id .checked, tr td.id input:checkbox:checked');
      lines = [];
      rawLines = [];
      names = [];
      $out = $('.export-block');
      if ($out.length && $out.is(':visible')) {
        $out.hide();
        return false;
      }
      if (!$cb.length) {
        alert('Выберите хотя бы один Токен');
        return false;
      }
      $cb.each(function() {
        var $e, $td, field, fields, q, s, val, values;
        fields = [];
        values = [];
        $td = $(this).parent();
        while ($td = $td.next('td:eq(0)')) {
          if ($td.hasClass('btn')) {
            break;
          }
          field = $td.data('field');
          $e = $td.find('[data-purpose="orig"]');
          val = $e.data('orig-value') || $e.html();
          if (val === void 0 || val === null) {
            val = $td.data('orig-value') || $td.html();
          }
          val = val.replace(/'/g, '\\\'').replace(/"/g, '\\\"');
          if (field === 'name') {
            names.push(val);
          }
          fields.push(field);
          values.push(val);
        }
        q = "INSERT IGNORE INTO `" + ($t.data('table')) + "`(`" + (fields.join('`,`')) + "`)\n\u0009\u0009\u0009VALUES('" + (values.join('\',\'')) + "');";
        s = "$this->getDb()->q(\"" + q + "\");";
        lines.push(s);
        rawLines.push(q);
        return true;
      });
      if (!$out.length) {
        $out = $('<div class="export-block"><textarea></textarea></div>').insertAfter($(this).parent());
      }
      text = names.map((function(_this) {
        return function(n) {
          return "'" + n + "',";
        };
      })(this)).concat([''], lines, [''], rawLines).join('\n');
      $out.show().find('textarea').val(text);
      return false;
    });
    return this;
  };

  return LocalizationAdmin;

})();

//# sourceMappingURL=LocalizationAdmin.js.map
