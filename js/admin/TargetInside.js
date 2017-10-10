// Generated by CoffeeScript 1.10.0
var TargetInside;

TargetInside = (function() {
  var $targetId, $targetType;

  $targetType = null;

  $targetId = null;

  function TargetInside(opts) {
    this.opts = $.extend({
      types: [],
      targets: [],
      emptyTitles: {
        type: '[ Не выбрано ]',
        id: '[ Не выбрано ]'
      },
      selected: {
        type: null,
        id: null
      }
    }, opts);
    this.setupSelects();
  }

  TargetInside.prototype.emptyOptionsNeeded = function() {
    return !!this.opts.emptyTitles.type;
  };

  TargetInside.prototype.createOption = function(title, id) {
    return $('<option value="{0}">{1}</option>'.format(id, title));
  };

  TargetInside.prototype.setupSelects = function() {
    var $s, self;
    self = this;
    $targetType = $('select[name="target_type"],input[name="target_type"]');
    $targetId = $('select[name="target_id"],input[name="target_id"]');
    if ($targetType.is('input')) {
      $s = $('<select name="target_type" id="target_type"></select>');
      $targetType.replaceWith($s);
      $targetType = $s;
    } else {
      $targetType.find('option').remove();
    }
    if ($targetId.is('input')) {
      $s = $('<select name="target_id" id="target_id"></select>');
      $targetId.replaceWith($s);
      $targetId = $s;
    }
    if (this.emptyOptionsNeeded()) {
      $targetType.append(this.createOption(this.opts.emptyTitles.type, 0));
    }
    $.each(this.opts.types, function(id, title) {
      $targetType.append(self.createOption(title, id));
      if (!(self.opts.selected.type * 1)) {
        self.opts.selected.type = id * 1;
      }
      return true;
    });
    $targetType.val(this.opts.selected.type).on('focus blur change click keyup', function() {
      self.loadTargets(this.value);
      if (this.value * 1) {
        self.opts.selected.type = this.value;
      }
      return true;
    });
    $targetId.on('focus blur change click keyup', function() {
      if (this.value * 1) {
        self.opts.selected.id = this.value;
      }
      return true;
    });
    this.loadTargets();
    return this;
  };

  TargetInside.prototype.loadTargets = function(type) {
    var self;
    if (type == null) {
      type = this.opts.selected.type;
    }
    if (this.opts.selected.type * 1 === this.value * 1 || !type) {
      return this;
    }
    self = this;
    $targetId.find('option').remove();
    if (this.emptyOptionsNeeded()) {
      $targetId.append(this.createOption(this.opts.emptyTitles.id, 0));
    }
    $.each(this.opts.targets[type], function(id, ar) {
      return $targetId.append(self.createOption(ar.title, ar.id));
    });
    if (this.opts.selected.type * 1 === $targetType.val() * 1) {
      $targetId.val(this.opts.selected.id);
    }
    return this;
  };

  return TargetInside;

})();

//# sourceMappingURL=TargetInside.js.map